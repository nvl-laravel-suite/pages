<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests;

use Nvl\Metafields\Providers\MetafieldsServiceProvider;

/** Boots Pages without discovering its optional Metafields adapter. */
abstract class StandalonePagesTestCase extends TestCase
{
    /**
     * Return only the required Pages providers.
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return array_values(array_filter(parent::getPackageProviders($app),
            static fn (string $provider): bool => $provider !== MetafieldsServiceProvider::class));
    }
}
