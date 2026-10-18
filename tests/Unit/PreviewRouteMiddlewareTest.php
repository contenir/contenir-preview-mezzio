<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\Unit;

use Contenir\Preview\Mezzio\PreviewRouteMiddleware;
use Contenir\Preview\Mezzio\TargetMatcher;
use Contenir\Preview\Mezzio\Tests\TestAsset\RecordingHandler;
use Contenir\Preview\Mezzio\Tests\TestAsset\RecordingMiddleware;
use Contenir\Preview\Resolver\PreviewTarget;
use Laminas\Diactoros\ServerRequest;
use Mezzio\MiddlewareFactoryInterface;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RouteMiddleware, which also routes a preview whose resolver named a handler.
 */
#[Group('unit')]
final class PreviewRouteMiddlewareTest extends TestCase
{
    #[Test]
    public function givesAHandlerARouteOfItsOwnAtThePreviewEndpoint(): void
    {
        $draft      = new RecordingMiddleware();
        $middleware = $this->createMock(MiddlewareFactoryInterface::class);
        $middleware->method('prepare')->with('DraftHandler')->willReturn($draft);
        $next = new RecordingHandler();

        (new PreviewRouteMiddleware(new RecordingMiddleware(), $middleware))->process(
            $this->request()->withAttribute(
                TargetMatcher::HANDLER_ATTRIBUTE,
                PreviewTarget::handler('DraftHandler', ['id' => '7']),
            ),
            $next,
        );
        $result = $next->handled?->getAttribute(RouteResult::class);
        static::assertInstanceOf(RouteResult::class, $result);
        $route = $result->getMatchedRoute();
        static::assertInstanceOf(Route::class, $route);

        static::assertSame(
            [PreviewRouteMiddleware::HANDLER_ROUTE, '/api/resource/preview', ['GET'], $draft, ['id' => '7'], '7'],
            [
                $route->getName(),
                $route->getPath(),
                $route->getAllowedMethods(),
                $route->getMiddleware(),
                $result->getMatchedParams(),
                $next->handled?->getAttribute('id'),
            ],
        );
    }

    #[Test]
    public function leavesAnyOtherRequestToTheApplicationsRouting(): void
    {
        $routing = new RecordingMiddleware();
        $request = $this->request()->withAttribute(TargetMatcher::HANDLER_ATTRIBUTE, PreviewTarget::path('/spa'));

        (new PreviewRouteMiddleware($routing, $this->createStub(MiddlewareFactoryInterface::class)))->process(
            $request,
            new RecordingHandler(),
        );

        static::assertSame($request, $routing->processed);
    }

    #[Test]
    public function leavesAVisitToTheApplicationsRouting(): void
    {
        $routing = new RecordingMiddleware();
        $request = $this->request();

        (new PreviewRouteMiddleware($routing, $this->createStub(MiddlewareFactoryInterface::class)))->process(
            $request,
            new RecordingHandler(),
        );

        static::assertSame($request, $routing->processed);
    }

    private function request(): ServerRequest
    {
        return new ServerRequest(
            uri: 'https://www.example.test/api/resource/preview',
            method: 'GET',
        );
    }
}
