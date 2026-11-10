<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\SerializesPageValues;

/**
 * Sanitized transport projection returned by a dynamic resource handler.
 *
 * @implements Arrayable<string, mixed>
 *
 * @api
 */
final class PageResourceData implements Arrayable, JsonSerializable
{
    use SerializesPageValues;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly array $payload,
    ) {}
}
