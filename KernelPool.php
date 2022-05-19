<?php

namespace xiusin\SwooleBundle;

use App\Kernel;
use Throwable;

class KernelPool
{
    private $pools = [];
    private $locker;

    private $size;

    /**
     * KernelPool Kernel池
     * @param int $size max object size
     * @throws Throwable
     */
    public function __construct(int $size, $init = true)
    {
        $this->locker = new \swoole_lock(SWOOLE_MUTEX);
        $this->size = $size;

        if ($init) {
            $this->init();
        }
    }

    /**
     * @throws Throwable
     */
    private function init()
    {
        for ($i = 0; $i < $this->size; $i++) {
            $kernel = $this->get();
            $this->put($kernel);
        }
    }

    public function get(): Kernel
    {
        try {
            $this->locker->lock();
            if (count($this->pools)) {
                $kernel = array_pop($this->pools);
            } else {
                $kernel = new Kernel($_ENV['APP_ENV'], $_ENV['APP_DEBUG']);
            }
            return $kernel;
        } finally {
            $this->locker->unlock();
        }
    }

    public function put(Kernel $kernel)
    {
        try {
            $this->locker->lock();
            if (count($this->pools) < $this->size) {
                $this->pools[] = $kernel;
            } else {
                unset($kernel);
            }
        } finally {
            $this->locker->unlock();
        }
    }
}