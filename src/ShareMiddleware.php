<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\Share\ShareEntry;
use Contenir\Preview\Share\ShareId;
use Contenir\Preview\Share\ShareOutcome;
use Contenir\Preview\Share\SharePass;
use Contenir\Preview\Share\ShareStoreException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_array;
use function is_string;

/**
 * Answers shared preview links in a Mezzio application (§9 of the protocol). Pipe it before
 * RouteMiddleware, after the middleware that parses a form's body, beside PreviewMiddleware.
 *
 * A visit to `/preview/{id}` is handed to contenir-preview's ShareEntry, and answered as it says:
 * the code form, the form again after a wrong code, or a redirect back to the link with the pass
 * once the code was right. A visitor holding a pass is sent down the pipeline as a visit to the
 * record's page, which the application routes and renders as it would an editor's preview, with
 * the record as it is saved. Every other address is left to the pipeline, as is every address
 * while the site has no `contenir_preview.share` configuration.
 *
 * Every answer is `no-store, private`, which keeps a page cache from storing it, and the request
 * sent down the pipeline carries {@see PreviewMiddleware::REQUEST_CACHE_CONTROL} so no stored copy
 * answers it either.
 *
 * Wrong codes are counted by the address the request came from (`REMOTE_ADDR`): behind a proxy
 * that doesn't pass the visitor's own on, every visitor counts as one.
 *
 * @api
 */
final readonly class ShareMiddleware implements MiddlewareInterface
{
    /**
     * @param ShareEntry|null $entry Null while the site has no shared previews
     */
    public function __construct(
        private ?ShareEntry $entry,
        private TargetMatcher $matcher,
        private ShareResponder $responder,
    ) {}

    /**
     * A text value from a request's cookies, body or server parameters, or null.
     */
    private static function text(mixed $values, string $name): ?string
    {
        /** @var mixed $value */
        $value = is_array($values) ? $values[$name] ?? null : null;

        return is_string($value) ? $value : null;
    }

    /**
     * @throws ShareStoreException When the links cannot be read
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $id   = ShareId::fromPath($path);
        if (null === $this->entry || null === $id) {
            return $handler->handle($request);
        }

        try {
            $visit = $this->entry->open(
                $request->getMethod(),
                $id,
                self::text($request->getCookieParams(), SharePass::COOKIE),
                self::text($request->getParsedBody(), ShareEntry::FIELD),
                self::text($request->getServerParams(), 'REMOTE_ADDR') ?? '',
            );

            if (null !== $visit->target) {
                return $this->responder->page($handler->handle($this->matcher->prepare(
                    $visit->target,
                    $request->withMethod('GET')
                        ->withParsedBody([])
                        ->withHeader('Cache-Control', PreviewMiddleware::REQUEST_CACHE_CONTROL),
                )));
            }

            return match ($visit->outcome) {
                ShareOutcome::AskForCode => $this->responder->codeForm(),
                ShareOutcome::WrongCode  => $this->responder->wrongCodeForm(),
                default                  => $this->responder->admitted($path, (string) $visit->cookie),
            };
        } catch (InvalidPreview $refusal) {
            return $this->responder->refusal($refusal);
        }
    }
}
