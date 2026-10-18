<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\TestAsset\Site;

use Mezzio\Router\RouteResult;
use Psr\Http\Message\ServerRequestInterface;

use function htmlspecialchars;
use function is_string;
use function sprintf;

/**
 * The test site's page: a title, the route that reached it and its route parameter "mode".
 */
final class Page
{
    public static function html(mixed $title, ServerRequestInterface $request): string
    {
        /** @var mixed $result */
        $result = $request->getAttribute(RouteResult::class);
        /** @var mixed $mode */
        $mode = $request->getAttribute('mode');

        return sprintf(
            '<html><body><h1>%s</h1><p>route %s, mode %s</p></body></html>',
            htmlspecialchars(is_string($title) ? $title : 'Untitled'),
            $result instanceof RouteResult ? (string) $result->getMatchedRouteName() : 'none',
            is_string($mode) ? $mode : 'none',
        );
    }
}
