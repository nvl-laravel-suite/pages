<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Nvl\Metafields\Contracts\MetafieldAuthorization;
use Nvl\Metafields\Enums\MetafieldAbility;
use Nvl\Metafields\Models\MetafieldDefinition;
use Nvl\Pages\Actions\CreatePageAction;
use Nvl\Pages\Actions\GetPageEditorBootstrapAction;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Data\Mutations\CreatePageData;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Tests\Fixtures\RecordingPageAuthorization;
use Nvl\Seo\Contracts\SeoAuthorization;
use Nvl\Seo\Support\SeoAuthorizationContext;

it('fails the page editor bootstrap when the Metafields authorization boundary denies', function (): void {
    $page = app(CreatePageAction::class)->execute(
        new CreatePageData('pages.editor-denied', 'editor-denied'),
        PageActorData::system(),
    );
    $actor = new PageActorData('user', 'editor-user');
    app()->instance(PageAuthorization::class, new RecordingPageAuthorization);
    app()->instance(SeoAuthorization::class, new class implements SeoAuthorization
    {
        public function authorize(SeoAuthorizationContext $context): void {}
    });
    app()->instance(MetafieldAuthorization::class, new class implements MetafieldAuthorization
    {
        public function authorizeDefinition(
            MetafieldAbility $ability,
            ?MetafieldDefinition $definition = null,
        ): void {}

        public function authorizeOwner(
            MetafieldAbility $ability,
            ?Model $owner = null,
            ?MetafieldDefinition $definition = null,
        ): void {
            throw new AuthorizationException;
        }
    });

    expect(fn () => app(GetPageEditorBootstrapAction::class)->execute(
        $page->id,
        'en',
        $actor,
    ))->toThrow(AuthorizationException::class);
});
