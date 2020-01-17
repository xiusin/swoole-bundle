
1. 安装 

```bash
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

执行命令添加`packages/swoole.yaml`: 

```bash
php bin/console swoole:publish
```

```yaml
// 完整的配置文件大概是这样的
swoole:
  # 配置服务类型
  server: Swoole\WebSocket\Server

  # 服务的配置
  config:
      worker_num: 1
      http_compression: true
      max_request: 0
      pid_file: '%kernel.logs_dir%/swoole.server.pid'
      log_file: '%kernel.logs_dir%/swoole_%kernel.environment%.log'
      document_root: '%kernel.project_dir%/public/'
      enable_static_handler: true

  # 附加进程列表
  processes:
    - App\Process\TestProcess

  # 附加channel列表
  chans:
    - {class: App\Chan\DemoChan, size: 1500}

  # 附加表列表
  tables:
    - App\Table\DemoTable

  # 事件监听列表
  event_listeners:
    - App\ServerEventListener\ServerOnOpenEventListener
    - App\ServerEventListener\ServerOnManagerEventListener
    - App\ServerEventListener\ServerOnCloseEventListener
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
 - [ ] 开发session组件适配组件
 - [ ] cookie session开启依赖配置项
 - [ ] 大批量测试数据污染 
 
# 压测数据 #
```bash
# 优化压测
$ wrk -t12 -c100 -d10s http://localhost:9501/index
Running 10s test @ http://localhost:9501/index
  12 threads and 100 connections
\  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency     5.08ms    1.70ms  44.74ms   91.53%
    Req/Sec     1.60k   136.54     1.84k    61.75%
  191082 requests in 10.02s, 38.81MB read
Requests/sec:  19079.03
Transfer/sec:      3.88MB



# 默认压测
$ wrk -t12 -c100 -d10s http://localhost:9501/index
Running 10s test @ http://localhost:9501/index
  12 threads and 100 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency    18.59ms    4.04ms  48.44ms   73.99%
    Req/Sec   431.70     37.94   525.00     57.25%
  51656 requests in 10.03s, 10.49MB read
Requests/sec:   5152.41
Transfer/sec:      1.05MB



# nginx服务器压测 #
# 开通多核,其他优化项不知如何处理
$ wrk -t12 -c100 -d10s http://sf.com/index        
Running 10s test @ http://sf.com/index
  12 threads and 100 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency   101.58ms   84.46ms 661.39ms   95.55%
    Req/Sec    94.57     32.41   161.00     72.00%
  10738 requests in 10.10s, 2.56MB read
Requests/sec:   1062.77
Transfer/sec:    259.42KB

```

