<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use Nvl\Pages\Tests\Fixtures\Consumer\PreviewActor;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\SerializesPageValues;
use Nvl\Support\Enums\SystemActor;

/**
 * Transport-neutral actor identity for authorization and event context.
 *
 * @implements Arrayable<string, mixed>
 *
 * @api
 */
final class PageActorData implements Arrayable, JsonSerializable
{
    use SerializesPageValues;

    private ?Authenticatable $authenticated = null;

    /**
     * Create an actor identity for server-side authorization.
     */
    public function __construct(
        public readonly ?string $type,
        public readonly int|string|null $id,
        public readonly bool $system = false,
    ) {}

    /**
     * Return an anonymous public actor.
     */
    public static function anonymous(): self
    {
        return new self(null, null);
    }

    /**
     * Return a trusted system actor.
     */
    public static function system(): self
    {
        return new self('system', null, true);
    }

    /**
     * Build an actor identity from a Laravel authenticatable.
     */
    public static function fromAuthenticatable(Authenticatable $actor): self
    {
        $identifier = $actor->getAuthIdentifier();

        $identity = new self(
            type: $actor instanceof Model ? $actor->getMorphClass() : $actor::class,
            id: is_int($identifier) || is_string($identifier) ? $identifier : null,
        );
        $identity->authenticated = $actor;

        return $identity;
    }

    /**
     * Adapt this identity to the Content authorization boundary.
     */
    public function contentActor(): Authenticatable|SystemActor
    {
        return $this->native();
    }

    public function native(): Authenticatable|SystemActor
    {
        return $this->authenticated ?? ($this->system ? SystemActor::Background : new GenericUser(['id' => $this->id]));
    }

    public function previewActor(): Authenticatable
    {
        if ($this->authenticated instanceof Model && $this->authenticated->exists) {
            return $this->authenticated;
        }
        if (! $this->system) {
            throw new AuthorizationException('The consumer preview requires a persisted actor.');
        }

        return PreviewActor::query()->firstOrCreate(['id' => 1]);
    }

    /** @return array{type: string|null, id: int|string|null, system: bool} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id, 'system' => $this->system];
    }
}
