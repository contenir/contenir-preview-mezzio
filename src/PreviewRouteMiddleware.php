<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio;

use Contenir\Preview\PreviewConfig;
use Contenir\Preview\Resolver\PreviewTarget;
use Mezzio\Exception\InvalidMiddlewareException;
use Mezzio\MiddlewareFactoryInterface;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The application's RouteMiddleware, which also routes a preview whose resolver named a handler:
 * the request is given a route of its own at the preview endpoint, dispatching that handler with
 * the target's parameters as route parameters, just as RouteMiddleware gives a matched route's.
 * Every other request is routed by the application's RouteMiddleware.
 *
 * @api
 */
final readonly class PreviewRouteMiddleware implements MiddlewareInterface
{
    /** The name of the route a handler is dispatched through */
    public const string HANDLER_ROUTE = 'contenir-preview';

    public function __construct(
        private MiddlewareInterface $routing,
        private MiddlewareFactoryInterface $middleware,
    ) {}

    /**
     * @throws InvalidMiddlewareException When the handler is no middleware or request handler service
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var mixed $target */
        $target = $request->getAttribute(TargetMatcher::HANDLER_ATTRIBUTE);
        if (! $target instanceof PreviewTarget || null === $target->handler) {
            return $this->routing->process($request, $handler);
        }

        $route = new Route(
            PreviewConfig::ENDPOINT,
            $this->middleware->prepare($target->handler),
            ['GET'],
            self::HANDLER_ROUTE,
        );
        $request = $request->withAttribute(RouteResult::class, RouteResult::fromRoute($route, $target->params));
        /** @var mixed $value */
        foreach ($target->params as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        return $handler->handle($request);
    }
}
