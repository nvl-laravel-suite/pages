<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Http\Request;
use Nvl\Ownership\Contracts\OwnerSiteResolver;
use Nvl\Ownership\Services\OwnerIdentityResolver;
use Nvl\Ownership\Services\PublicOwnerSiteRunner;
use Nvl\Pages\Actions\CreatePageAction;
use Nvl\Pages\Data\Mutations\CreatePageData;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Enums\PageStatus;
use Nvl\Pages\Tests\Fixtures\OwnershipCompany;
use Nvl\Seo\Services\SitemapGenerator;
use Nvl\Support\Ownership\ValueObjects\VerifiedOwnerSiteContext;

beforeEach(function (): void {
    $this->app->bind(OwnerSiteResolver::class, static fn () => new class implements OwnerSiteResolver
    {
        public function resolve(Request $request): ?VerifiedOwnerSiteContext
        {
            if (! in_array($request->getHost(), ['a.pages.test', 'b.pages.test'], true)) {
                return null;
            }

            return new VerifiedOwnerSiteContext(app(OwnerIdentityResolver::class)->reference(OwnershipCompany::findOrFail($request->getHost() === 'a.pages.test' ? 7 : 8)),
                'default', 'https://'.$request->getHost(), ['pages.pages', 'pages.translations', 'content.blocks', 'content.definitions', 'content.translations', 'content.placements', 'content.revisions', 'seo.profiles', 'seo.translations', 'seo.redirects']);
        }
    });
    foreach ([7, 8] as $id) {
        $this->owned($id, fn () => app(CreatePageAction::class)->execute(new CreatePageData(key: 'about', slug: 'about', status: PageStatus::Published, translations: ['en' => ['title' => 'Owner '.$id]]), PageActorData::system()));
    }
});

it('resolves the public host before page composition and ignores caller site and owner selectors', function (): void {
    $this->app->instance(MaintenanceMode::class, new class implements MaintenanceMode
    {
        public function activate(array $payload): void {}

        public function deactivate(): void {}

        public function active(): bool
        {
            return false;
        }

        public function data(): array
        {
            return [];
        }
    });
    $configuration = config()->all();
    foreach (['a' => 7, 'b' => 8] as $host => $id) {
        $this->withHeaders(['X-Owner-Id' => '8', 'X-Site' => 'foreign', 'X-Canonical-Origin' => 'https://foreign.test'])
            ->getJson('https://'.$host.'.pages.test/nvl/api/v1/pages/about?site=foreign&owner=8')
            ->assertOk()->assertJsonPath('data.page.title', 'Owner '.$id)
            ->assertJsonPath('data.page.url', 'https://'.$host.'.pages.test/about');
    }
    $this->getJson('https://unknown.pages.test/nvl/api/v1/pages/about')->assertNotFound();
    expect(config()->all())->toBe($configuration);
});

it('publishes only the verified site pages into the real discovery artifact', function (): void {
    foreach (['a', 'b'] as $host) {
        app(PublicOwnerSiteRunner::class)->run(Request::create('https://'.$host.'.pages.test'), function () use ($host): void {
            expect(app(SitemapGenerator::class)->generate())->toContain('https://'.$host.'.pages.test/about')
                ->not->toContain('https://'.($host === 'a' ? 'b' : 'a').'.pages.test');
        });
    }
});
