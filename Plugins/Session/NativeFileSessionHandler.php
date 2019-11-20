<?php

namespace App\Bundles\SwooleBundle\Plugins\Session;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;

class NativeFileSessionHandler extends AbstractSessionHandler
{
    private $sessSavePath = '';
    private $maxlifetime;
    private $prefix;

    public function __construct(string $savePath = null, $prefix = null)
    {
        if (null === $savePath) {
            $this->sessSavePath = ini_get('session.save_path');
        } else {
            $this->sessSavePath = rtrim($savePath, DIRECTORY_SEPARATOR);
        }
        $this->maxlifetime = ini_get('session.gc_maxlifetime');
        $this->prefix = $prefix ?: 'sf_s';
        $baseDir = $savePath;
        if ($count = substr_count($savePath, ';')) {
            if ($count > 2) {
                throw new \InvalidArgumentException(sprintf('Invalid argument $savePath \'%s\'', $savePath));
            }
            $baseDir = ltrim(strrchr($savePath, ';'), ';');
        }

        if ($baseDir && !is_dir($baseDir) && !@mkdir($baseDir, 0777, true) && !is_dir($baseDir)) {
            throw new \RuntimeException(sprintf('Session Storage was not able to create directory "%s"', $baseDir));
        }
    }

    protected function doRead($sessionId)
    {
        $path = $this->sessSavePath.'/'.$sessionId;
        if (file_exists($path)) {
            if (filemtime($path) >= time() - $this->maxlifetime) {
                return file_get_contents($path);
            }
        }
        return '';
    }

    protected function doWrite($sessionId, $data)
    {
        if (!is_dir($this->sessSavePath) && !mkdir($this->sessSavePath, 0755) && !is_dir($this->sessSavePath)) {
            return false;
        }
        $path = $this->sessSavePath.'/'.$sessionId;
        return file_put_contents($path, $data);
    }

    protected function doDestroy($sessionId)
    {
        if (!is_dir($this->sessSavePath) || !file_exists($this->sessSavePath.'/'.$sessionId)) {
            return true;
        }
        $path = $this->sessSavePath.'/'.$sessionId;
        try {
            return unlink($path);
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function close()
    {
        return true;
    }

    public function gc($maxlifetime)
    {
        return true;
    }

    public function updateTimestamp($session_id, $session_data)
    {
        return $this->doWrite($this->prefix.$session_id, $session_data);
    }
}
