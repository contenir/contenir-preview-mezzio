<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\TestAsset;

use Laminas\Diactoros\Response\HtmlResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The rest of the pipeline: remembers the request it was handed and answers with a set response.
 */
final class RecordingHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $handled = null;

    public function __construct(
        private readonly ResponseInterface $response = new HtmlResponse('<html><body>Spa Rituals</body></html>'),
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->handled = $request;

        return $this->response;
    }
}
