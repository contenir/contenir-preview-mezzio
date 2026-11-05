<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\Unit\Container;

use Contenir\Preview\Container\PreviewOptions;
use Contenir\Preview\Mezzio\Container\PreviewMiddlewareFactory;
use Contenir\Preview\Mezzio\Tests\TestAsset\ArrayContainer;
use Contenir\Preview\Mezzio\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Mezzio\Tests\TestAsset\Previews;
use Contenir\Preview\Mezzio\Tests\TestAsset\RecordingHandler;
use Contenir\Preview\Mezzio\Tests\TestAsset\RecordingMiddleware;
use Contenir\Preview\PreviewContext;
use Contenir\Preview\PreviewEntry;
use Contenir\Preview\PreviewMarker;
use Contenir\Preview\PreviewResponse;
use Contenir\Preview\Resolver\PathResolver;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Token\TokenVerifier;
use Contenir\SiteToken\Replay\InMemoryReplayGuard;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function str_contains;

/**
 * The preview middleware built from the container's services and the `contenir_preview` settings.
 */
#[Group('unit')]
final class PreviewMiddlewareFactoryTest extends TestCase
{
    /**
     * @return array<string, array{bool}>
     */
    public static function markerSettings(): array
    {
        return [
            'the badge on'  => [true],
            'the badge off' => [false],
        ];
    }

    #[Test]
    #[DataProvider('markerSettings')]
    public function marksThePageAsConfigured(bool $marker): void
    {
        $clock   = new FrozenClock();
        $request = new ServerRequest(
            uri: 'https://www.example.test/api/resource/preview',
            method: 'POST',
            parsedBody: ['contenir_preview' => Previews::token($clock)],
        );

        $response = (new PreviewMiddlewareFactory())($this->container($clock, $marker))
            ->process(
                $request,
                new RecordingHandler(),
            );

        static::assertSame([200, $marker], [
            $response->getStatusCode(),
            str_contains((string) $response->getBody(), PreviewMarker::HTML),
        ]);
    }

    private function container(FrozenClock $clock, bool $marker): ArrayContainer
    {
        $context = new PreviewContext();
        $router  = $this->createStub(RouterInterface::class);
        $router->method('match')->willReturn(RouteResult::fromRoute(new Route('/{slug}', new RecordingMiddleware())));

        return new ArrayContainer([
            PreviewEntry::class             => new PreviewEntry(
                new TokenVerifier(Previews::config(), $clock, new InMemoryReplayGuard($clock)),
                new ResolverRegistry(['site.page.home' => new PathResolver()]),
                $context,
            ),
            PreviewResponse::class          => new PreviewResponse(Previews::config()),
            PreviewOptions::class           => PreviewOptions::fromAppConfig(Previews::appConfig([
                'marker' => $marker,
            ])),
            RouterInterface::class          => $router,
            ResponseFactoryInterface::class => new ResponseFactory(),
            StreamFactoryInterface::class   => new StreamFactory(),
        ]);
    }
}
