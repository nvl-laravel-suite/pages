<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data\Queries;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Validated site and pagination boundary for page management listings.
 */
#[MapInputName(CamelCaseMapper::class)]
#[MapOutputName(CamelCaseMapper::class)]
final class PageIndexQueryData extends Data
{
    /**
     * Create a bounded page management query.
     */
    public function __construct(
        public readonly string $site,
        #[MapInputName('per_page')] #[MapOutputName('per_page')] public readonly int $perPage = 25,
    ) {}

    /**
     * Return page management query validation rules.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'site' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
