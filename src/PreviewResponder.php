<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewMarker;
use Contenir\Preview\PreviewResponse;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function str_contains;

/**
 * A preview's answer (§6, step 4): the page the application rendered, or the short page that
 * explains a refusal, with the protocol's headers in place of the page's own and, on an HTML
 * page unless configured away, the visible "Preview" badge.
 *
 * @api
 */
final readonly class PreviewResponder
{
    public function __construct(
        private PreviewResponse $response,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
        private bool $marker,
    ) {}

    public function page(ResponseInterface $page): ResponseInterface
    {
        $page = $this->withPreviewHeaders($page);
        $type = $page->getHeaderLine('Content-Type');
        if (! $this->marker || ('' !== $type && ! str_contains($type, 'html'))) {
            return $page;
        }

        return $page->withoutHeader('Content-Length')
            ->withBody($this->streams->createStream(PreviewMarker::mark((string) $page->getBody())));
    }

    public function refusal(InvalidPreview $refusal): ResponseInterface
    {
        return $this->withPreviewHeaders(
            $this->responses
                ->createResponse($refusal->status)
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withBody($this->streams->createStream($this->response->refusal($refusal))),
        );
    }

    private function withPreviewHeaders(ResponseInterface $response): ResponseInterface
    {
        foreach ($this->response->headers() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
