<?php

namespace xiusin\SwooleBundle\Session;

use Swoole\Coroutine;
use Swoole\Table;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;

class SwooleTableSessionHandler extends AbstractSessionHandler
{
    protected Table $table;

    private $maxlifetime;
    private $prefix;

    public function __construct($prefix = null)
    {
        $this->maxlifetime = ini_get('session.gc_maxlifetime');
        $this->prefix = $prefix;

        $this->table = new Table(1024, 0.2);
        $this->table->column('data', Table::TYPE_STRING, 1024);
        $this->table->column('expires_at', Table::TYPE_INT, 11);
        $this->table->create();


        // 启动Gc协程
        go(function () {
            while (true) {
                foreach ($this->table as $row) {
                    if ($row['expires_at'] < time()) {
                        $this->table->del($row['id']);
                    }
                }
                sleep(3);
            }
        });



    }

    protected function doRead($sessionId)
    {
        $record = $this->table->get($this->getKeyWithPrefix($sessionId));
        if ($record['expires_at'] >= time() - $this->maxlifetime) {
            return $record['data'];
        }
        return '';
    }

    protected function doWrite($sessionId, $data)
    {
        $this->table->set($this->getKeyWithPrefix($sessionId), [
            'data' => $data,
            'expires_at' => time() + $this->maxlifetime,
        ]);
    }

    protected function doDestroy($sessionId)
    {
        $this->table->del($this->getKeyWithPrefix($sessionId));
    }

    public function close()
    {
        return true;
    }

    protected function getKeyWithPrefix($sessionId): string
    {
        return $this->prefix . $sessionId;
    }

    public function gc($maxlifetime)
    {
        return true;
    }

    public function updateTimestamp($session_id, $session_data)
    {
        return $this->doWrite($session_id, $session_data);
    }
}
