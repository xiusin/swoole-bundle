<?php

namespace xiusin\SwooleBundle\DependencyInjection;

use xiusin\SwooleBundle\Plugins\ChanInterface;
use xiusin\SwooleBundle\Plugins\ProcessInterface;
use xiusin\SwooleBundle\Plugins\ServerEventListener;
use xiusin\SwooleBundle\Plugins\TableInterface;
use App\Kernel;
use RuntimeException;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Symfony\Bundle\TwigBundle\Controller\ExceptionController;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Twig\Environment;

/**
 * Class Server.
 */
class Server
{
    const WORKER_START = 'WorkerStart';

    const START = 'Start';

    const SHUTDOWN = 'Shutdown';

    const WORKER_STOP = 'WorkerStop';

    const WORKER_EXIT = 'WorkerExit';

    const CONNECT = 'Connect';

    const RECEIVE = 'Receive';

    const PACKET = 'Packet';

    const OPEN = 'Open';

    const MESSAGE = 'Message';

    const CLOSE = 'Close';

    const BUFFER_FULL = 'BufferFull';

    const BUFFER_EMPTY = 'BufferEmpty';

    const TASK = 'Task';

    const REQUEST = 'Request';

    const FINISH = 'Finish';

    const PIPE_MESSAGE = 'PipeMessage';

    const WORKER_ERROR = 'WorkerError';

    const MANAGER_START = 'ManagerStart';

    const MANAGER_STOP = 'ManagerStop';

    private $chans = [];

    private $tables = [];

    private $config = [];

    private $serverConfig = [];

    private $io;

    private $container;

    private $kernel;

    protected $locker;

    /**
     * @var \swoole_http_server | \swoole_websocket_server
     */
    private $handler;

    /**
     * @param ContainerInterface $container
     * @param SymfonyStyle $io
     * @param bool $daemonize
     */
    public function __construct(ContainerInterface $container, SymfonyStyle $io, $daemonize = false)
    {
        $this->container = $container;
        $this->io = $io;
        $this->config = $container->getParameter('swoole.config');
        $this->config['config']['daemonize'] = $daemonize;
        $this->locker = new \swoole_lock(SWOOLE_RWLOCK);
        $handlerClass = $this->config['server'];
        $this->handler = new $handlerClass($this->config['http_host'], $this->config['http_port'], SWOOLE_PROCESS);
        $this->serverConfig = $this->config['config'];    //覆盖config内容
        $this->handler->set($this->getSetting());

    }

    public function getHandler()
    {
        return $this->handler;
    }

    private function getSetting()
    {
        return [
            'worker_num' => $this->serverConfig['worker_num'],
            'upload_tmp_dir' => sys_get_temp_dir(),
            'enable_static_handler' => $this->serverConfig['enable_static_handler'],
            'document_root' => $this->serverConfig['document_root'],
            'http_compression' => $this->serverConfig['http_compression'],
            'log_level' => $this->serverConfig['log_level'],
            'log_file' => $this->serverConfig['log_file'],
            'daemonize' => $this->serverConfig['daemonize'] ? 1 : 0,
            'pid_file' => WebServer::getInstance()->getPidFile(),
        ];
    }

    public function addChan(string $name, ChanInterface $chan)
    {
        $this->locker->lock();
        $this->chans[$name] = $chan;
        $this->locker->unlock();
    }

    public function getChan($name)
    {
        $this->locker->lock();
        $chan = $this->chans[$name];
        $this->locker->unlock();
        return $chan;
    }

    public function addTable(string $name, TableInterface $table)
    {
        $this->locker->lock();
        $this->tables[$name] = $table;
        $this->locker->unlock();
    }

    public function getTable($name)
    {
        $this->locker->lock();
        $table = $this->tables[$name];
        $this->locker->unlock();
        return $table;
    }

    private function attachProcess()
    {
        $processes = $this->config['processes'];
        foreach ($processes as $processName) {
            if (in_array(ProcessInterface::class, class_implements($processName, true))) {
                $this->handler->addProcess(new \swoole_process(function ($process) use (&$processName) {
                    /**
                     * @var $processHandler ProcessInterface
                     */
                    $processHandler = new $processName();
                    $processHandler->handle($process, $this->handler);
                }));
            } else {
                throw new \RuntimeException('processes 配置错误, 请继承' . ProcessInterface::class . '接口');
            }
        }
    }

    private function attachTables()
    {
        $tables = $this->config['tables'];
        foreach ($tables as $table) {
            if (in_array(TableInterface::class, class_implements($table, true))) {
                $this->addTable($table, new $table());
            } else {
                throw new \RuntimeException('table 配置错误, 请继承' . TableInterface::class . '接口');
            }
        }
    }

    private function attachChannel()
    {
        $chans = $this->config['chans'] ?? [];
        foreach ($chans as $chan) {
            if (in_array(ChanInterface::class, class_implements($chan['class'], true))) {
                $this->addChan($chan['class'], new $chan['class']($chan['size']));
            } else {
                throw new \RuntimeException('chan 配置错误, class请继承' . ChanInterface::class . '接口');
            }
        }
    }

    private function initComponent()
    {
        $this->attachProcess();
        $this->attachChannel();
        $this->attachTables();
    }

