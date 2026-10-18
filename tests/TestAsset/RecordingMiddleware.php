<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\TestAsset;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A middleware that remembers the request it was handed and passes it on.
 */
final class RecordingMiddleware implements MiddlewareInterface
{
    public ?ServerRequestInterface $processed = null;

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->processed = $request;

        return $handler->handle($request);
    }
}
