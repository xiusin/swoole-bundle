<?php

namespace xiusin\SwooleBundle\Command;

use xiusin\SwooleBundle\DependencyInjection\WebServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SwooleRestartCommand extends Command
{
    protected static $defaultName = 'swoole:restart';

    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    /**
     * @var \Swoole\Http\Server
     */
    protected function configure()
    {
        $this->setDescription('restart a swoole server');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $server = WebServer::getInstance();
        $server->setContainer($this->container);
        if ($server->stop($io)) {
            $server->start($io, true);
        }
    }
}
