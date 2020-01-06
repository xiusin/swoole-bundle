<?php

namespace xiusin\SwooleBundle\Plugins;

interface ProcessInterface
{
    /**
     * @param $process \swoole_process
     * @param $server \swoole_http_server | \swoole_websocket_server
     *
     * @return mixed
     */
    public function handle($process, $server);
}
