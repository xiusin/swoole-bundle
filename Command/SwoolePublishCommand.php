<?php

namespace xiusin\SwooleBundle\Command;

use Swoole\Process;
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
     * @var \Swoole\Http\Server
     */
    protected function configure()
    {
        $this->setDescription('publish `swoole.yaml` to config/packages');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $io = new SymfonyStyle($input, $output);
        $configDir = $this->container->getParameter("kernel.project_dir") . '/config';
        $bundleConfig = realpath(__DIR__ . '/../Resources/config/swoole.yaml');
        $swooleYamlFile = $configDir . '/packages/swoole.yaml';
        if (!file_exists($swooleYamlFile)) {
            if (false !== file_put_contents($swooleYamlFile, file_get_contents($bundleConfig))) {
                $io->success($swooleYamlFile . ' has created');
            } else {
                $io->error($swooleYamlFile . ' create failed');
            }
        }
//        $bundlePhpFile = $configDir . '/bundles.php';
//        $arr = include $bundlePhpFile;
//
//        if (!isset($arr[SwooleBundle::class])) {
//            $content = file_get_contents($bundlePhpFile);
//            $content = str_replace('];', "xiusin\SwooleBundle\SwooleBundle::class => ['all' => true]," . PHP_EOL . "];");
//            file_put_contents($bundlePhpFile, $content);
//        }
        $io->success('publish config file successfully');
        return 0;
    }

}
