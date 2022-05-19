<?php

namespace xiusin\SwooleBundle\Plugins;

use Swoole\Process;
use swoole_http_server;
use swoole_websocket_server;

interface ProcessInterface
{
    /**
     * @param $process Process
     * @param $server swoole_http_server | swoole_websocket_server
     *
     * @return mixed
     */
    public function handle(Process $process, $server);
}
