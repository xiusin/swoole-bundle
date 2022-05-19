<?php

namespace xiusin\SwooleBundle\DependencyInjection;

use App\Kernel;
use Swoole\Http\Request;
use Swoole\Http\Response;
use swoole_http_server;
use swoole_websocket_server;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use xiusin\SwooleBundle\KernelPool;
use xiusin\SwooleBundle\Plugins\ChanInterface;
use xiusin\SwooleBundle\Plugins\ProcessInterface;
use xiusin\SwooleBundle\Plugins\AbstractServerEventListener;
use xiusin\SwooleBundle\Plugins\TableInterface;

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

    /**
     * 通道数组
     *
     * @var array
     */
    private array $chans = [];

    /**
     * 表数组
     * @var array
     */
    private array $tables = [];

    /**
     * 配置
     * @var array
     */
    private $config = [];

    /**
     * swoole 服务配置
     *
     * @var mixed
     */
    private $serverConfig = [];

    private SymfonyStyle $io;

    private ContainerInterface $container;

    /**
     * 内核池对象
     *
     * @var KernelPool
     */
    private KernelPool $kernelPool;

    /* @var swoole_http_server | swoole_websocket_server */
    private $handler;

    public $pidFile = '';

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
        $handlerClass = $this->config['server'];
        $this->handler = new $handlerClass($this->config['http_host'], $this->config['http_port'], SWOOLE_PROCESS);

        $this->serverConfig = $this->config['config'];
        $this->pidFile = $this->serverConfig['pid_file'] ?? '';
        $this->handler->set($this->getSetting());
    }

    public function getHandler()
    {
        return $this->handler;
    }

    private function getSetting(): array
    {
        return [
            'worker_num' => $this->serverConfig['worker_num'],
            'reactor_num' => $this->serverConfig['reactor_num'],
            'upload_tmp_dir' => sys_get_temp_dir(),
            'enable_static_handler' => $this->serverConfig['enable_static_handler'],
            'document_root' => $this->serverConfig['document_root'],
            'http_compression' => $this->serverConfig['http_compression'],
            'log_level' => $this->serverConfig['log_level'],
            'log_file' => $this->serverConfig['log_file'],
            'daemonize' => $this->serverConfig['daemonize'] ? 1 : 0,
            'pid_file' => $this->pidFile,
        ];
    }

    public function addChan(string $name, ChanInterface $chan)
    {
        $this->chans[$name] = $chan;
    }

    public function getChan($name)
    {
        return $this->chans[$name];
    }

    public function addTable(string $name, TableInterface $table)
    {
        $this->tables[$name] = $table;
    }

    public function getTable($name)
    {
        return $this->tables[$name];
    }

    private function attachProcess()
    {
        $processes = $this->config['processes'];
        foreach ($processes as $processName) {
            if (in_array(ProcessInterface::class, class_implements($processName, true))) {
                $this->handler->addProcess(new \swoole_process(function ($process) use (&$processName) {

                    /* @var $processHandler ProcessInterface */
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
        $this->handler->on(self::START, $this->onStart());
        $this->handler->on(self::REQUEST, self::onRequest());
        $this->handler->on(self::WORKER_START, $this->onWorkStart());
        // use event_listeners register ws hander
        $listeners = $this->config['event_listeners'];
        foreach ($listeners as $listenerName) {
            if (in_array(AbstractServerEventListener::class, class_parents($listenerName, true))) {
                /**
                 * @var $listener AbstractServerEventListener
                 */
                $listener = new $listenerName();
                $listener->setServer($this->handler);
                if (!in_array($listener->getEventName(), [Server::REQUEST, Server::START, Server::WORKER_START])) {
                    $listener->handle();
                }
            }
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
    }

    // 将request 附着到kernel上,在控制器内使用, 现在不知道怎么使用优雅的方式解决
    private function requestBindToKernel(SymfonyRequest $request, Kernel $kernel)
    {
        $kernel->request = $request;
    }

    // 检查session
    // todo 依赖session功能开启
    // 如果不存在session_name的 cookie 则设置
    private function checkSessionExists(Request $request, Response $response)
    {
        if (!($request->cookie[session_name()] ?? '')) {
            $request->cookie[session_name()] = strtoupper(session_create_id('sess-'));
            $response->cookie(session_name(), $request->cookie[session_name()]);
        }
    }

    private function isLinux()
    {
        return PHP_OS == "Linux";
    }

    public function onStart()
    {
        return function () {
            $host = 'http://' . $this->getHttpHost();
            $this->io->success('Swoole-' . SWOOLE_VERSION . ' started, listening on ' . $host . '/');
        };
    }

    public function onWorkStart()
    {
        return function ($serv, $worker_id) {
            if ($this->isLinux()) {
                $processFlags = $worker_id >= $serv->setting['worker_num'] ? 'task' : 'event';
                swoole_set_process_name(sprintf('swoole-bundle %s worker', $processFlags));
            }
        };
    }

    public function onRequest()
    {
        $this->kernelPool = new KernelPool(10, true);
        return function (Request $request, Response $response) {
            try {
                $request->server['http_host'] = $this->getHttpHost();
                $k = $this->kernelPool->get();
                $symfonyRequest = $this->warpToSymfonyRequest($request);
                $this->requestBindToKernel($symfonyRequest, $k);
                $this->finishResponse($k, $symfonyRequest, $response);
            } finally {
                if (isset($k) && $k) $this->kernelPool->put($k);
            }
        };
    }

    private function getHttpHost()
    {
        $host = $this->config['http_host'];
        $port = $this->config['http_port'];
        return $host . ((80 !== $port) ? ':' . $port : '');
    }

    public function run()
    {
        $this->initComponent();
        $this->initEventListener();
        $this->handler->start();
    }

    // mixin
    public function __call($name, $arguments)
    {
        $this->handler->{$name}(...$arguments);
    }
}
