<?php

declare(strict_types=1);

namespace Nvl\Pages\Data;

use Nvl\Data\Traits\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Pages-owned display projection of an optional owner metafield.
 *
 * @api
 */
#[MapOutputName(CamelCaseMapper::class)]
#[MapInputName(CamelCaseMapper::class)]
#[TypeScript]
final class PageMetafieldFieldData extends Data
{
    use DataTransform;

    /**
     * @param  array<string, mixed>|null  $translations
     * @param  list<array<string, mixed>>|null  $jsonPropertySchema
     */
    public function __construct(
        #[LiteralTypeScriptType('string')]
        public readonly string $definitionId,
        #[LiteralTypeScriptType('string')]
        public readonly string $handle,
        #[LiteralTypeScriptType('string')]
        public readonly string $namespace,
        #[LiteralTypeScriptType('string')]
        public readonly string $key,
        public readonly string $type,
        #[LiteralTypeScriptType('string')]
        public readonly string $title,
        #[LiteralTypeScriptType('string | null')]
        public readonly ?string $description,
        #[LiteralTypeScriptType('string | null')]
        public readonly ?string $hint,
        #[LiteralTypeScriptType('boolean')]
        public readonly bool $isTranslatable,
        #[LiteralTypeScriptType('boolean')]
        public readonly bool $isRequired,
        #[LiteralTypeScriptType('boolean')]
        public readonly bool $hasStoredValue,
        #[LiteralTypeScriptType('boolean')]
        public readonly bool $usesDefaultValue,
        #[LiteralTypeScriptType('unknown | null')]
        public readonly mixed $value,
        #[LiteralTypeScriptType('Record<string, unknown> | null')]
        public readonly ?array $translations,
        #[LiteralTypeScriptType('Array<Record<string, unknown>> | null')]
        public readonly ?array $jsonPropertySchema,
        #[LiteralTypeScriptType('unknown | null')]
        public readonly mixed $defaultValue,
        #[LiteralTypeScriptType('number')]
        public readonly int $displayOrder,
    ) {}
}
