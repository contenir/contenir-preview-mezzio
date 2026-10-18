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
 * A site's page: its stored title, with the preview's laid over it, and the route that reached it.
 */
final readonly class PageHandler implements RequestHandlerInterface
{
    public function __construct(
        private PreviewContext $context,
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $page = $this->context->overlay('site.page.home', ['resource_id' => '101'], ['title' => 'Stored title']);

        return new HtmlResponse(Page::html($page['title'] ?? null, $request));
    }
}
