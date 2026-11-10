<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\SerializesPageValues;

/**
 * Consumer-safe result for a globally unique Page key availability check.
 *
 * @implements Arrayable<string, mixed>
 *
 * @api
 */
final class PageKeyAvailabilityData implements Arrayable, JsonSerializable
{
    use SerializesPageValues;

    /**
     * Create one Page key availability result.
     */
    public function __construct(
        public readonly string $site,
        public readonly string $key,
        public readonly bool $available,
        public readonly ?string $conflictingPageId,
    ) {}

    /** @param array{site: string, key: string, available: bool, conflictingPageId: string|null} $row */
    public static function fromRow(array $row): self
    {
        return new self($row['site'], $row['key'], $row['available'], $row['conflictingPageId']);
    }
}
