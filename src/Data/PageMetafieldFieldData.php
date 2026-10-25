<?php

declare(strict_types=1);

namespace Nvl\Pages\Data;

use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Pages-owned display projection of an optional owner metafield.
 *
 * @api
 */
#[MapOutputName(CamelCaseMapper::class)]
#[MapInputName(CamelCaseMapper::class)]
final class PageMetafieldFieldData extends Data
{
    use DataTransform;

    /**
     * @param  array<string, mixed>|null  $translations
     * @param  list<array<string, mixed>>|null  $jsonPropertySchema
     */
    public function __construct(
        public readonly string $definitionId,
        public readonly string $handle,
        public readonly string $namespace,
        public readonly string $key,
        public readonly string $type,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $hint,
        public readonly bool $isTranslatable,
        public readonly bool $isRequired,
        public readonly bool $hasStoredValue,
        public readonly bool $usesDefaultValue,
        public readonly mixed $value,
        public readonly ?array $translations,
        public readonly ?array $jsonPropertySchema,
        public readonly mixed $defaultValue,
        public readonly int $displayOrder,
    ) {}
}
