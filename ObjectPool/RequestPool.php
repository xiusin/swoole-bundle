<?php

namespace xiusin\SwooleBundle\ObjectPool;

use Symfony\Component\HttpFoundation\Request;

class RequestPool extends PoolBase
{
    public function new()
    {
        return new Request(...func_get_args());
    }
}