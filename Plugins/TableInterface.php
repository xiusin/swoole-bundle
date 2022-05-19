<?php

namespace xiusin\SwooleBundle\Plugins;

use Swoole\Table;

interface TableInterface
{
    public function get(): Table;
}
