<?php

declare(strict_types=1);

namespace Nvl\Pages\Integrations;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Nvl\Metafields\Actions\Metafields\ListAuthorizedOwnerMetafieldsAction;
use Nvl\Metafields\Models\Metafield;
use Nvl\Metafields\Relations\StringMorphMany;
use Nvl\Pages\Contracts\PageMetafields;
use Nvl\Pages\Data\PageMetafieldFieldData;
use Nvl\Pages\Models\Page;

/** Projects the Metafields public read boundary into Pages-owned display types. */
final readonly class MetafieldsPageAdapter implements PageMetafields
{
    /** Create the authorized optional metafield reader. */
    public function __construct(private ListAuthorizedOwnerMetafieldsAction $metafields) {}

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
    public function fields(Page $page, string $locale): array
    {
        $fields = [];

        foreach ($this->metafields->execute($page, $locale) as $field) {
            $fields[] = PageMetafieldFieldData::from($field->toArray());
        }

        return $fields;
    }
}
