<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\Unit;

use Contenir\Preview\Mezzio\PreviewResponder;
use Contenir\Preview\Mezzio\ShareMiddleware;
use Contenir\Preview\Mezzio\ShareResponder;
use Contenir\Preview\Mezzio\TargetMatcher;
use Contenir\Preview\Mezzio\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Mezzio\Tests\TestAsset\Previews;
use Contenir\Preview\Mezzio\Tests\TestAsset\RecordingHandler;
use Contenir\Preview\Mezzio\Tests\TestAsset\RecordingMiddleware;
use Contenir\Preview\Mezzio\Tests\TestAsset\Share\ArrayCounter;
use Contenir\Preview\Mezzio\Tests\TestAsset\Share\ArrayShareStore;
use Contenir\Preview\Mezzio\Tests\TestAsset\Share\Shares;
use Contenir\Preview\PreviewContext;
use Contenir\Preview\PreviewMarker;
use Contenir\Preview\PreviewResponse;
use Contenir\Preview\Resolver\PathResolver;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Share\ShareEntry;
use Contenir\Preview\Share\SharePass;
use Contenir\Preview\Share\Throttle\ShareThrottle;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Router\RouterInterface;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

use function str_contains;

/**
 * A visit to a link answered before the router runs: with the code form, the pass, a refusal, or
 * the record's page, sent down the pipeline as a visit to it.
 */
#[Group('unit')]
final class ShareMiddlewareTest extends TestCase
{
    private const string VISITOR = '203.0.113.7';

    private PreviewContext $context;

    private ArrayShareStore $store;

    /**
     * @return array<string, array{array<string, mixed>|object|null}>
     */
    public static function bodiesWithoutTheCode(): array
    {
        return [
            'no body'                => [null],
            'a body that is no form' => [new stdClass()],
            'no code in the form'    => [['title' => 'Spa']],
            'a code that is a list'  => [['code' => ['K7M4QX']]],
        ];
    }

    #[Test]
    public function admitsAVisitorWhoEntersTheCodeAndSendsThemBack(): void
    {
        $response = $this->middleware()->process($this->posted(['code' => 'k7m-4qx']), new RecordingHandler());

        $this->assertSame(
            [303, Shares::PATH, (new SharePass(Previews::config()))->cookie(Shares::make()), 'no-store, private'],
            [
                $response->getStatusCode(),
                $response->getHeaderLine('Location'),
                $response->getHeaderLine('Set-Cookie'),
                $response->getHeaderLine('Cache-Control'),
            ],
        );
    }

