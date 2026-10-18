<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Container;

use Contenir\Preview\Container\InvalidConfiguration;
use Contenir\Preview\Container\PreviewOptions;
use Contenir\Preview\Container\Service;
use Contenir\Preview\Mezzio\PreviewMiddleware;
use Contenir\Preview\Mezzio\PreviewResponder;
use Contenir\Preview\Mezzio\TargetMatcher;
use Contenir\Preview\PreviewEntry;
use Contenir\Preview\PreviewResponse;
use Mezzio\Router\RouterInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class PreviewMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidConfiguration
     */
    public function __invoke(ContainerInterface $container): PreviewMiddleware
    {
        return new PreviewMiddleware(
            Service::get($container, PreviewEntry::class),
            new TargetMatcher(Service::get($container, RouterInterface::class)),
            new PreviewResponder(
                Service::get($container, PreviewResponse::class),
                Service::get($container, ResponseFactoryInterface::class),
                Service::get($container, StreamFactoryInterface::class),
                Service::get($container, PreviewOptions::class)->marker,
            ),
        );
    }
}
