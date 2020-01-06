<?php

namespace xiusin\SwooleBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SwooleFixerCommand extends Command
{
    protected static $defaultName = 'swoole:fixer';

    protected $container;

    protected $config;

    /**
     * @var \Swoole\Http\Server
     */
    protected function configure()
    {
        $this->setDescription('format code style.@see: https://cs.symfony.com/');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        exec('which php-cs-fixer', $cmdOut, $ret);
        if ($ret) {
            $output->writeln('<error>error: cannot find the command `php-cs-fixer`, please install it</error>');
        } else {
            $command = [
                $cmdOut[0],
                'fix',
                getcwd().'/src/',
                '--allow-risky=yes',
                '--rules=@Symfony,-@PSR1,-blank_line_before_statement,strict_comparison',
                '>/dev/null 2>&1',
            ];
            exec(implode(' ', $command), $cmdOut, $ret);
            if ($ret) {
                $output->writeln('<error>error: '.implode(PHP_EOL, $cmdOut).'</error>');
            } else {
                $output->writeln('<info>exec success!</info>');
            }
        }
    }
}
