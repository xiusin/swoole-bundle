<?php

namespace xiusin\SwooleBundle\ObjectPool;

use App\Kernel;

class KernelPool extends PoolBase
{
   public function new() {
       return new Kernel($_ENV['APP_ENV'], $_ENV['APP_DEBUG']);
   }
}