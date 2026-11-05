<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\TestAsset\Share;

use Contenir\Preview\Mezzio\Tests\TestAsset\Previews;
use Contenir\Preview\Share\Share;
use Contenir\Preview\Share\SharePass;
use DateTimeImmutable;

/**
 * A link for tests, made at the frozen clock's instant and lasting a week, and its pass.
 */
final class Shares
{
    public const string ID = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789-_AbCdE';

    public const string PATH = '/preview/' . self::ID;

    public const string CODE = 'K7M4QX';

    public const int CREATED = 1_792_236_000;

    /**
     * @param array<string, string> $keys
     */
    public static function make(string $section = 'site.page.home', array $keys = ['resource_id' => '101']): Share
    {
        return new Share(
            self::ID,
            self::CODE,
            $section,
            $keys,
            'Jane, Acme',
            '12',
            (new DateTimeImmutable())->setTimestamp(self::CREATED),
            (new DateTimeImmutable())->setTimestamp(self::CREATED + (7 * 86_400)),
        );
    }

    public static function pass(?Share $share = null): string
    {
        return (new SharePass(Previews::config()))->for($share ?? self::make());
    }
}
