<?php

namespace xiusin\SwooleBundle\Plugins;

use swoole_http_server;
use swoole_websocket_server;

abstract class AbstractServerEventListener
{
    private string $eventName = ''; //从server常量中直接设置如 : Server::onWorkStart
    /**
     * @var $server swoole_http_server | swoole_websocket_server
     */
    private $server;

    /**
     * @param $server swoole_http_server | swoole_websocket_server
     */
    public function setServer($server)
    {
        $this->server = $server;
    }

    public function server()
    {
        return $this->server;
    }

    /**
     * @return string
     */
    public function getEventName(): string
    {
        return $this->eventName;
    }

    abstract public function handle();

    /**
     * @return string
     */
    abstract public function eventName(): string;
}
