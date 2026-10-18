<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Container;

use Contenir\Preview\Container\InvalidConfiguration;
use Contenir\Preview\Container\Service;
use Contenir\Preview\Mezzio\PreviewRouteMiddleware;
use Mezzio\MiddlewareFactoryInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Wraps the application's RouteMiddleware so it also routes a preview's handler.
 */
final class PreviewRouteMiddlewareDelegator
{
    /**
     * @param callable(): mixed $callback
     * @throws ContainerExceptionInterface
     * @throws InvalidConfiguration When the route middleware is not a middleware
     */
    public function __invoke(ContainerInterface $container, string $name, callable $callback): PreviewRouteMiddleware
    {
        /** @var mixed $routing */
        $routing = $callback();

        return new PreviewRouteMiddleware(
            $routing instanceof MiddlewareInterface
                ? $routing
                : throw new InvalidConfiguration("The service {$name} is not a middleware."),
            Service::get($container, MiddlewareFactoryInterface::class),
        );
    }
}
