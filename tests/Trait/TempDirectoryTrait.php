<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\Trait;

use function bin2hex;
use function glob;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

/**
 * A directory of the test's own below the system's temporary one, removed with its files after.
 */
trait TempDirectoryTrait
{
    private string $directory;

    protected function setUpTempDirectory(): void
    {
        $this->directory = sprintf('%s/contenir-preview-mezzio-%s', sys_get_temp_dir(), bin2hex(random_bytes(6)));
        mkdir($this->directory);
    }

    protected function tearDownTempDirectory(): void
    {
        $files = glob("{$this->directory}/*");
        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }
}
