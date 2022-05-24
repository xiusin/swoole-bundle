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

        $this->setSessionId();
    }

    private function setSessionId()
    {
        if ($this->container->has('session')) {
            $req = $this->container->get('kernel')->request;
            $this->container->get('session')->setId($req->cookies->get(session_name()));
        }
    }
}
