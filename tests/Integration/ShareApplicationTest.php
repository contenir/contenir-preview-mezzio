<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\Integration;

use Contenir\Preview\Mezzio\ConfigProvider;
use Contenir\Preview\Mezzio\PreviewRouteMiddleware;
use Contenir\Preview\Mezzio\ShareMiddleware;
use Contenir\Preview\Mezzio\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Mezzio\Tests\TestAsset\Previews;
use Contenir\Preview\Mezzio\Tests\TestAsset\Share\ArrayShareStore;
use Contenir\Preview\Mezzio\Tests\TestAsset\Share\Shares;
use Contenir\Preview\Mezzio\Tests\TestAsset\Site\DraftHandler;
use Contenir\Preview\Mezzio\Tests\TestAsset\Site\DraftResolver;
use Contenir\Preview\Mezzio\Tests\TestAsset\Site\PageHandler;
use Contenir\Preview\Mezzio\Tests\Trait\TempDirectoryTrait;
use Contenir\Preview\PreviewContext;
use Contenir\Preview\PreviewMarker;
use Contenir\Preview\Share\Share;
use Contenir\Preview\Share\SharePass;
use Contenir\Preview\Share\ShareStoreInterface;
use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\Diactoros\ConfigProvider as DiactorosConfigProvider;
use Laminas\Diactoros\ServerRequest;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\Application;
use Mezzio\ConfigProvider as MezzioConfigProvider;
use Mezzio\Handler\NotFoundHandler;
use Mezzio\Router\ConfigProvider as RouterConfigProvider;
use Mezzio\Router\FastRouteRouter\ConfigProvider as FastRouteConfigProvider;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

use function explode;
use function str_contains;

/**
 * A Mezzio application with the package, answering a shared preview link from start to finish:
 * the code form, the code, the way back with the pass, and the record's page.
 */
#[Group('integration')]
#[Group('middleware')]
final class ShareApplicationTest extends TestCase
{
    use TempDirectoryTrait;

    #[Test]
    public function asksForTheCodeThenShowsTheRecordToAVisitorWhoEnteredIt(): void
    {
        $application = $this->application();
        $form        = $application->handle($this->request('GET'));
        $admitted    = $application->handle($this->request('POST', body: ['code' => Shares::CODE]));
        $pass        = explode('=', explode(';', $admitted->getHeaderLine('Set-Cookie'))[0], limit: 2)[1] ?? '';
        $page        = $application->handle($this->request('GET', [SharePass::COOKIE => $pass]));

        $this->assertSame(
            [200, 303, Shares::PATH, 200, true, true, "frame-ancestors 'none'"],
            [
                $form->getStatusCode(),
                $admitted->getStatusCode(),
                $admitted->getHeaderLine('Location'),
                $page->getStatusCode(),
                str_contains(
                    (string) $page->getBody(),
                    '<p>route ' . PreviewRouteMiddleware::HANDLER_ROUTE . ', mode draft</p>',
                ),
                str_contains((string) $page->getBody(), PreviewMarker::HTML),
                $page->getHeaderLine('Content-Security-Policy'),
            ],
        );
    }

    #[Test]
    public function keepsEveryAnswerFromBeingStored(): void
    {
        $application = $this->application();

        $this->assertSame(
            ['no-store, private', 'no-store, private', 'no-store, private'],
            [
                $application->handle($this->request('GET'))->getHeaderLine('Cache-Control'),
                $application->handle($this->request('POST', body: ['code' => 'WRONG1']))
                    ->getHeaderLine('Cache-Control'),
                $application->handle($this->request('GET', [
                    SharePass::COOKIE => Shares::pass(
                        Shares::make('site.page.draft', ['resource_id' => '7']),
                    ),
                ]))
                    ->getHeaderLine('Cache-Control'),
            ],
        );
    }

    #[Test]
    public function leavesAnOrdinaryVisitAlone(): void
    {
        $response = $this->application()->handle(new ServerRequest(
            uri: 'https://www.example.test/spa-rituals',
            method: 'GET',
        ));

        $this->assertSame([200, false], [$response->getStatusCode(), $response->hasHeader('X-Robots-Tag')]);
    }

    #[Test]
    public function refusesARecordAPathCannotPlaceWithoutItsValues(): void
    {
        $share    = Shares::make();
        $response = $this->application($share)->handle($this->request('GET', [
            SharePass::COOKIE => Shares::pass($share),
        ]));

        $this->assertSame(
            [404, true],
            [
                $response->getStatusCode(),
                str_contains((string) $response->getBody(), 'The site has no page at this record&apos;s address.'),
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->setUpTempDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownTempDirectory();
    }

    /**
     * The test site with sharing on and one link, to a saved draft its handler resolver places
     * unless another is given.
     */
    private function application(?Share $share = null): Application
    {
        $merged = (new ConfigAggregator([
            MezzioConfigProvider::class,
            RouterConfigProvider::class,
            FastRouteConfigProvider::class,
            DiactorosConfigProvider::class,
            ConfigProvider::class,
            new ArrayProvider([
                ...Previews::appConfig([
                    'clock'     => 'TestClock',
                    'replay'    => ['directory' => $this->directory],
                    'share'     => [],
                    'resolvers' => [
                        'site.page.home'  => '/{slug}',
                        'site.page.draft' => DraftResolver::class,
                    ],
                ]),
                'dependencies' => [
                    'services'   => [
                        'TestClock'                => new FrozenClock(),
                        ShareStoreInterface::class => new ArrayShareStore(
                            $share ?? Shares::make('site.page.draft', ['resource_id' => '7']),
                        ),
                    ],
                    'invokables' => [DraftResolver::class => DraftResolver::class],
                    'factories'  => [
                        PageHandler::class  => static fn(ContainerInterface $container): PageHandler => new PageHandler(
                            $container->get(PreviewContext::class),
                        ),
                        DraftHandler::class => static fn(ContainerInterface $container): DraftHandler => new DraftHandler(
                            $container->get(PreviewContext::class),
                        ),
                    ],
                ],
            ]),
        ]))->getMergedConfig();

        $dependencies                       = $merged['dependencies'];
        $dependencies['services']['config'] = $merged;
        $application                        = (new ServiceManager($dependencies))->get(Application::class);
        $application->pipe(ShareMiddleware::class);
        $application->pipe(RouteMiddleware::class);
        $application->pipe(DispatchMiddleware::class);
        $application->pipe(NotFoundHandler::class);
        $application->get('/{slug}', PageHandler::class, 'page');

        return $application;
    }

    /**
     * @param array<string, string> $cookies
     * @param array<string, string> $body
     */
    private function request(string $method, array $cookies = [], array $body = []): ServerRequest
    {
        return new ServerRequest(
            serverParams: ['REMOTE_ADDR' => '203.0.113.7'],
            uri: 'https://www.example.test' . Shares::PATH,
            method: $method,
            cookieParams: $cookies,
            parsedBody: $body,
        );
    }
}