    #[Test]
    #[DataProvider('bodiesWithoutTheCode')]
    public function answersAPostWithoutTheCodeAsAWrongCode(array|object|null $body): void
    {
        $response = $this->middleware()->process($this->posted()->withParsedBody($body), new RecordingHandler());

        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function asksAgainSayingTheCodeWasWrong(): void
    {
        $response = $this->middleware()->process($this->posted(['code' => 'WRONG1']), new RecordingHandler());

        $this->assertSame(
            [401, true, 'noindex, nofollow'],
            [
                $response->getStatusCode(),
                str_contains((string) $response->getBody(), 'That code isn&apos;t right.'),
                $response->getHeaderLine('X-Robots-Tag'),
            ],
        );
    }

    #[Test]
    public function asksAVisitorWithoutAPassForTheCode(): void
    {
        $next     = new RecordingHandler();
        $response = $this->middleware()->process($this->visit(), $next);

        $this->assertSame(
            [200, true, "frame-ancestors 'none'", 'text/html; charset=utf-8', null],
            [
                $response->getStatusCode(),
                str_contains((string) $response->getBody(), 'Enter the code you were given'),
                $response->getHeaderLine('Content-Security-Policy'),
                $response->getHeaderLine('Content-Type'),
                $next->handled,
            ],
        );
    }

    #[Test]
    public function countsWrongCodesByTheAddressTheyCameFrom(): void
    {
        $middleware = $this->middleware();
        for ($tries = 0; $tries < ShareThrottle::PER_VISITOR; $tries++) {
            $middleware->process($this->posted(['code' => 'WRONG1']), new RecordingHandler());
        }

        $this->assertSame(
            [429, 303],
            [
                $middleware->process($this->posted(['code' => Shares::CODE]), new RecordingHandler())->getStatusCode(),
                $middleware->process(
                    $this->posted(['code' => Shares::CODE], ['REMOTE_ADDR' => '198.51.100.1']),
                    new RecordingHandler(),
                )
                    ->getStatusCode(),
            ],
        );
    }

    #[Test]
    public function leavesAnotherAddressToThePipeline(): void
    {
        $next    = new RecordingHandler();
        $request = new ServerRequest(
            uri: 'https://www.example.test/preview',
            method: 'GET',
        );

        $response = $this->middleware()->process($request, $next);

        $this->assertSame([$request, ''], [$next->handled, $response->getHeaderLine('X-Robots-Tag')]);
    }

    #[Test]
    public function leavesEveryAddressToThePipelineWhileSharingIsOff(): void
    {
        $next    = new RecordingHandler();
        $request = $this->visit(Shares::pass());

        $this->middlewareWith(null)->process($request, $next);

        $this->assertSame($request, $next->handled);
    }

    #[Test]
    public function refusesALinkThatIsGone(): void
    {
        $this->store->delete(Shares::ID);

        $response = $this->middleware()->process($this->visit(Shares::pass()), new RecordingHandler());

        $this->assertSame(
            [404, true, "frame-ancestors 'none'"],
            [
                $response->getStatusCode(),
                str_contains((string) $response->getBody(), 'This preview link has expired or been withdrawn.'),
                $response->getHeaderLine('Content-Security-Policy'),
            ],
        );
    }

    #[Test]
    public function refusesAnyMethodButGetAndPost(): void
    {
        $response = $this->middleware()->process($this->visit()->withMethod('PUT'), new RecordingHandler());

        $this->assertSame(405, $response->getStatusCode());
    }

    #[Test]
    public function refusesARecordWhosePageTheSiteDoesNotHave(): void
    {
        $this->store = new ArrayShareStore(Shares::make(section: 'library.news.articles'));

        $response = $this->middleware()->process(
            $this->visit(Shares::pass(Shares::make(
                section: 'library.news.articles',
            ))),
            new RecordingHandler(),
        );

        $this->assertSame([404, false], [$response->getStatusCode(), $this->context->isActive()]);
    }

    #[Test]
    public function sendsAVisitorWithAPassDownThePipelineAsAVisitToThePage(): void
    {
        $next = new RecordingHandler();

        $this->middleware()->process($this->visit(Shares::pass())->withHeader('Cache-Control', 'max-age=0'), $next);

        $this->assertSame(
            ['GET', [], '/spa-rituals', ['no-cache, no-store'], true],
            [
                $next->handled?->getMethod(),
                $next->handled?->getParsedBody(),
                $next->handled?->getUri()->getPath(),
                $next->handled?->getHeader('Cache-Control'),
                $this->context->request()?->shared,
            ],
        );
    }

    #[Test]
    public function showsThePageMarkedAndUnframed(): void
    {
        $response = $this->middleware()->process($this->visit(Shares::pass()), new RecordingHandler());

        $this->assertSame(
            [
                200,
                '<html><body>Spa Rituals' . PreviewMarker::HTML . '</body></html>',
                "frame-ancestors 'none'",
                'no-store, private',
            ],
            [
                $response->getStatusCode(),
                (string) $response->getBody(),
                $response->getHeaderLine('Content-Security-Policy'),
                $response->getHeaderLine('Cache-Control'),
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->context = new PreviewContext();
        $this->store   = new ArrayShareStore(Shares::make());
    }

    private function middleware(): ShareMiddleware
    {
        $clock = new FrozenClock();

        return $this->middlewareWith(new ShareEntry(
            $this->store,
            new ResolverRegistry(['site.page.home' => new PathResolver('/spa-rituals')]),
            $this->context,
            new SharePass(Previews::config()),
            new ShareThrottle(new ArrayCounter(), $clock),
            $clock,
        ));
    }

    private function middlewareWith(?ShareEntry $entry): ShareMiddleware
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('match')->willReturn(RouteResult::fromRoute(new Route('/{slug}', new RecordingMiddleware())));
        $response = new PreviewResponse(Previews::config());

        return new ShareMiddleware(
            $entry,
            new TargetMatcher($router),
            new ShareResponder(
                new PreviewResponder($response, new ResponseFactory(), new StreamFactory(), true),
                $response,
                new ResponseFactory(),
                new StreamFactory(),
            ),
        );
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $server
     */
    private function posted(array $body = [], array $server = ['REMOTE_ADDR' => self::VISITOR]): ServerRequest
    {
        return new ServerRequest(
            serverParams: $server,
            uri: 'https://www.example.test' . Shares::PATH,
            method: 'POST',
            parsedBody: $body,
        );
    }

    private function visit(?string $pass = null): ServerRequest
    {
        return new ServerRequest(
            serverParams: ['REMOTE_ADDR' => self::VISITOR],
            uri: 'https://www.example.test' . Shares::PATH,
            method: 'GET',
            cookieParams: null === $pass ? [] : [SharePass::COOKIE => $pass],
        );
    }
}
