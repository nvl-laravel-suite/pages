<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer;

use Illuminate\Foundation\Auth\User;

final class PreviewActor extends User
{
    protected $table = 'page_test_actors';

    protected $guarded = [];
}
