<?php

namespace xiusin\SwooleBundle\Plugins;

interface TableInterface
{
    /**
     * @return \swoole_table
     */
    public function get(): \swoole_table;
}
