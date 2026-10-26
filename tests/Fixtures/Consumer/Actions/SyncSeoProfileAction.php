<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Actions;

use Illuminate\Database\Eloquent\Model;
use Nvl\Seo\Models\SeoProfile;
use Nvl\Seo\Services\SeoProfiles;
use Nvl\Support\Enums\SystemActor;

final readonly class SyncSeoProfileAction
{
    public function __construct(private SeoProfiles $profiles) {}

    public function execute(Model $owner, array $payload, ?string $scope = null): SeoProfile
    {
        return $this->profiles->sync(SystemActor::Background, $owner, $payload, $scope);
    }
}
