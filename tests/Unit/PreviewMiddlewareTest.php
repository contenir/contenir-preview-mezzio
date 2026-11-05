<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\Unit;

use Contenir\Preview\Mezzio\PreviewMiddleware;
use Contenir\Preview\Mezzio\PreviewResponder;
use Contenir\Preview\Mezzio\TargetMatcher;
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
use SensitiveParameter;
use stdClass;

/**
 * A post to the preview endpoint opened before the router runs and sent down the pipeline as a
 * visit to its page, and the answer it gets.
 */
#[Group('unit')]
final class PreviewMiddlewareTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>|object|null}>
     */
    public static function bodiesWithoutAToken(): array
    {
        return [
            'no body'                => [null],
            'a body that is no form' => [new stdClass()],
            'no token in the form'   => [['title' => 'Spa']],
            'a token that is a list' => [['contenir_preview' => ['not', 'a', 'token']]],
        ];
    }

    #[Test]
    #[DataProvider('bodiesWithoutAToken')]
    public function answersAPostWithoutATokenAsMalformed(array|object|null $body): void
    {
        $next = new RecordingHandler();

        $response = $this->middleware(new PreviewContext())->process($this->posted()->withParsedBody($body), $next);

        static::assertSame([400, null], [$response->getStatusCode(), $next->handled]);
    }

    #[Test]
    public function answersARefusalWithoutGoingDownThePipeline(): void
    {
        $next = new RecordingHandler();

        $response = $this->middleware(new PreviewContext())->process(
            $this->posted(Previews::token(new FrozenClock()))->withMethod('PUT'),
            $next,
        );

        static::assertSame(
            [405, 'no-store, private', null],
            [$response->getStatusCode(), $response->getHeaderLine('Cache-Control'), $next->handled],
        );
    }

    #[Test]
    public function answersThePreviewWithThePagesResponseMarked(): void
    {
        $response = $this->middleware(new PreviewContext())->process(
            $this->posted(Previews::token(new FrozenClock())),
            new RecordingHandler(),
        );

        static::assertSame(
            [200, 'noindex, nofollow', '<html><body>Spa Rituals' . PreviewMarker::HTML . '</body></html>'],
            [$response->getStatusCode(), $response->getHeaderLine('X-Robots-Tag'), (string) $response->getBody()],
        );
    }

    #[Test]
    public function beginsThePreviewContext(): void
    {
        $context = new PreviewContext();

        $this->middleware($context)->process($this->posted(Previews::token(new FrozenClock())), new RecordingHandler());

        static::assertSame('site.page.home', $context->request()?->record->section);
    }

    #[Test]
    public function leavesAnotherAddressToThePipeline(): void
    {
        $next    = new RecordingHandler();
        $request = new ServerRequest(
            uri: 'https://www.example.test/spa-rituals',
            method: 'POST',
        );

        $response = $this->middleware(new PreviewContext())->process($request, $next);

        static::assertSame([$request, ''], [$next->handled, $response->getHeaderLine('X-Robots-Tag')]);
    }

    #[Test]
    public function marksAPreviewNeitherToBeServedFromACacheNorStored(): void
    {
        $next = new RecordingHandler();

        $this->middleware(new PreviewContext())->process(
            $this->posted(Previews::token(new FrozenClock()))->withHeader('Cache-Control', 'max-age=0'),
            $next,
        );

        static::assertSame(['no-cache, no-store'], $next->handled?->getHeader('Cache-Control'));
    }

    #[Test]
    public function sendsThePreviewDownThePipelineAsAVisitToItsPage(): void
    {
        $next = new RecordingHandler();

        $this->middleware(new PreviewContext())->process($this->posted(Previews::token(new FrozenClock())), $next);

        static::assertSame(
            ['GET', [], '/spa-rituals'],
            [$next->handled?->getMethod(), $next->handled?->getParsedBody(), $next->handled?->getUri()->getPath()],
        );
    }

    private function middleware(PreviewContext $context): PreviewMiddleware
    {
        $clock  = new FrozenClock();
        $router = $this->createStub(RouterInterface::class);
        $router->method('match')->willReturn(RouteResult::fromRoute(new Route('/{slug}', new RecordingMiddleware())));

        return new PreviewMiddleware(
            new PreviewEntry(
                new TokenVerifier(Previews::config(), $clock, new InMemoryReplayGuard($clock)),
                new ResolverRegistry(['site.page.home' => new PathResolver()]),
                $context,
            ),
            new TargetMatcher($router),
            new PreviewResponder(
                new PreviewResponse(Previews::config()),
                new ResponseFactory(),
                new StreamFactory(),
                true,
            ),
        );
    }

    private function posted(#[SensitiveParameter] ?string $token = null): ServerRequest
    {
        return new ServerRequest(
            uri: 'https://www.example.test/api/resource/preview',
            method: 'POST',
            parsedBody: null === $token ? null : ['contenir_preview' => $token],
        );
    }
}
