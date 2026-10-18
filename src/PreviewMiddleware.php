<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewConfig;
use Contenir\Preview\PreviewEntry;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_array;
use function is_string;

/**
 * Answers the CMS's previews in a Mezzio application (§4 to §6 of the protocol). Pipe it before
 * RouteMiddleware.
 *
 * A post to /api/resource/preview is opened: its token verified, the page that shows the record
 * resolved, and the preview context begun. The request then goes down the pipeline as a plain
 * visit to that page, which the application routes and renders as it always would, while its data
 * layer lays the preview's values over the record. A refusal is answered at once with the
 * protocol's status and a short page saying why. A preview's response gets the protocol's headers
 * and, unless configured away, a visible "Preview" badge.
 *
 * A page cache that keys on a GET to a path would otherwise see the preview as a visit to the
 * page, and could serve the stored page in its place or store the editor's unsaved page for
 * visitors, so the request carries {@see self::REQUEST_CACHE_CONTROL}, the HTTP way of saying
 * neither serve nor store this.
 *
 * @api
 */
final readonly class PreviewMiddleware implements MiddlewareInterface
{
    /**
     * The request's Cache-Control once a preview opens: no stored copy may answer it (RFC 9111
     * §5.2.1.4), and no part of its answer may be stored (§5.2.1.5).
     */
    public const string REQUEST_CACHE_CONTROL = 'no-cache, no-store';

    public function __construct(
        private PreviewEntry $entry,
        private TargetMatcher $matcher,
        private PreviewResponder $responder,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (PreviewConfig::ENDPOINT !== $request->getUri()->getPath()) {
            return $handler->handle($request);
        }

        try {
            $target  = $this->entry->open($request->getMethod(), $this->token($request));
            $request = $this->matcher->prepare(
                $target,
                $request->withMethod('GET')
                    ->withParsedBody([])
                    ->withHeader('Cache-Control', self::REQUEST_CACHE_CONTROL),
            );
        } catch (InvalidPreview $refusal) {
            return $this->responder->refusal($refusal);
        }

        return $this->responder->page($handler->handle($request));
    }

    private function token(ServerRequestInterface $request): ?string
    {
        $body = $request->getParsedBody();
        /** @var mixed $token */
        $token = is_array($body) ? $body[PreviewEntry::FIELD] ?? null : null;

        return is_string($token) ? $token : null;
    }
}
