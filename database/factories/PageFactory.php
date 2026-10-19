<?php

declare(strict_types=1);

namespace Nvl\Pages\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\PageModelRegistry;
use Nvl\Support\Ownership\Contracts\OwnershipWriter;

/**
 * Builds Page fixture rows and their declared package parents.
 *
 * @extends Factory<Page>
 *
 * @api
 */
final class PageFactory extends Factory
{
    /** Resolve the one configured concrete Page model. */
    public function modelName(): string
    {
        return PageModelRegistry::modelClass();
    }

    /** Persist native factory roots under the same actual source assignment as Actions.
     * @param  Collection<int,Page>  $results
     */
    protected function store(Collection $results): void
    {
        if (PageModelRegistry::modelClass()::ownershipDefinition()->strategy === 'unscoped') {
            parent::store($results);

            return;
        }
        foreach ($results as $model) {
            app(OwnershipWriter::class)->create($model::class, function (array $attributes) use ($model): Page {
                $model->forceFill($attributes);
                parent::store(new Collection([$model]));

                return $model;
            });
        }
    }

    protected $model = Page::class;

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
        })->afterMaking(function (Page $model) use (&$expandRelationships): void {
            if (! $expandRelationships) {
                return;
            }

            FactoryGuard::root($model, 'pages.pages');
        });
    }

    /**
     * Define the fixture's persisted attributes.
     *
     * @return array<model-property<Page>, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(3),
            'site' => 'default',
            'slug' => $this->faker->unique()->slug(3),
            'path' => function (array $attributes): string {
                $slug = $attributes['slug'];
                if (! is_string($slug)) {
                    throw new InvalidArgumentException('Page fixture paths require a native string slug.');
                }

                return '/'.$slug;
            },
            'kind' => 'static',
            'status' => 'draft',
            'revision' => 1,
        ];
    }
}
