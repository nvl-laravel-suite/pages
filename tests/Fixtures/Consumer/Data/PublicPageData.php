<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Nvl\Pages\Contracts\PageUrlGenerator;
use Nvl\Pages\Enums\PageKind;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\SerializesPageValues;
use Nvl\Translatable\TranslationResolution;

/**
 * Locale-resolved and redacted page projection for public delivery.
 *
 * @implements Arrayable<string, mixed>
 *
 * @api
 */
final class PublicPageData implements Arrayable, JsonSerializable
{
    use SerializesPageValues {
        toArray as private serializePublicProperties;
    }

    private readonly bool $includesPublishedAt;

    /**
     * Create one locale-resolved public page projection.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $key,
        public readonly string $slug,
        public readonly string $path,
        public readonly PageKind $kind,
        public readonly string $url,
        public readonly string $locale,
        public readonly string $title,
        public readonly ?string $navigationLabel,
        public readonly ?string $summary,
        public readonly ?string $titleLocale,
        public readonly ?string $navigationLabelLocale,
        public readonly ?string $summaryLocale,
        public readonly ?string $publishedAt = null,
    ) {
        $this->includesPublishedAt = func_num_args() >= 14;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $properties = $this->serializePublicProperties();
        if (! $this->includesPublishedAt) {
            unset($properties['publishedAt']);
        }

        return $properties;
    }

    /**
     * Build a locale-resolved public projection from one page.
     *
     * @internal
     */
    public static function fromModel(
        Page $page,
        string $locale,
        PageUrlGenerator $urls,
    ): self {
        $page->loadMissing('translations');
        $title = $page->resolveTranslation('title', $locale);
        $navigationLabel = $page->resolveTranslation('navigation_label', $locale);
        $summary = $page->resolveTranslation('summary', $locale);
        $titleValue = self::stringValue($title) ?? '';
        $navigationLabelValue = self::stringValue($navigationLabel);
        $publishedAt = $page->published_at ?? $page->created_at;

        return new self(
            id: $page->id,
            key: $page->key,
            slug: $page->slug,
            path: $page->path,
            kind: $page->kind,
            url: $urls->url($page, $locale),
            locale: $locale,
            title: $titleValue,
            navigationLabel: $navigationLabelValue ?? $titleValue,
            summary: self::stringValue($summary),
            titleLocale: $title->resolvedLocale,
            navigationLabelLocale: $navigationLabelValue !== null
                ? $navigationLabel->resolvedLocale
                : $title->resolvedLocale,
            summaryLocale: $summary->resolvedLocale,
            publishedAt: $publishedAt->format(DATE_ATOM),
        );
    }

    /**
     * Return a string translation value when one resolved.
     */
    private static function stringValue(TranslationResolution $resolution): ?string
    {
        return is_string($resolution->value) ? $resolution->value : null;
    }
}
