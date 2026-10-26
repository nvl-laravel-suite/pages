<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Integrations;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Nvl\Metafields\Models\Metafield;
use Nvl\Metafields\Relations\StringMorphMany;
use Nvl\Metafields\Services\MetafieldReader;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Tests\Fixtures\Consumer\Contracts\PageMetafields;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageMetafieldFieldData;
use Nvl\Support\Enums\SystemActor;

/** Projects the Metafields public read boundary into Pages-owned display types. */
final readonly class MetafieldsPageAdapter implements PageMetafields
{
    /** Create the authorized optional metafield reader. */
    public function __construct(private MetafieldReader $metafields) {}

    /**
     * Preserve the established owner relation through the optional adapter.
     *
     * @return MorphMany<Metafield, Page>
     */
    public static function relation(Page $page): MorphMany
    {
        $related = new Metafield;

        return new StringMorphMany(
            $related->newQuery(),
            $page,
            $related->qualifyColumn('metafieldable_type'),
            $related->qualifyColumn('metafieldable_id'),
            $page->getKeyName(),
        );
    }

    /**
     * Return the same authorized field payload through package-owned display types.
     *
     * @return list<PageMetafieldFieldData>
     */
    public function fields(Page $page, string $locale, Authenticatable|SystemActor $actor): array
    {
        $fields = [];

        foreach ($this->metafields->forOwner($page, $actor, $locale) as $field) {
            $definition = $field['definition'];
            $value = $field['value'];
            $resolved = $value?->getValue($locale) ?? $definition->getDefaultValue($locale);
            $fields[] = new PageMetafieldFieldData(
                definitionId: $definition->id, handle: $definition->handle,
                namespace: $definition->namespace, key: $definition->key,
                type: $definition->type->value, title: $definition->displayTitle($locale),
                description: $definition->displayDescription($locale), hint: $definition->displayHint($locale),
                isTranslatable: $definition->is_translatable, isRequired: $definition->is_required || $field['assignment']->is_required,
                hasStoredValue: $value !== null, usesDefaultValue: $value === null,
                value: $resolved, translations: null,
                jsonPropertySchema: $definition->json_property_schema === null ? null : array_values($definition->json_property_schema),
                defaultValue: $definition->getDefaultValue($locale), displayOrder: $field['assignment']->display_order,
            );
        }

        return $fields;
    }
}
