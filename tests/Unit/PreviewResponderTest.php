<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\Unit;

use Contenir\Preview\InvalidPreview;
use Contenir\Preview\Mezzio\PreviewResponder;
use Contenir\Preview\Mezzio\Tests\TestAsset\Previews;
use Contenir\Preview\PreviewMarker;
use Contenir\Preview\PreviewResponse;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function str_contains;

/**
 * A preview's answer: the rendered page or a refusal, with the protocol's headers and the badge.
 */
#[Group('unit')]
final class PreviewResponderTest extends TestCase
{
    #[Test]
    public function addsThePreviewHeadersInPlaceOfThePagesOwn(): void
    {
        $page = (new HtmlResponse('<html><body>Spa Rituals</body></html>'))->withHeader('Content-Language', 'en')
            ->withHeader('content-security-policy', "frame-ancestors 'none'");

        static::assertSame(
            [
                'content-type'            => ['text/html; charset=utf-8'],
                'Content-Language'        => ['en'],
                'Cache-Control'           => ['no-store, private'],
                'X-Robots-Tag'            => ['noindex, nofollow'],
                'Referrer-Policy'         => ['no-referrer'],
                'Content-Security-Policy' => ['frame-ancestors ' . Previews::ISSUER],
            ],
            $this->responder()->page($page)->getHeaders(),
        );
    }

    #[Test]
    public function answersARefusalWithItsStatusAndAPageSayingWhy(): void
    {
        $response = $this->responder()->refusal(InvalidPreview::wrongMethod());

        static::assertSame(
            [405, 'text/html; charset=utf-8', 'no-store, private', true],
            [
                $response->getStatusCode(),
                $response->getHeaderLine('Content-Type'),
                $response->getHeaderLine('Cache-Control'),
                str_contains((string) $response->getBody(), '<p>A preview is posted, not fetched.</p>'),
            ],
        );
    }

    #[Test]
    public function dropsTheLengthOfThePageItMarks(): void
    {
        $page = $this->responder()->page(
            (new HtmlResponse('<html><body>Spa Rituals</body></html>'))->withHeader('Content-Length', '37'),
        );

        static::assertFalse($page->hasHeader('Content-Length'));
    }

    #[Test]
    public function keepsTheLengthOfAPageLeftUnmarked(): void
    {
        $page = $this->responder(marker: false)->page(
            (new HtmlResponse('<html><body>Spa Rituals</body></html>'))->withHeader('Content-Length', '37'),
        );

        static::assertSame('37', $page->getHeaderLine('Content-Length'));
    }

    #[Test]
    public function leavesAnAnswerThatIsNotHtmlUnmarked(): void
    {
        $page = $this->responder()->page(new TextResponse('Spa Rituals</body>'));

        static::assertSame('Spa Rituals</body>', (string) $page->getBody());
    }

    #[Test]
    public function leavesTheBadgeOffWhenConfiguredTo(): void
    {
        $page = $this->responder(marker: false)->page(new HtmlResponse('<html><body>Spa Rituals</body></html>'));

        static::assertSame('<html><body>Spa Rituals</body></html>', (string) $page->getBody());
    }

    #[Test]
    public function marksAnHtmlPage(): void
    {
        $page = $this->responder()->page(new HtmlResponse('<html><body>Spa Rituals</body></html>'));

        static::assertSame(
            '<html><body>Spa Rituals' . PreviewMarker::HTML . '</body></html>',
            (string) $page->getBody(),
        );
    }

    #[Test]
    public function marksAPageThatDoesNotSayWhatItIs(): void
    {
        $page = $this->responder()->page($this->untyped('<html><body>Spa Rituals</body></html>'));

        static::assertSame(
            '<html><body>Spa Rituals' . PreviewMarker::HTML . '</body></html>',
            (string) $page->getBody(),
        );
    }

    private function responder(bool $marker = true): PreviewResponder
    {
        return new PreviewResponder(
            new PreviewResponse(Previews::config()),
            new ResponseFactory(),
            new StreamFactory(),
            $marker,
        );
    }

    private function untyped(string $html): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write($html);

        return $response;
    }
}
