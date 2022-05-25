<?php

namespace xiusin\SwooleBundle;

use xiusin\SwooleBundle\DependencyInjection\WebServer;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SwooleBundle extends Bundle
{
    /**
     * @var string 注册到容器的名称
     */
    private string $swooleName = 'app.swoole';

    public function boot()
    {
        if ($this->swooleHasStarted()) {
            $this->registerServices();
        }
    }

    private function swooleHasStarted(): bool
    {
        return WebServer::getInstance()->started();
    }

    private function registerServices()
    {
        $this->container->set($this->swooleName, WebServer::getInstance()->httpServer());
    }
}
