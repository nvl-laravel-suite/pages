<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Reparents and reorders one page with optimistic concurrency.
 *
 * @api
 */
#[MapInputName(CamelCaseMapper::class)]
#[MapOutputName(CamelCaseMapper::class)]
final class MovePageData extends Data
{
    /**
     * Create one validated page move payload.
     */
    public function __construct(
        public readonly ?string $parentId,
        public readonly int $position,
        #[MapInputName('expected_revision')] #[MapOutputName('expected_revision')] public readonly int $expectedRevision,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'parentId' => ['nullable', 'uuid'],
            'position' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ];
    }
}
