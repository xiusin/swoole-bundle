<?php

namespace xiusin\SwooleBundle\Command;

use Swoole\Http\Server;
use Swoole\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SwooleDevCommand extends Command
{
    protected static $defaultName = 'swoole:dev';

    protected ContainerInterface $container;

    protected string $sh = <<<SH
#!/usr/bin/env bash
WORK_DIR="src/"
php bin/console swoole:start -d -v
LOCKING=0
fswatch -e ".*" -i "\\.php$" \${WORK_DIR} | while read file
do
    if [ \${LOCKING} -eq 1 ] ;then
        echo "Reloading, skipped."
        continue
    fi
    echo "File \${file} has been modified."
    LOCKING=1
    php bin/console swoole:reload -v
    LOCKING=0
done
exit 0
SH;

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
        $this->setDescription('start a dev swoole server. ');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->start(new SymfonyStyle($input, $output));
        return Command::SUCCESS;
    }

    /**
     * @param SymfonyStyle $io
     */
    protected function start(SymfonyStyle $io)
    {
        exec('which bash', $out, $ret);
        if ($ret) {
            $io->error('can\'t find the command `bash`');
        } else {
            //发布文件
            $projectDir = $this->container->getParameter('kernel.project_dir');
            $shFile = $projectDir . '/bin/fswatch.sh';
            if (!file_exists($shFile) && !file_put_contents($shFile, $this->sh)) {
                $io->error(sprintf('create the [%s] failed', $shFile));
                return;
            }

            exec('ps aux | grep \'swoole\' | grep -v grep | grep -v \'swoole:dev\' | awk \'{print "kill -9 " $2}\' | bash 2>&1 >/dev/null');

            $process = new Process(function (Process $worker) use ($out, $shFile) {
                $worker->exec($out[0], [$shFile]);
            }, false, 0);
            $process->start();
            Process::wait(true);
        }
    }
}