    private function initEventListener()
    {
        $this->handler->on(self::REQUEST, self::onRequest());
        $this->handler->on(self::START, $this->onStart());
        $this->handler->on(self::WORKER_START, $this->onWorkStart());

        $listeners = $this->config['event_listeners'];
        foreach ($listeners as $listenerName) {
            if (in_array(ServerEventListener::class, class_parents($listenerName, true))) {
                /**
                 * @var $listener ServerEventListener 服务事件监听对象
                 */
                $listener = new $listenerName();
                $listener->setServer($this->handler);
                if (!in_array($listener->getEventName(), [Server::REQUEST, Server::START, Server::WORKER_START])) {
                    $listener->handle();
                }
            }
        }
    }

    // @TODO 修改为配置文件
    private function setTrustedProxiesAndHosts()
    {
        if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false) {
            SymfonyRequest::setTrustedProxies(explode(',', $trustedProxies), SymfonyRequest::HEADER_X_FORWARDED_ALL ^ SymfonyRequest::HEADER_X_FORWARDED_HOST);
        }
        if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? $_ENV['TRUSTED_HOSTS'] ?? false) {
            SymfonyRequest::setTrustedHosts([$trustedHosts]);
        }
    }

    private function warpToSymfonyRequest(Request $request)
    {
        $parameters = array_merge($request->post ?? [], $request->get ?? []);
        return SymfonyRequest::create(
            $request->server['request_uri'], $request->server['request_method'],
            $parameters, $request->cookie ?? [],
            $request->files ?? [],
            array_change_key_case($request->server, CASE_UPPER),
            $request->rawcontent()
        );
    }

    /**
     * @param Kernel $kernel
     * @param SymfonyRequest $symfonyRequest
     * @param Response $response
     *
     * @throws \Exception
     */
    private function finishResponse(Kernel $kernel, SymfonyRequest $symfonyRequest, Response $response)
    {
        // 解析为响应
        $symfonyResponse = $kernel->handle($symfonyRequest);
        // 发送响应状态码
        $response->status($symfonyResponse->getStatusCode());
        // 获取所有的响应头, 并且发送
        $headers = $symfonyResponse->headers->allPreserveCase();
        array_walk($headers, function ($items, $key) use ($response) {
            foreach ($items as $item) {
                $response->header($key, $item, false);
            }
        });
        // 将响应内容写入响应对象
        $response->end($symfonyResponse->getContent());
        // 关闭请求与响应的生命周期
        $kernel->terminate($symfonyRequest, $symfonyResponse);
        // 将 session写到存储缓存内
        $symfonyRequest->getSession()->save();
    }

    // 将request 附着到kernel上,在控制器内使用, 现在不知道怎么使用优雅的方式解决
    private function requestBindToKernel(SymfonyRequest $request, Kernel $kernel)
    {
        $kernel->request = $request;
    }

    // 检查session
    // 如果不存在session_name的 cookie 则设置
    private function checkSessionExists(Request $request, Response $response)
    {
        if (!($request->cookie[session_name()] ?? '')) {
            $request->cookie[session_name()] = session_create_id('sess-');
            $response->cookie(session_name(), $request->cookie[session_name()]);
        }
    }

    private function loadEnv()
    {
        if (!getenv('APP_ENV')) {
            (new Dotenv())->load(__DIR__ . '/../.env');
        }
    }

    private function ensureDebug()
    {
        if ((bool) getenv('APP_DEBUG')) {
            umask(0000);
            Debug::enable();
        }
    }

    private function isLinux()
    {
        return PHP_OS == "Linux";
    }

    private function initKernel($env, $debug)
    {
        $this->kernel = new Kernel($env, (bool)$debug);
    }


    public function onStart()
    {
        return function () {
            $host = $this->config['http_host'];
            $port = $this->config['http_port'];
            $host = 'http://' . $host . ((80 !== $port) ? ':' . $port : '');
            $this->io->writeln('<info>[OK]</info> Swoole-' . SWOOLE_VERSION . ' started, listening on <info>' . $host . '/</info>');
        };
    }

    public function onWorkStart()
    {
        return function ($serv, $worker_id) {
            if ($worker_id === 0) {
                require dirname(__DIR__) . '/../../../config/bootstrap.php';
                $this->loadEnv();
                $this->ensureDebug();
                $this->setTrustedProxiesAndHosts();
                $this->initKernel(getenv('APP_ENV') ?: 'dev', (bool)getenv('APP_DEBUG'));
            }
            if ($this->isLinux()) {
                $processFlags = $worker_id >= $serv->setting['worker_num'] ? 'task' : 'event';
                swoole_set_process_name(sprintf('symfony %s worker', $processFlags));
            }
        };
    }

    public function onRequest()
    {
        return function (Request $request, Response $response) {
            $k = clone $this->kernel;
            $this->checkSessionExists($request, $response);
            $symfonyRequest = $this->warpToSymfonyRequest($request);
            $this->requestBindToKernel($symfonyRequest, $k);
            try {
                $this->finishResponse($k, $symfonyRequest, $response);
            } catch (\Throwable $exception) {
                /**
                 * @var $twig Environment
                 */
                $twig = $this->container->get('twig');
                // convert to ExceptionController
                $controller = new ExceptionController($twig, (bool) getenv('APP_DEBUG'));
                // convert to Exception
                $exception = FlattenException::create($exception);

                // render exception info
                $response->end($controller->showAction($symfonyRequest, $exception)->getContent());
            }
        };
    }

    public function run()
    {
        $this->initComponent();

        $this->initEventListener();

        $this->handler->start();
    }

    // mixin 混合方法
    public function __call($name, $arguments)
    {
        $this->handler->{$name}(...$arguments);
    }
}
