<?php

namespace xiusin\SwooleBundle;

use xiusin\SwooleBundle\DependencyInjection\WebServer;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SwooleBundle extends Bundle
{
    private $swooleName = 'app.swoole';
    public function boot()
    {
        if ($this->swooleHasStarted()) {
            $this->registerServices();
        }
    }

    private function swooleHasStarted()
    {
        $server = WebServer::getInstance();
        if ($server) {
            return $server->started();
        } else {
            return false;
        }
    }

    private function registerServices()
    {
        $this->container->set($this->swooleName, WebServer::getInstance()->httpServer());
        $req = $this->container->get('kernel')->request;
        $this->container->get('session')->setId($req->cookies->get(session_name()));
    }
}
