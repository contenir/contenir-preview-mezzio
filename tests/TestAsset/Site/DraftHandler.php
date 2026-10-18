<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\TestAsset\Site;

use Contenir\Preview\PreviewContext;
use Laminas\Diactoros\Response\HtmlResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Shows a page not saved yet, which only a preview has values for.
 */
final readonly class DraftHandler implements RequestHandlerInterface
{
    public function __construct(
        private PreviewContext $context,
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse(Page::html($this->context->unsaved('site.page.draft')['title'] ?? null, $request));
    }
}
