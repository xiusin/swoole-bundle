
1. 安装 
```shell
composer require xiusin/swoole-bundle
```

2. 注册包
```php
<?php
// config/bundles.php

return [
 // ...
 App\Bundles\SwooleBundle\SwooleBundle::class => ['all' => true], 
 // ...
];
```

配置bundle:
```yaml
// config/services.yaml

services:
    // ...

    swoole.session.handler:
        class: App\Bundles\SwooleBundle\Plugins\Session\NativeFileSessionHandler
        arguments:
            - '%kernel.project_dir%/var/sessions/%kernel.environment%/'
            - 'swsf_'

    swoole.session.storage:
        class: App\Bundles\SwooleBundle\Plugins\Session\SwooleSessionStorage
        arguments:
            - '@swoole.session.handler'
    app.swoole:
        public: true
        synthetic: true

    # 消息队列 不设置异步接收器则会直接处理队列
    App\MessageHandler\PutStreamToRTMPHandler:
        tags: [messenger.message_handler]

```

配置bundle文件 (添加到`packages/swoole.yaml`): 
```yaml
swoole:
  # 配置服务类型
  server: Swoole\Http\Server # Swoole\WebSocket\Server 需要在  event_listeners 配置注册 ServerOnManagerEventListener对象.

  # 服务的配置
  config:
      worker_num: 1
      http_compression: true
      max_request: 0
      log_file: '%kernel.logs_dir%/swoole_%kernel.environment%.log'
      document_root: '%kernel.project_dir%/public/'
      enable_static_handler: true

  # 附加进程列表
  processes:
    - App\Process\TestProcess

  # 附加channel列表
#  chans:
#    - {class: App\Chan\DemoChan, size: 1500}

  # 附加表列表
  tables:
    - App\Table\DemoTable

  # 事件监听列表
  event_listeners:
    - App\ServerEventListener\ServerOnOpenEventListener
    - App\ServerEventListener\ServerOnManagerEventListener
    - App\ServerEventListener\ServerOnCloseEventListener
```


修改框架配置文件: 

```yaml
// packages/framework.yaml
framework:
    // other key
    session:
        storage_id: swoole.session.storage
        handler_id: ~
    // ...
```






# TODO #
 -[ ] 让session在initBundles之间共享




