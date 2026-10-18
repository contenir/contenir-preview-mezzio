<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\Resolver\PreviewTarget;
use Mezzio\Router\Exception\ExceptionInterface as RouterException;
use Mezzio\Router\RouterInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Sets the request up so the application routes it to the page a resolver names, as it would a
 * visit, and every middleware after the router sees the page's route. A path, or a named route
 * generated to one, becomes the request's address, so the page sees its own URL; the router must
 * have a page there. A handler, which no route reaches, is carried on the request for
 * {@see PreviewRouteMiddleware} to dispatch with its parameters.
 *
 * @api
 */
final readonly class TargetMatcher
{
    /** The request attribute that carries a handler target past the router */
    public const string HANDLER_ATTRIBUTE = PreviewTarget::class;

    public function __construct(
        private RouterInterface $router,
    ) {}

    /**
     * @throws InvalidPreview When the site has no page there
     */
    public function prepare(PreviewTarget $target, ServerRequestInterface $request): ServerRequestInterface
    {
        if ($target->isHandler()) {
            return $request->withAttribute(self::HANDLER_ATTRIBUTE, $target);
        }

        $request = $request->withUri($request->getUri()->withPath($this->path($target)));
        if ($this->router->match($request)->isFailure()) {
            throw InvalidPreview::notPreviewable('The site has no page at this record\'s address.');
        }

        return $request;
    }

    /**
     * @throws InvalidPreview
     */
    private function path(PreviewTarget $target): string
    {
        if (null !== $target->path) {
            return $target->path;
        }

        try {
            return $this->router->generateUri((string) $target->route, $target->params);
        } catch (RouterException) {
            throw InvalidPreview::notPreviewable('The site has no route for this record.');
        }
    }
}
