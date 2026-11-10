<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\SerializesPageValues;

/**
 * One localized public navigation node and its visible children.
 *
 * @implements Arrayable<string, mixed>
 *
 * @api
 */
final class NavigationItemData implements Arrayable, JsonSerializable
{
    use SerializesPageValues;

    /**
     * Create one localized navigation node.
     *
     * @param  list<NavigationItemData>  $children
     */
    public function __construct(
        public readonly string $id,
        public readonly string $key,
        public readonly string $path,
        public readonly string $url,
        public readonly string $label,
        public readonly array $children,
    ) {}

    /** @param array<string, mixed> $row A navigation node with recursively shaped child maps. */
    public static function fromRow(array $row): self
    {
        $id = $row['id'] ?? null;
        $key = $row['key'] ?? null;
        $path = $row['path'] ?? null;
        $url = $row['url'] ?? null;
        $label = $row['label'] ?? null;
        $children = $row['children'] ?? null;
        if (! is_string($id) || ! is_string($key) || ! is_string($path)
            || ! is_string($url) || ! is_string($label) || ! is_array($children) || ! array_is_list($children)) {
            throw new InvalidArgumentException('Navigation rows require string identities and a child list.');
        }

        $items = [];
        foreach ($children as $child) {
            if (! is_array($child)) {
                throw new InvalidArgumentException('Navigation children must be maps.');
            }
            $values = [];
            foreach ($child as $field => $value) {
                if (! is_string($field)) {
                    throw new InvalidArgumentException('Navigation child fields must have string keys.');
                }
                $values[$field] = $value;
            }
            $items[] = self::fromRow($values);
        }

        return new self(
            id: $id, key: $key, path: $path, url: $url, label: $label,
            children: $items,
        );
    }
}
