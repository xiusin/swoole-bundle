<?php

namespace xiusin\SwooleBundle\Plugins;


use Swoole\Coroutine\Channel;

interface ChanInterface
{
    public function __construct(int $size);

    public function get(): Channel;
}
