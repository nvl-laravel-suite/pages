<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data\Queries;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Validated site and locale boundary for page previews.
 */
#[MapInputName(CamelCaseMapper::class)]
#[MapOutputName(CamelCaseMapper::class)]
final class PagePreviewQueryData extends Data
{
    /**
     * Create a page preview query.
     */
    public function __construct(
        public readonly string $site,
        public readonly string $locale,
    ) {}

    /**
     * Return page preview query validation rules.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'site' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
            'locale' => ['required', 'string', 'max:35'],
        ];
    }
}
