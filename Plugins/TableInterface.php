<?php

namespace App\Bundles\SwooleBundle\Plugins;

interface TableInterface
{
    /**
     * @return \swoole_table
     */
    public function get(): \swoole_table;
}
