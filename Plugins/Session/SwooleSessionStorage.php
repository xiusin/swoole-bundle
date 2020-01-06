<?php
/**
 * Author: chenchengbin@altamob.com
 * Date: 2018-08-31 15:49
 * Description: Please write description.
 */

namespace xiusin\SwooleBundle\Plugins\Session;

use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

class SwooleSessionStorage implements SessionStorageInterface
{
    /**
     * @var SessionBagInterface[]
     */
    protected $bags = array();

    private $sessionId = '';
    private $sessionName = '';
    protected $session = array();
    protected $started = false;
    protected $closed = false;
    protected $saveHandler;
    protected $metadataBag;
    private $emulateSameSite;

    public function __construct($handler = null, MetadataBag $metaBag = null)
    {
        $this->sessionName = session_name();
        $this->setMetadataBag($metaBag);
        $this->setOptions([]);
        $this->setSaveHandler($handler);
    }

    /**
     * 获取saveHandler实例.
     *
     * @return AbstractProxy|\SessionHandlerInterface
     */
    public function getSaveHandler()
    {
        return $this->saveHandler;
    }

    /**
     * 启动session.
     */
    public function start()
    {
        $this->loadSession();
        return true;
    }

    /**
     * 获取sessionID.
     */
    public function getId()
    {
        if (!$this->sessionId) {
            $this->setId(null);
        }
        return $this->sessionId;
    }

    /**
     * 设置SESSION_ID.
     *
     * @param null $id
     */
    public function setId($id = null)
    {
        if (!$id) {
            $id = session_id() || session_create_id('sess-');
        }
        $this->sessionId = $id;
    }

    /**
     * 获取sessionName.
     */
    public function getName()
    {
        return $this->sessionName;
    }

    /**
     * 设置sessionName.
     */
    public function setName($name)
    {
        $this->sessionName = $name;
    }

    public function regenerate($destroy = false, $lifetime = null)
    {
        return false;
    }

    public function save()
    {
        foreach ($this->bags as $bag) {
            if (empty($this->session[$key = $bag->getStorageKey()])) {
                unset($this->session[$key]);
            }
        }
        if (array($key = $this->metadataBag->getStorageKey()) === array_keys($this->session)) {
            unset($this->session[$key]);
        }

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new \ErrorException($errstr, $errno, E_WARNING, $errfile, $errline);
        }, E_WARNING);

        try {
            $this->getSaveHandler()->write($this->getId(), serialize($this->session));
        } catch (\ErrorException $e) {
        } finally {
            restore_error_handler();
        }
        $this->closed = true;
        $this->started = false;
    }

    public function clear()
    {
        foreach ($this->bags as $bag) {
            $bag->clear();
        }
        $this->session = array();
        $this->loadSession();
    }

    public function registerBag(SessionBagInterface $bag)
    {
        if ($this->started) {
            throw new \LogicException('Cannot register a bag when the session is already started.');
        }
        $this->bags[$bag->getName()] = $bag;
    }

    public function getBag($name)
    {
        if (!$this->session) {
            $this->loadSession();
        }
        return $this->bags[$name];
    }

    public function setMetadataBag(MetadataBag $metaBag = null)
    {
        if (null === $metaBag) {
            $metaBag = new MetadataBag();
        }

        $this->metadataBag = $metaBag;
    }

    /**
     * Gets the MetadataBag.
     *
     * @return MetadataBag
     */
    public function getMetadataBag()
    {
        return $this->metadataBag;
    }

    public function isStarted()
    {
        return $this->started;
    }

    public function setSaveHandler($saveHandler = null)
    {
        if (!$saveHandler instanceof AbstractProxy &&
            !$saveHandler instanceof \SessionHandlerInterface &&
            null !== $saveHandler) {
            throw new \InvalidArgumentException('Must be instance of AbstractProxy; implement \SessionHandlerInterface; or be null.');
        }
        if (!$saveHandler instanceof AbstractProxy && $saveHandler instanceof \SessionHandlerInterface) {
            $saveHandler = new SessionHandlerProxy($saveHandler);
        } elseif (!$saveHandler instanceof AbstractProxy) {
            $saveHandler = new SessionHandlerProxy(new StrictSessionHandler(new \SessionHandler()));
        }
        $this->saveHandler = $saveHandler;
    }

    protected function loadSession()
    {
        $this->session = @unserialize($this->saveHandler->read($this->getId())) ?: [];
        $bags = array_merge($this->bags, array($this->metadataBag));
        /**
         * @var $bag SessionBagInterface
         */
        foreach ($bags as $bag) {
            $key = $bag->getStorageKey();
            $this->session[$key] = $this->session[$key] ?? [];
            $bag->initialize($this->session[$key]);
        }
        $this->started = true;
        $this->closed = false;
    }

    public function setOptions(array $options)
    {
        $validOptions = array_flip([
            'cache_expire', 'cache_limiter', 'cookie_domain', 'cookie_httponly',
            'cookie_lifetime', 'cookie_path', 'cookie_secure', 'cookie_samesite',
            'gc_divisor', 'gc_maxlifetime', 'gc_probability',
            'lazy_write', 'name', 'referer_check',
            'serialize_handler', 'use_strict_mode', 'use_cookies',
            'use_only_cookies', 'use_trans_sid', 'upload_progress.enabled',
            'upload_progress.cleanup', 'upload_progress.prefix', 'upload_progress.name',
            'upload_progress.freq', 'upload_progress.min_freq', 'url_rewriter.tags',
            'sid_length', 'sid_bits_per_character', 'trans_sid_hosts', 'trans_sid_tags',
        ]);

        foreach ($options as $key => $value) {
            if (isset($validOptions[$key])) {
                if ('cookie_samesite' === $key && \PHP_VERSION_ID < 70300) {
                    $this->emulateSameSite = $value;
                    continue;
                }
                ini_set('url_rewriter.tags' !== $key ? 'session.'.$key : $key, $value);
            }
        }
    }
}
