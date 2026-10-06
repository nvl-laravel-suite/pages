<?php

declare(strict_types=1);

namespace Nvl\Pages\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;
use Nvl\Pages\Models\Page;

/**
 * Builds Page fixture rows and their declared package parents.
 *
 * @extends Factory<Page>
 *
 * @api
 */
final class PageFactory extends Factory
{
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
