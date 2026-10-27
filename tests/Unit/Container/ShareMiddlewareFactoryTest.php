<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\Unit\Container;

use Contenir\Preview\Container\PreviewOptions;
use Contenir\Preview\Mezzio\Container\ShareMiddlewareFactory;
use Contenir\Preview\Mezzio\Tests\TestAsset\ArrayContainer;
use Contenir\Preview\Mezzio\Tests\TestAsset\FrozenClock;
use Contenir\Preview\Mezzio\Tests\TestAsset\Previews;
use Contenir\Preview\Mezzio\Tests\TestAsset\RecordingHandler;
use Contenir\Preview\Mezzio\Tests\TestAsset\Share\ArrayCounter;
use Contenir\Preview\Mezzio\Tests\TestAsset\Share\ArrayShareStore;
use Contenir\Preview\Mezzio\Tests\TestAsset\Share\Shares;
use Contenir\Preview\PreviewContext;
use Contenir\Preview\PreviewResponse;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Share\ShareEntry;
use Contenir\Preview\Share\SharePass;
use Contenir\Preview\Share\Throttle\ShareThrottle;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The share middleware built from the container's services, answering links once the site has a
 * `contenir_preview.share` configuration and leaving them alone until it does.
 */
#[Group('unit')]
final class ShareMiddlewareFactoryTest extends TestCase
{
    #[Test]
    public function answersLinksOnceSharingIsOn(): void
    {
        $response = (new ShareMiddlewareFactory())($this->container(['share' => []]))
            ->process(
                new ServerRequest(
                    uri: 'https://www.example.test' . Shares::PATH,
                    method: 'GET',
                ),
                new RecordingHandler(),
            );

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function leavesLinksAloneWhileSharingIsOff(): void
    {
        $next    = new RecordingHandler();
        $request = new ServerRequest(
            uri: 'https://www.example.test' . Shares::PATH,
            method: 'GET',
        );

        (new ShareMiddlewareFactory())($this->container([]))->process($request, $next);

        $this->assertSame($request, $next->handled);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function container(array $settings): ArrayContainer
    {
        $clock = new FrozenClock();

        return new ArrayContainer([
            PreviewOptions::class           => PreviewOptions::fromAppConfig(Previews::appConfig($settings)),
            PreviewResponse::class          => new PreviewResponse(Previews::config()),
            ShareEntry::class               => new ShareEntry(
                new ArrayShareStore(Shares::make()),
                new ResolverRegistry([]),
                new PreviewContext(),
                new SharePass(Previews::config()),
                new ShareThrottle(new ArrayCounter(), $clock),
                $clock,
            ),
            RouterInterface::class          => $this->createStub(RouterInterface::class),
            ResponseFactoryInterface::class => new ResponseFactory(),
            StreamFactoryInterface::class   => new StreamFactory(),
        ]);
    }
}
