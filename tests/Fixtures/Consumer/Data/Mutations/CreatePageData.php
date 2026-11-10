<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ValidatedInput;
use Illuminate\Validation\Rule;
use LogicException;
use Nvl\Pages\Enums\PageKind;
use Nvl\Pages\Enums\PageStatus;
use Nvl\Seo\Enums\SitemapChangeFrequency;
use Nvl\Translatable\Rules\SupportedLocaleMapRule;

/**
 * Validated contract for creating a structural page and its initial locale rows.
 *
 * @api
 */
final class CreatePageData
{
    /**
     * Create one validated page creation payload.
     *
     * @param  array<string,array{title:string,navigationLabel?:?string,summary?:?string}>  $translations
     */
    public function __construct(
        public readonly string $key,
        public readonly string $slug,
        public readonly ?string $parentId = null,
        public readonly string $site = 'default',
        public readonly PageKind $kind = PageKind::Static,
        public readonly ?string $resource = null,
        public readonly PageStatus $status = PageStatus::Draft,
        public readonly int $position = 0,
        public readonly bool $isNavigable = true,
        public readonly bool $sitemapIncluded = true,
        public readonly ?string $sitemapPriority = null,
        public readonly ?SitemapChangeFrequency $sitemapChangeFrequency = null,
        public readonly ?string $publishedAt = null,
        public readonly ?string $expiresAt = null,
        public readonly array $translations = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
            'slug' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9](?:[a-z0-9_-]*[a-z0-9])?$/'],
            'parentId' => ['nullable', 'uuid'],
            'site' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
            'kind' => ['sometimes', Rule::enum(PageKind::class)],
            'resource' => ['nullable', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.-]*$/'],
            'status' => ['sometimes', Rule::enum(PageStatus::class)],
            'position' => ['integer', 'min:0', 'max:2147483647'],
            'isNavigable' => ['boolean'],
            'sitemapIncluded' => ['boolean'],
            'sitemapPriority' => ['nullable', 'numeric', 'between:0,1'],
            'sitemapChangeFrequency' => ['nullable', Rule::enum(SitemapChangeFrequency::class)],
            'publishedAt' => ['nullable', 'date'],
            'expiresAt' => ['nullable', 'date', 'after:publishedAt'],
            'translations' => ['array', new SupportedLocaleMapRule],
            'translations.*' => ['array:title,navigationLabel,summary'],
            'translations.*.title' => ['required', 'string', 'max:255'],
            'translations.*.navigationLabel' => ['nullable', 'string', 'max:255'],
            'translations.*.summary' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Validate native HTTP input and map it to this consumer fixture.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, self::rules())->safe();

        return new self(
            key: $validated->string('key')->toString(),
            slug: $validated->string('slug')->toString(),
            parentId: $validated->input('parentId') === null ? null : $validated->string('parentId')->toString(),
            site: $validated->string('site', 'default')->toString(),
            kind: $validated->enum('kind', PageKind::class, PageKind::Static),
            resource: $validated->input('resource') === null ? null : $validated->string('resource')->toString(),
            status: $validated->enum('status', PageStatus::class, PageStatus::Draft),
            position: $validated->integer('position', 0),
            isNavigable: $validated->boolean('isNavigable', true),
            sitemapIncluded: $validated->boolean('sitemapIncluded', true),
            sitemapPriority: $validated->input('sitemapPriority') === null ? null : $validated->string('sitemapPriority')->toString(),
            sitemapChangeFrequency: $validated->enum('sitemapChangeFrequency', SitemapChangeFrequency::class, null),
            publishedAt: $validated->input('publishedAt') === null ? null : $validated->string('publishedAt')->toString(),
            expiresAt: $validated->input('expiresAt') === null ? null : $validated->string('expiresAt')->toString(),
            translations: self::translations($validated),
        );
    }

    /**
     * Preserve locale map keys and the presence of nullable translation fields.
     *
     * @return array<string, array{title: string, navigationLabel?: string|null, summary?: string|null}>
     */
    private static function translations(ValidatedInput $input): array
    {
        $translations = [];
        foreach ($input->array('translations') as $locale => $row) {
            if (! is_string($locale) || ! is_array($row) || ! is_string($row['title'] ?? null)) {
                throw new LogicException('Validated page translations must contain locale keys and string titles.');
            }
            $translation = ['title' => $row['title']];
            if (array_key_exists('navigationLabel', $row)) {
                $label = $row['navigationLabel'];
                if ($label !== null && ! is_string($label)) {
                    throw new LogicException('Validated page navigation labels must be strings or null.');
                }
                $translation['navigationLabel'] = $label;
            }
            if (array_key_exists('summary', $row)) {
                $summary = $row['summary'];
                if ($summary !== null && ! is_string($summary)) {
                    throw new LogicException('Validated page summaries must be strings or null.');
                }
                $translation['summary'] = $summary;
            }
            $translations[$locale] = $translation;
        }

        return $translations;
    }
}
