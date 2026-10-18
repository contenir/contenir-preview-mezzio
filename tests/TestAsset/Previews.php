<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\TestAsset;

use Contenir\Preview\PreviewConfig;
use Contenir\Preview\PreviewRecord;
use Contenir\Preview\PreviewRequest;
use Contenir\Preview\Token\TokenSigner;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * The shared configuration a test site and its CMS agree on, and tokens signed with it.
 */
final class Previews
{
    public const string SHARED = 'a-secret-of-at-least-thirty-two-bytes!!';

    public const string ISSUER = 'https://admin.example.test';

    public const string AUDIENCE = 'https://www.example.test';

    /**
     * The application's `contenir_preview` configuration, with any of its settings replaced.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public static function appConfig(array $settings = []): array
    {
        return [
            'contenir_preview' => [
                'issuer'    => self::ISSUER,
                'audience'  => self::AUDIENCE,
                'secrets'   => ['2026-10' => self::SHARED],
                'sign_with' => '2026-10',
                ...$settings,
            ],
        ];
    }

    public static function config(): PreviewConfig
    {
        return new PreviewConfig(self::ISSUER, self::AUDIENCE, ['2026-10' => self::SHARED], '2026-10');
    }

    /**
     * @param array<string, string>                      $keys
     * @param array<string, string|list<string>|null> $values
     */
    public static function record(
        string $section = 'site.page.home',
        array $keys = ['resource_id' => '101'],
        array $values = ['slug' => 'spa-rituals', 'title' => 'Edited title'],
    ): PreviewRecord {
        return new PreviewRecord($section, $keys, $values);
    }

    public static function request(?PreviewRecord $record = null): PreviewRequest
    {
        $now = new DateTimeImmutable('@1792236000');

        return new PreviewRequest($record ?? self::record(), '12', '0123456789abcdef0123456789abcdef', $now, $now);
    }

    public static function token(ClockInterface $clock, ?PreviewRecord $record = null): string
    {
        return (new TokenSigner(self::config(), $clock))->sign($record ?? self::record(), '12');
    }
}
