<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\Unit\Container;

use Contenir\Preview\Container\InvalidConfiguration;
use Contenir\Preview\Mezzio\Container\PreviewRouteMiddlewareDelegator;
use Contenir\Preview\Mezzio\Tests\TestAsset\ArrayContainer;
use Contenir\Preview\Mezzio\Tests\TestAsset\RecordingHandler;
use Contenir\Preview\Mezzio\Tests\TestAsset\RecordingMiddleware;
use Laminas\Diactoros\ServerRequest;
use Mezzio\MiddlewareFactoryInterface;
use Mezzio\Router\Middleware\RouteMiddleware;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The application's RouteMiddleware wrapped so it also routes a preview's handler.
 */
#[Group('unit')]
final class PreviewRouteMiddlewareDelegatorTest extends TestCase
{
    #[Test]
    public function refusesARouteMiddlewareThatIsNoMiddleware(): void
    {
        $this->expectExceptionObject(new InvalidConfiguration(
            'The service Mezzio\Router\Middleware\RouteMiddleware is not a middleware.',
        ));

        (new PreviewRouteMiddlewareDelegator())(
            $this->container(),
            RouteMiddleware::class,
            static fn() => new stdClass(),
        );
    }

    #[Test]
    public function routesAVisitThroughTheApplicationsRouteMiddleware(): void
    {
        $routing = new RecordingMiddleware();
        $request = new ServerRequest(
            uri: 'https://www.example.test/spa-rituals',
            method: 'GET',
        );

        (new PreviewRouteMiddlewareDelegator())($this->container(), RouteMiddleware::class, static fn() => $routing)
            ->process($request, new RecordingHandler());

        static::assertSame($request, $routing->processed);
    }

    private function container(): ArrayContainer
    {
        return new ArrayContainer([
            MiddlewareFactoryInterface::class => $this->createStub(MiddlewareFactoryInterface::class),
        ]);
    }
}
