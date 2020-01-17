<?php


namespace xiusin\SwooleBundle;


use App\Kernel;

class KernelPool
{
    private $pools = [];
    private $locker;

    private $size;
    private $env;
    private $debug;

    /**
     * KernelPool constructor.
     * @param $env
     * @param $debug
     * @param $size max object size
     */
    public function __construct($env, $debug, $size, $allinit)
    {
        $this->locker = new \swoole_lock(SWOOLE_MUTEX);
        $this->env = $env;
        $this->debug = $debug;
        $this->size = $size;
        $allinit && $this->init();
    }

    private function init(){
        for ($i = 0 ; $i < $this->size; $i++) {
            $kernel = $this->get();
            $this->put($kernel);
        }
    }

    public function get(): Kernel
    {
        $kernel = null;
        try {
            $this->locker->lock();
            if (count($this->pools)) {
                $kernel = array_pop($this->pools);
            } else {
                $kernel = new Kernel($this->env, $this->debug);
            }
            return $kernel;
        } catch (\Throwable $exception) {
            throw $exception;
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
        } catch (\Throwable $exception) {
            throw  $exception;
        } finally {
            $this->locker->unlock();
        }
    }
}