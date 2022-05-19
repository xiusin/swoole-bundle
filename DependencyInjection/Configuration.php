<?php

namespace xiusin\SwooleBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * 配置树根节点名称
     * @var string
     */
    protected string $rootNodeName = 'swoole';

    /**
     * @var string[]
     */
    protected array $serverEnumList = [
        \Swoole\Http\Server::class,
        \Swoole\WebSocket\Server::class,
        \swoole_http_server::class,
        \swoole_websocket_server::class,
    ];

    protected $treeBuilder;

    /**
     * Generates the configuration tree builder.
     *
     * @return TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $this->treeBuilder = new TreeBuilder($this->rootNodeName);
        $this->treeBuilder->getRootNode()
            ->children()
            ->enumNode('server')->values($this->serverEnumList)->defaultValue(\swoole_http_server::class)->end()
            ->arrayNode('processes')->scalarPrototype()->end()->end()
            ->arrayNode('event_listeners')->scalarPrototype()->end()->end()
            ->arrayNode('tables')->scalarPrototype()->end()->end()
            ->integerNode('http_port')->defaultValue('9501')->end()
            ->scalarNode('http_host')->defaultValue('0.0.0.0')->end()
            ->arrayNode('config')
            ->children()
            ->integerNode('reactor_num')->defaultValue(\swoole_cpu_num())->end()
            ->integerNode('worker_num')->defaultValue(\swoole_cpu_num() * 2)->end()
            ->integerNode('log_level')->defaultValue(5)->end()
            ->booleanNode('http_compression')->defaultValue(true)->end()
            ->integerNode('max_request')->min(0)->defaultValue(0)->end()
            ->scalarNode('log_file')->defaultValue('%kernel.logs_dir%/swoole_%kernel.environment%.log')->end()
            ->scalarNode('pid_file')->defaultValue('%kernel.logs_dir%/swoole.server.pid')->end()
            ->scalarNode('project_dir')->defaultValue('%kernel.project_dir%')->end()
            ->scalarNode('enable_static_handler')->defaultFalse()->end()
            ->scalarNode('document_root')->defaultValue('%kernel.project_dir%/public/')->end()
            ->booleanNode('enable_coroutine')->defaultTrue()->end()
            ->booleanNode('reload_async')->defaultTrue()->end()
            ->booleanNode('enable_reuse_port')->defaultFalse()->end()
            ->end()
            ->end()
            ->arrayNode('chans')
            ->arrayPrototype()
            ->children()
            ->scalarNode('class')->end()
            ->scalarNode('size')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end();

        return $this->treeBuilder;
    }
}
