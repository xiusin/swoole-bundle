<?php

namespace xiusin\SwooleBundle\DependencyInjection;

use Swoole\Process;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class WebServer
{
    protected $server;

    private static $instance;

    private $started;

    protected $container;

    private $pidFile = 'swoole.server.pid';

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function started()
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
        return getcwd() . '/' . $this->pidFile;
    }

    public function start(SymfonyStyle $io, $daemonize = false)
    {
        $this->server = new Server($this->container, $io, $daemonize);
        $this->started = true;
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
                    // ensure kill timeout
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
            // check process exists
            if (! Process::kill($masterPid, 0)) {
                $io->error("PID[{$masterPid}] does not exist, or permission denied.");
            } else {
                try {
                    // restart process
                    if ($res = Process::kill($masterPid, SIGUSR1)) {
                        $io->success(sprintf('Reload server successfully, PID[%d]. ', $masterPid));
                    } else {
                        $io->error(sprintf('Reload server failed,PID [%d]', $masterPid));
                    }
                } catch (\Exception $exception) {
                    $io->error($exception->getMessage());
                }
            }
        }
    }
}
