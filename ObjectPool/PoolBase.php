<?php

namespace xiusin\SwooleBundle\ObjectPool;

use App\Kernel;
use Swoole\Lock;
use Throwable;

abstract class PoolBase
{
    private \SplQueue $queue;

    /**
     * 互斥锁
     * @var Lock
     */
    private Lock $locker;

    /**
     * 最大池容量
     * @var int
     */
    private int $size;

    /**
     * @param int $size max object size
     * @throws Throwable
     */
    public function __construct(int $size, $init = true)
    {
        $this->queue = new \SplQueue();

        $this->locker = new Lock(SWOOLE_MUTEX);
        $this->size = $size;

        $init && $this->init();
    }

    /**
     * @throws Throwable
     */
    private function init()
    {
        for ($i = 0; $i < $this->size; $i++) {
            $object = $this->new();
            $this->put($object);
        }
    }

    public function get()
    {
        $this->locker->lock();
        try {
            if ($this->queue->count()) {
                $object = $this->queue->pop();
            } else {
                $object = $this->new();
            }
            return $object;
        } finally {
            $this->locker->unlock();
        }
    }

    public function put($object)
    {
        if ($object) {
            try {
                $this->locker->lock();
                if ($this->queue->count() < $this->size) {
                    $this->queue->push($object);
                } else {
                    unset($object);
                }
            } finally {
                $this->locker->unlock();
            }
        }
    }

    abstract public function new();
}