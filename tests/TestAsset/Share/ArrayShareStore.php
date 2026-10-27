<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\TestAsset\Share;

use Contenir\Preview\Share\Share;
use Contenir\Preview\Share\ShareStoreInterface;
use DateTimeImmutable;
use Override;

use function array_key_exists;
use function array_values;

/**
 * Links in an array, as a site finds them, for testing the middleware without a database. A site
 * only finds links, so the CMS's writes do nothing here.
 */
final class ArrayShareStore implements ShareStoreInterface
{
    /** @var array<string, Share> */
    private array $shares = [];

    public function __construct(Share ...$shares)
    {
        foreach ($shares as $share) {
            $this->shares[$share->id] = $share;
        }
    }

    #[Override]
    public function add(Share $share): void
    {
        $this->shares[$share->id] = $share;
    }

    #[Override]
    public function delete(string $id): bool
    {
        $found = array_key_exists($id, $this->shares);
        unset($this->shares[$id]);

        return $found;
    }

    #[Override]
    public function deleteExpired(DateTimeImmutable $now): int
    {
        return 0;
    }

    #[Override]
    public function deleteForRecord(string $section, array $keys): int
    {
        return 0;
    }

    #[Override]
    public function find(string $id): ?Share
    {
        return $this->shares[$id] ?? null;
    }

    #[Override]
    public function forRecord(string $section, array $keys): array
    {
        return array_values($this->shares);
    }

    #[Override]
    public function replaceCode(string $id, string $code): bool
    {
        return false;
    }
}
