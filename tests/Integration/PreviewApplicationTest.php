<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\Integration;

use Contenir\Preview\Mezzio\ConfigProvider;
use Contenir\Preview\Mezzio\PreviewMiddleware;
use Contenir\Preview\Mezzio\PreviewRouteMiddleware;
use Contenir\Preview\Mezzio\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Mezzio\Tests\TestAsset\Previews;
use Contenir\Preview\Mezzio\Tests\TestAsset\Site\DraftHandler;
use Contenir\Preview\Mezzio\Tests\TestAsset\Site\DraftResolver;
use Contenir\Preview\Mezzio\Tests\TestAsset\Site\PageHandler;
use Contenir\Preview\Mezzio\Tests\Trait\TempDirectoryTrait;
use Contenir\Preview\PreviewContext;
use Contenir\Preview\PreviewMarker;
use Contenir\Preview\PreviewRecord;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;

use function str_contains;

/**
 * A Mezzio application with the package, answering the CMS's previews from start to finish:
 * routed by path or named route, dispatched to a handler, refused, and leaving visits alone.
 */
#[Group('integration')]
#[Group('middleware')]
final class PreviewApplicationTest extends TestCase
{
    use TempDirectoryTrait;

    /**
     * @return array<string, array{PreviewRecord, string}>
     */
    public static function previewsAndTheirRoutes(): array
    {
        return [
            'a page at its path'        => [
                new PreviewRecord('site.page.home', ['resource_id' => '101'], ['slug' => 'spa-rituals']),
                'route page, mode none',
            ],
            'a page at its named route' => [
                new PreviewRecord('site.page.named', ['resource_id' => '101'], ['slug' => 'spa-rituals']),
                'route page, mode none',
            ],
            'a draft at its handler'    => [
                new PreviewRecord('site.page.draft', [], ['title' => 'A draft']),
                'route ' . PreviewRouteMiddleware::HANDLER_ROUTE . ', mode draft',
            ],
        ];
    }

    #[Test]
    public function dispatchesADraftToItsHandler(): void
    {
        $draft = new PreviewRecord('site.page.draft', [], ['title' => 'A page not saved yet']);

        $response = $this->preview($this->configured(), Previews::token(new FrozenClock(), $draft));

        static::assertSame([200, true], [
            $response->getStatusCode(),
            str_contains((string) $response->getBody(), '<h1>A page not saved yet</h1>'),
        ]);
    }

    #[Test]
    public function leavesAnOrdinaryVisitAlone(): void
    {
        $response = $this->visit($this->configured(), '/spa-rituals');

        static::assertSame(
            [200, true, false, false],
            [
                $response->getStatusCode(),
                str_contains((string) $response->getBody(), '<h1>Stored title</h1>'),
                str_contains((string) $response->getBody(), PreviewMarker::HTML),
                $response->hasHeader('X-Robots-Tag'),
            ],
        );
    }

    #[Test]
    #[DataProvider('previewsAndTheirRoutes')]
    public function letsTheMiddlewareAfterTheRouterSeeThePreviewsRoute(PreviewRecord $record, string $route): void
    {
        $response = $this->preview($this->configured(), Previews::token(new FrozenClock(), $record));

        static::assertSame([200, true], [
            $response->getStatusCode(),
            str_contains((string) $response->getBody(), "<p>{$route}</p>"),
        ]);
    }

    #[Test]
    public function refusesAPreviewWhosePageTheSiteDoesNotHave(): void
    {
        $record = new PreviewRecord('site.page.nested', ['resource_id' => '101'], ['slug' => 'spa-rituals']);

        $response = $this->preview($this->configured(), Previews::token(new FrozenClock(), $record));

        static::assertSame(
            [404, true, 'noindex, nofollow'],
            [
                $response->getStatusCode(),
                str_contains((string) $response->getBody(), 'The site has no page at this record&apos;s address.'),
                $response->getHeaderLine('X-Robots-Tag'),
            ],
        );
    }

    #[Test]
    public function refusesATokenUsedTwice(): void
    {
        $token = Previews::token(new FrozenClock());
        $this->preview($this->configured(), $token);

        static::assertSame(409, $this->preview($this->configured(), $token)->getStatusCode());
    }

    #[Test]
    public function rendersThePageWithTheUnsavedValues(): void
    {
        $response = $this->preview($this->configured(), Previews::token(new FrozenClock()));

        static::assertSame(
            [200, true, true, 'noindex, nofollow'],
            [
                $response->getStatusCode(),
                str_contains((string) $response->getBody(), '<h1>Edited title</h1>'),
                str_contains((string) $response->getBody(), PreviewMarker::HTML),
                $response->getHeaderLine('X-Robots-Tag'),
            ],
        );
    }

    #[Test]
    public function routesAsBeforeWherePreviewsAreNotConfigured(): void
    {
        $response = $this->visit($this->application([]), '/spa-rituals');

        static::assertSame([200, true], [
            $response->getStatusCode(),
            str_contains((string) $response->getBody(), '<h1>Stored title</h1>'),
        ]);
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
     * The test site, rendering previews once it has a `contenir_preview` configuration.
     *
     * @param array<string, mixed> $config
     */
    private function application(array $config): Application
    {
        $merged = (new ConfigAggregator([
            MezzioConfigProvider::class,
            RouterConfigProvider::class,
            FastRouteConfigProvider::class,
            DiactorosConfigProvider::class,
            ConfigProvider::class,
            new ArrayProvider([
                ...$config,
                'dependencies' => [
                    'services'   => ['TestClock' => new FrozenClock()],
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
        if (null !== ($config['contenir_preview'] ?? null)) {
            $application->pipe(PreviewMiddleware::class);
        }
        $application->pipe(RouteMiddleware::class);
        $application->pipe(DispatchMiddleware::class);
        $application->pipe(NotFoundHandler::class);
        $application->get('/{slug}', PageHandler::class, 'page');

        return $application;
    }

    private function configured(): Application
    {
        return $this->application(Previews::appConfig([
            'clock'     => 'TestClock',
            'replay'    => ['directory' => $this->directory],
            'resolvers' => [
                'site.page.home'   => '/{slug}',
                'site.page.named'  => ['route' => 'page', 'params' => ['slug' => '{slug}']],
                'site.page.nested' => '/{slug}/nested',
                'site.page.draft'  => DraftResolver::class,
            ],
        ]));
    }

    private function preview(Application $application, #[SensitiveParameter] string $token): ResponseInterface
    {
        return $application->handle(new ServerRequest(
            uri: 'https://www.example.test/api/resource/preview',
            method: 'POST',
            parsedBody: ['contenir_preview' => $token],
        ));
    }

    private function visit(Application $application, string $path): ResponseInterface
    {
        return $application->handle(new ServerRequest(
            uri: "https://www.example.test{$path}",
            method: 'GET',
        ));
    }
}
