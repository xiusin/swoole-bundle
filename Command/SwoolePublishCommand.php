<?php

namespace xiusin\SwooleBundle\Command;

use Swoole\Http\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use xiusin\SwooleBundle\SwooleBundle;

class SwoolePublishCommand extends Command
{
    protected static $defaultName = 'swoole:publish';

    protected $container;

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
        $this->setDescription('publish `swoole.yaml` to config/packages');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $configDir = $this->container->getParameter("kernel.project_dir") . '/config';

        $bundleConfig = realpath(__DIR__ . '/../Resources/config/swoole.yaml');
        $yamlFile = $configDir . '/packages/swoole.yaml';

        if (!file_exists($yamlFile)) {
            if (false !== file_put_contents($yamlFile, file_get_contents($bundleConfig))) {
                $io->success($yamlFile . ' has created');
            } else {
                $io->error($yamlFile . ' create failed');
            }
        }

        $bundles = sprintf("%s/bundles.php", $configDir);
        $arr = require_once $bundles;

        if (!isset($arr[SwooleBundle::class])) {
            $content = str_replace('];', "\txiusin\SwooleBundle\SwooleBundle::class => ['all' => true]," . PHP_EOL . "];", file_get_contents($bundles));
            file_put_contents($bundles, $content);
        }
        $io->success('publish config file successfully');
        return Command::SUCCESS;
    }

}
