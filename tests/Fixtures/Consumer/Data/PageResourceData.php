<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Sanitized transport projection returned by a dynamic resource handler.
 *
 * @api
 */
#[MapOutputName(CamelCaseMapper::class)]
final class PageResourceData extends Data
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly array $payload,
    ) {}
}
