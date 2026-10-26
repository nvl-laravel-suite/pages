<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Soft-delete contract protected by an exact page revision.
 *
 * @api
 */
#[MapInputName(CamelCaseMapper::class)]
#[MapOutputName(CamelCaseMapper::class)]
final class DeletePageData extends Data
{
    /**
     * Create one validated page deletion payload.
     */
    public function __construct(
        #[MapInputName('expected_revision')] #[MapOutputName('expected_revision')] public readonly int $expectedRevision,
    ) {}

    /**
     * Return the validation rules for a page deletion.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'expected_revision' => ['required', 'integer', 'min:1'],
        ];
    }
}
