<?php

namespace xiusin\SwooleBundle\Command;

use Swoole\Http\Server;
use xiusin\SwooleBundle\DependencyInjection\WebServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SwooleStopCommand extends Command
{
    protected static $defaultName = 'swoole:stop';

    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    /**
     * @var Server
     */
    protected function configure()
    {
        $this->setDescription('stop a running swoole server');
        $this->addOption('all', '', InputOption::VALUE_OPTIONAL, 'close all "swoole:start" process', false);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('all')) {
            exec('ps aux | grep "swoole:start" | grep -v "grep" | awk \'{print "kill -9 " $2}\' | bash');
        } else {
            $io = new SymfonyStyle($input, $output);
            $server = WebServer::getInstance();
            $server->setContainer($this->container);
            $server->stop($io);
        }

        return Command::SUCCESS;
    }
}
