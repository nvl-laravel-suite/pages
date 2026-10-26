<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Spatie\LaravelData\Data;

/**
 * Typed request-independent context supplied to consumer page authorization.
 *
 * @api
 */
final class PageAuthorizationContextData extends Data
{
    /**
     * Create typed domain context for one authorization decision.
     */
    public function __construct(
        public readonly ?string $site = null,
        public readonly ?string $parentId = null,
        public readonly ?string $path = null,
        public readonly ?string $locale = null,
        public readonly ?string $resourceId = null,
    ) {}
}
