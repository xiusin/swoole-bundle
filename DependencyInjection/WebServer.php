<?php

namespace xiusin\SwooleBundle\DependencyInjection;

use Closure;
use Exception;
use Swoole\Process;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class WebServer
{
    /**
     * @var Server
     */
    protected Server $server;

    /**
     * @var WebServer
     */
    private static $instance;

    private bool $started = false;

    protected ContainerInterface $container;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function getInstance(): WebServer
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function started(): bool
    {
        return $this->started;
    }

    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function httpServer(): Server
    {
        return $this->server;
    }

    public function getPidFile()
    {
        $config = $this->container->getParameter('swoole.config');
        return $config['config']['pid_file'];
    }

    public function start(SymfonyStyle $io, Closure $callback, $daemonize = false)
    {
        $this->server = new Server($this->container, $io, $daemonize);
        $this->started = true;
        $callback();
        $this->server->run();
    }

    public function stop(SymfonyStyle $io): bool
    {
        if (!file_exists($this->getPidFile())) {
            $io->warning('no swoole service is running');
            return false;
        } else {
            $masterPid = intval(file_get_contents($this->getPidFile()));
            Process::kill($masterPid, 15);
            usleep(1000);
            $timeout = 60;
            $startTime = time();
            while (true) {
                if (Process::kill($masterPid, 0)) {
                    if (time() - $startTime >= $timeout) {
                        $io->error('error, stop the server is failed');
                        return false;
                    }
                    sleep(1000);
                    continue;
                }
                @unlink($this->getPidFile());
                $io->success('the server killed success');
                return true;
            }
        }
    }

    public function reload(SymfonyStyle $io)
    {
        if (!file_exists($this->getPidFile())) {
            $io->warning('no server is running');
        } else {
            $masterPid = intval(file_get_contents($this->getPidFile()));
            if (!Process::kill($masterPid, 0)) {
                $io->error("PID[$masterPid] does not exist, or permission denied.");
            } else {
                try {
                    if (Process::kill($masterPid, SIGUSR1)) {
                        $io->writeln(sprintf('<info>[SUSS]</info> reload server successfully, PID[%d]. ', $masterPid));
                    } else {
                        $io->writeln(sprintf('<info>[ERRO]</info> reload server failed,PID [%d]', $masterPid));
                    }
                } catch (Exception $exception) {
                    $io->error($exception->getMessage());
                }
            }
        }
    }
}
