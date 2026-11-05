<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio;

use Contenir\Preview\ConfigProvider as CoreConfigProvider;
use Contenir\Preview\Mezzio\Container\PreviewMiddlewareFactory;
use Contenir\Preview\Mezzio\Container\PreviewRouteMiddlewareDelegator;
use Contenir\Preview\Mezzio\Container\ShareMiddlewareFactory;
use Mezzio\Router\Middleware\RouteMiddleware;

/**
 * Registers the services contenir-preview builds from the `contenir_preview` configuration, the
 * preview and share middleware, and the wrapper that lets RouteMiddleware route a preview's
 * handler.
 *
 * @api
 */
final class ConfigProvider
{
    /**
     * @return array{
     *     factories: array<string, class-string>,
     *     delegators: array<class-string, list<class-string>>,
     * }
     */
    public function getDependencies(): array
    {
        $core = (new CoreConfigProvider())->getDependencies();

        return [
            'factories'  => [
                ...$core['factories'],
                PreviewMiddleware::class => PreviewMiddlewareFactory::class,
                ShareMiddleware::class   => ShareMiddlewareFactory::class,
            ],
            'delegators' => [RouteMiddleware::class => [PreviewRouteMiddlewareDelegator::class]],
        ];
    }

    /**
     * @return array{dependencies: array{
     *     factories: array<string, class-string>,
     *     delegators: array<class-string, list<class-string>>,
     * }}
     */
    public function __invoke(): array
    {
        return ['dependencies' => $this->getDependencies()];
    }
}
