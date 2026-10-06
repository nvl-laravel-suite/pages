<?php

declare(strict_types=1);

namespace Nvl\Pages\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Models\PageTranslation;

/**
 * Builds PageTranslation fixture rows and their declared package parents.
 *
 * @extends Factory<PageTranslation>
 *
 * @api
 */
final class PageTranslationFactory extends Factory
{
    protected $model = PageTranslation::class;

    /**
     * Prepare native parent and owner facts after Laravel expands relationships.
     *
     * @internal
     */
    public function configure(): static
    {
        $expandRelationships = true;

        return $this->state(function () use (&$expandRelationships): array {
            $expandRelationships = $this->expandRelationships;

            return [];
        })->afterMaking(function (PageTranslation $model) use (&$expandRelationships): void {
            if (! $expandRelationships) {
                return;
            }

            if ($model->getAttribute('page_id') !== null) {
                $parent = Page::query()->findOrFail(FactoryGuard::identifier($model->getAttribute('page_id')));
                FactoryGuard::parent($parent, $model);
                FactoryGuard::inherit($model, $parent);
            }
        });
    }

    /**
     * Define the fixture's persisted attributes.
     *
     * @return array<model-property<PageTranslation>, mixed>
     */
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'locale' => 'en',
            'title' => $this->faker->sentence(3),
        ];
    }

    /**
     * Associate an admitted persisted Page parent.
     *
     * @api
     */
    public function forPage(Page $parent): static
    {
        FactoryGuard::parent($parent, new PageTranslation);

        return $this->state([
            'page_id' => $parent->getKey(),
        ]);
    }
}
