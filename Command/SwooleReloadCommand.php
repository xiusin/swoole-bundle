<?php

namespace xiusin\SwooleBundle\Command;

use xiusin\SwooleBundle\DependencyInjection\WebServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SwooleReloadCommand extends Command
{
    protected static $defaultName = 'swoole:reload';

    protected $container;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure()
    {
        $this->setDescription('reload a running swoole server.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $server = WebServer::getInstance();
        $server->setContainer($this->container);
        $server->reload(new SymfonyStyle($input, $output));
    }
}
