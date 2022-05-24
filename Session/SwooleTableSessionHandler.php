<?php

namespace xiusin\SwooleBundle\Session;

use Swoole\Table;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;

class SwooleTableSessionHandler extends AbstractSessionHandler
{
    protected Table $table;

    /**
     * 存活时间
     * @var int
     */
    private int $maxlifetime;

    /**
     * sess 前缀
     * @var string|null
     */
    private string $prefix;

    /**
     *
     *     swoole.session.handler:
     *      class: App\Bundles\SwooleBundle\Plugins\Session\SwooleTableSessionHandler
     *      arguments:
     *      - 'sess_'
     *      - 1200
     *      - 30
     * @param null $prefix
     * @param int $maxlifetime
     * @param int $gctime
     */
    public function __construct($prefix = null, int $maxlifetime = 0, int $gctime = 3)
    {
        $this->maxlifetime = $maxlifetime;
        $this->prefix = $prefix;

        $this->table = new Table(1024, 0.2);
        $this->table->column('data', Table::TYPE_STRING, 1024);
        $this->table->column('expires_at', Table::TYPE_INT, 11);
        $this->table->create();


        // 启动Gc协程
        go(function () use ($gctime) {
            swoole_timer_tick($gctime * 1000, function () {
                foreach ($this->table as $row) {
                    if ($row['expires_at'] < time()) {
                        $this->table->del($row['id']);
                    }
                }
            });
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

    /**
     * 更新时间戳
     * @param string $session_id
     * @param string $session_data
     * @return bool|void
     */
    public function updateTimestamp($session_id, $session_data)
    {
        return $this->doWrite($session_id, $session_data);
    }
}
