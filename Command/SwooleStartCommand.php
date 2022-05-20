<?php

namespace xiusin\SwooleBundle\Command;

use xiusin\SwooleBundle\DependencyInjection\WebServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SwooleStartCommand extends Command
{
    protected static $defaultName = 'swoole:start';

    protected $container;

    /* @var WebServer */
    private $server = null;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure()
    {
        $this->setDescription('start a swoole http server.');
        $this->setDefinition([
            new InputOption('daemonize', 'd', InputOption::VALUE_OPTIONAL, 'daemonize the server', 0),
        ]);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $daemonize = $input->getOption('daemonize');
        $daemonize = !is_int($daemonize) || intval($daemonize) ? true : false;
        $server = WebServer::getInstance();
        $this->server = $server;
        $server->setContainer($this->container);
        $io = new SymfonyStyle($input, $output);
        $server->start($io, function () use ($io, $output) {
            $this->info($io, $output);
        }, $daemonize);
        return Command::SUCCESS;
    }

    // 打印组件信息
    protected function info(SymfonyStyle $io, OutputInterface $output)
    {
        $config = $this->container->getParameter("swoole.config");
        $io->writeln('');
        $table = new Table($output);

        $table
            ->setHeaderTitle('SPEED YOUR SYMFONY PROJECT')
            ->setRows([
                ['Configuration' => 'Info', 'Values' => "PHP:" . phpversion() . "   Symfony:" . $this->getApplication()->getVersion() . "   Swoole:" . \swoole_version()],
                ['Configuration' => 'Env', 'Values' => "APP_ENV=" . $_SERVER['APP_ENV'] . '     APP_DEBUG='.($_SERVER['APP_DEBUG'] ? 'true' : 'false')],
                ['Configuration' => str_repeat('-', 20), 'Values' => str_repeat('-', 50)],
                ['Configuration' => 'server', 'Values' => $config['server']],
                ['Configuration' => 'running_mode', 'Values' => 'Process'],
                ['Configuration' => 'worker_num', 'Values' => $config['config']['worker_num']],
                ['Configuration' => 'reactor_num', 'Values' => $config['config']['reactor_num']],
                ['Configuration' => 'memory_limit', 'Values' => ini_get('memory_limit')],
                ['Configuration' => 'document_root', 'Values' => $config['config']['document_root']],
            ])
            ->setColumnWidth(0, 20)
            ->setColumnWidth(1, 40)
            ->render();
        $io->writeln('');
    }
}
