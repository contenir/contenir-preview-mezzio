<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Container;

use Contenir\Preview\Container\InvalidConfiguration;
use Contenir\Preview\Container\PreviewOptions;
use Contenir\Preview\Container\Service;
use Contenir\Preview\Mezzio\PreviewResponder;
use Contenir\Preview\Mezzio\ShareMiddleware;
use Contenir\Preview\Mezzio\ShareResponder;
use Contenir\Preview\Mezzio\TargetMatcher;
use Contenir\Preview\PreviewResponse;
use Contenir\Preview\Share\ShareEntry;
use Mezzio\Router\RouterInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The share middleware, which leaves every request alone while the site has no
 * `contenir_preview.share` configuration, so piping it is always safe.
 */
final class ShareMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidConfiguration
     */
    public function __invoke(ContainerInterface $container): ShareMiddleware
    {
        $options   = Service::get($container, PreviewOptions::class);
        $response  = Service::get($container, PreviewResponse::class);
        $responses = Service::get($container, ResponseFactoryInterface::class);
        $streams   = Service::get($container, StreamFactoryInterface::class);

        return new ShareMiddleware(
            null === $options->share ? null : Service::get($container, ShareEntry::class),
            new TargetMatcher(Service::get($container, RouterInterface::class)),
            new ShareResponder(
                new PreviewResponder($response, $responses, $streams, $options->marker),
                $response,
                $responses,
                $streams,
            ),
        );
    }
}
