<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\SerializesPageValues;

/**
 * Typed request-independent context supplied to consumer page authorization.
 *
 * @implements Arrayable<string, mixed>
 *
 * @api
 */
final class PageAuthorizationContextData implements Arrayable, JsonSerializable
{
    use SerializesPageValues;

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
