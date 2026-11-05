<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\PreviewResponse;
use Contenir\Preview\Share\ShareOutcome;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * A shared preview's answers (§9.3 and §9.4 of the protocol): the page the application rendered,
 * marked as a preview's is; the code form, again when the code was wrong; the way back to the link
 * once it was right, with the pass; or the short page that explains a refusal. Each carries the
 * shared preview's headers, which no one may frame, in place of its own.
 *
 * @api
 */
final readonly class ShareResponder
{
    public function __construct(
        private PreviewResponder $previews,
        private PreviewResponse $response,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {}

    /**
     * Sends the visitor back to the link they entered the code at, with their pass.
     *
     * @param string $path   The link's path
     * @param string $cookie The pass's `Set-Cookie` header
     */
    public function admitted(string $path, string $cookie): ResponseInterface
    {
        return $this->withShareHeaders(
            $this->responses
                ->createResponse(ShareOutcome::Admitted->status())
                ->withHeader('Location', $path)
                ->withHeader('Set-Cookie', $cookie),
        );
    }

    public function codeForm(): ResponseInterface
    {
        return $this->html(ShareOutcome::AskForCode->status(), $this->response->codeForm());
    }

    public function page(ResponseInterface $page): ResponseInterface
    {
        return $this->withShareHeaders($this->previews->page($page));
    }

    public function refusal(InvalidPreview $refusal): ResponseInterface
    {
        return $this->withShareHeaders($this->previews->refusal($refusal));
    }

    public function wrongCodeForm(): ResponseInterface
    {
        return $this->html(ShareOutcome::WrongCode->status(), $this->response->wrongCodeForm());
    }

    private function html(int $status, string $page): ResponseInterface
    {
        return $this->withShareHeaders(
            $this->responses
                ->createResponse($status)
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withBody($this->streams->createStream($page)),
        );
    }

    private function withShareHeaders(ResponseInterface $response): ResponseInterface
    {
        foreach ($this->response->shareHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
