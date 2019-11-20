<?php

namespace App\Bundles\SwooleBundle\Plugins;

interface ChanInterface
{
    public function __construct(int $size);

    public function get(): \swoole_channel;
}
