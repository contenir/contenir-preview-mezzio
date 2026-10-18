<?php

declare(strict_types=1);

namespace Contenir\Preview\Mezzio\Tests\TestAsset\Site;

use Contenir\Preview\PreviewRecord;
use Contenir\Preview\Resolver\PreviewResolverInterface;
use Contenir\Preview\Resolver\PreviewTarget;
use Override;

/**
 * Sends every draft to the handler that shows drafts.
 */
final class DraftResolver implements PreviewResolverInterface
{
    #[Override]
    public function resolve(PreviewRecord $record): PreviewTarget
    {
        return PreviewTarget::handler(DraftHandler::class, ['mode' => 'draft']);
    }
}
