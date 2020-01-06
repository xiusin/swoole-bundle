<?php

namespace xiusin\SwooleBundle\Plugins;

interface ChanInterface
{
    public function __construct(int $size);

    public function get(): \swoole_channel;
}
