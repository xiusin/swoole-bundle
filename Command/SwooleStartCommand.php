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

    protected $config;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure()
    {
        $this->setDescription('start a swoole server.');
        $this->setDefinition([
            new InputOption('daemonize', 'd', InputOption::VALUE_OPTIONAL, 'daemonize the server', 0),
        ]);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $daemonize = $input->getOption('daemonize');
        $daemonize = !is_int($daemonize) || intval($daemonize) ? true : false;
        $server = WebServer::getInstance();
        $server->setContainer($this->container);
        $io = new SymfonyStyle($input, $output);
        $this->info($io, $output);
        $server->start($io, $daemonize);
    }

    // 打印组件信息
    protected function info(SymfonyStyle $io, OutputInterface $output)
    {
        $io->writeln('');
        $io->writeln('');
        $table = new Table($output);
        $table->setHeaders(['组件', '版本'])->setHeaderTitle('use swoole http server speed your symfony project')->setRows([
            ['组件' => 'PHP', '版本' => phpversion()],
            ['组件' => 'Swoole', '版本' => \swoole_version()],
            ['组件' => $this->getApplication()->getName(), '版本' => $this->getApplication()->getVersion()],
        ])->setColumnWidth(0, 20)->setColumnWidth(1, 50)->render();
        $io->writeln('');
    }
}
