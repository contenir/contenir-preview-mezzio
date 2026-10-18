<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\Unit;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\Mezzio\TargetMatcher;
use Contenir\Preview\Mezzio\Tests\TestAsset\RecordingMiddleware;
use Contenir\Preview\Resolver\PreviewTarget;
use Laminas\Diactoros\ServerRequest;
use Mezzio\Router\Exception\RuntimeException as RouterException;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The request set up so the application routes it to the page a resolver names, as it would a
 * visit.
 */
#[Group('unit')]
final class TargetMatcherTest extends TestCase
{
    #[Test]
    public function carriesAHandlerPastTheRouterAtThePreviewEndpoint(): void
    {
        $target = PreviewTarget::handler('DraftHandler', ['id' => '7']);

        $request = (new TargetMatcher($this->createStub(RouterInterface::class)))->prepare($target, $this->request());

        static::assertSame(
            [$target, '/api/resource/preview'],
            [$request->getAttribute(TargetMatcher::HANDLER_ATTRIBUTE), $request->getUri()->getPath()],
        );
    }

    #[Test]
    public function matchesThePageAtItsNewAddress(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(static::once())
            ->method('match')
            ->with(static::callback(
                static fn(ServerRequestInterface $request): bool => '/spa-rituals' === $request->getUri()->getPath(),
            ))
            ->willReturn($this->found());

        (new TargetMatcher($router))->prepare(PreviewTarget::path('/spa-rituals'), $this->request());
    }

    #[Test]
    public function movesTheRequestToANamedRoutesAddress(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generateUri')
            ->with('news/article', ['slug' => 'spa-rituals'])
            ->willReturn('/news/spa-rituals');
        $router->method('match')->willReturn($this->found());

        $request = (new TargetMatcher($router))->prepare(
            PreviewTarget::route('news/article', ['slug' => 'spa-rituals']),
            $this->request(),
        );

        static::assertSame('/news/spa-rituals', $request->getUri()->getPath());
    }

    #[Test]
    public function movesTheRequestToAPath(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('match')->willReturn($this->found());

        $request = (new TargetMatcher($router))->prepare(PreviewTarget::path('/spa-rituals'), $this->request());

        static::assertSame(
            ['https://www.example.test/spa-rituals', null],
            [(string) $request->getUri(), $request->getAttribute(TargetMatcher::HANDLER_ATTRIBUTE)],
        );
    }

    #[Test]
    public function refusesAPathTheSiteHasNoPageAt(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('match')->willReturn(RouteResult::fromRouteFailure(null));

        $this->assertRefused(
            'The site has no page at this record\'s address.',
            fn() => (new TargetMatcher($router))->prepare(PreviewTarget::path('/gone'), $this->request()),
        );
    }

    #[Test]
    public function refusesARouteTheRouterCannotGenerate(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generateUri')->willThrowException(new RouterException('Cannot generate URI for route "news"'));

        $this->assertRefused(
            'The site has no route for this record.',
            fn() => (new TargetMatcher($router))->prepare(PreviewTarget::route('news'), $this->request()),
        );
    }

    /**
     * @param callable(): mixed $prepare
     */
    private function assertRefused(string $reason, callable $prepare): void
    {
        try {
            $prepare();
            static::fail('The target was prepared.');
        } catch (InvalidPreview $refusal) {
            static::assertSame([404, $reason], [$refusal->status, $refusal->getMessage()]);
        }
    }

    private function found(): RouteResult
    {
        return RouteResult::fromRoute(new Route('/{slug}', new RecordingMiddleware()));
    }

    private function request(): ServerRequest
    {
        return new ServerRequest(
            uri: 'https://www.example.test/api/resource/preview',
            method: 'GET',
        );
    }
}
