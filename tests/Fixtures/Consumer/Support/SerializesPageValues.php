<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Support;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/** Serializes consumer display values without hydrating or validating inputs. */
trait SerializesPageValues
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $properties = [];
        foreach (get_mangled_object_vars($this) as $key => $value) {
            if (is_string($key) && ! str_contains($key, "\0")) {
                $properties[$key] = self::serializeValue($value);
            }
        }

        return $properties;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function serializeValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof Arrayable => self::serializeValue($value->toArray()),
            $value instanceof JsonSerializable => self::serializeValue($value->jsonSerialize()),
            is_array($value) => array_map(self::serializeValue(...), $value),
            default => $value,
        };
    }
}
