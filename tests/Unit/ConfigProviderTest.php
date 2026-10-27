<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\Unit;

use Contenir\Preview\ConfigProvider as CoreConfigProvider;
use Contenir\Preview\Mezzio\ConfigProvider;
use Contenir\Preview\Mezzio\Container\PreviewMiddlewareFactory;
use Contenir\Preview\Mezzio\Container\PreviewRouteMiddlewareDelegator;
use Contenir\Preview\Mezzio\Container\ShareMiddlewareFactory;
use Contenir\Preview\Mezzio\PreviewMiddleware;
use Contenir\Preview\Mezzio\ShareMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The services the package registers with a Mezzio application.
 */
#[Group('unit')]
final class ConfigProviderTest extends TestCase
{
    #[Test]
    public function registersTheCoresServicesThePreviewAndShareMiddlewareAndTheRoutingWrapper(): void
    {
        static::assertSame(
            [
                'factories'  => [
                    ...(new CoreConfigProvider())->getDependencies()['factories'],
                    PreviewMiddleware::class => PreviewMiddlewareFactory::class,
                    ShareMiddleware::class   => ShareMiddlewareFactory::class,
                ],
                'delegators' => [RouteMiddleware::class => [PreviewRouteMiddlewareDelegator::class]],
            ],
            (new ConfigProvider())->getDependencies(),
        );
    }

    #[Test]
    public function servesTheDependenciesAsTheApplicationsConfiguration(): void
    {
        static::assertSame(
            ['dependencies' => (new ConfigProvider())->getDependencies()],
            (new ConfigProvider())(),
        );
    }
}
