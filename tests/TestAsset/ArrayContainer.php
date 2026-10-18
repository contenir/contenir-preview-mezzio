<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\TestAsset;

use Override;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function array_key_exists;

/**
 * A PSR-11 container over an array of ready services, for testing factories without a container.
 */
final readonly class ArrayContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $services
     */
    public function __construct(
        private array $services,
    ) {}

    #[Override]
    public function get(string $id): mixed
    {
        return array_key_exists($id, $this->services)
            ? $this->services[$id]
            : throw new RuntimeException("No service {$id}.");
    }

    #[Override]
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}
