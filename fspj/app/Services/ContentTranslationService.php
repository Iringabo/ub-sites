<?php

namespace App\Services;

use App\Models\ContentTranslationModel;
use App\Models\SettingModel;
use Throwable;

class ContentTranslationService
{
    private const DEFAULT_LOCALE = 'fr';

    /**
     * @var list<string>
     */
    private array $supportedLocales = ['fr', 'en'];

    /**
     * @var array<string, array<int, array<string, string>>>
     */
    private array $recordCache = [];

    /**
     * @var array<string, array<string, string>>|null
     */
    private ?array $settingsRows = null;

    private ?int $settingsRowsSiteId = null;

    public function locale(?string $locale = null): string
    {
        $locale ??= service('request')->getLocale();
        $locale = strtolower(trim((string) $locale));
        $locale = str_replace('_', '-', $locale);
        $locale = explode('-', $locale)[0] ?? $locale;

        return in_array($locale, $this->supportedLocales, true) ? $locale : self::DEFAULT_LOCALE;
    }

    public function isDefaultLocale(?string $locale = null): bool
    {
        return $this->locale($locale) === self::DEFAULT_LOCALE;
    }

    public function reset(): void
    {
        $this->recordCache = [];
        $this->settingsRows = null;
        $this->settingsRowsSiteId = null;
    }

    /**
     * @template T of object|array<string, mixed>|null
     *
     * @param T $record
     *
     * @return T
     */
    public function record(string $resourceType, object|array|null $record, ?string $locale = null): object|array|null
    {
        $locale = $this->locale($locale);
        if ($record === null || $locale === self::DEFAULT_LOCALE) {
            return $record;
        }

        $id = $this->recordId($record);
        if ($id === null) {
            return $record;
        }

        $translations = $this->values($resourceType, $id, $locale);
        if ($translations === []) {
            return $record;
        }

        return $this->applyTranslations($record, $translations);
    }

    /**
     * @template T of object|array<string, mixed>
     *
     * @param list<T> $records
     *
     * @return list<T>
     */
    public function records(string $resourceType, array $records, ?string $locale = null): array
    {
        $locale = $this->locale($locale);
        if ($records === [] || $locale === self::DEFAULT_LOCALE) {
            return $records;
        }

        $ids = [];
        foreach ($records as $record) {
            $id = $this->recordId($record);
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        $translations = $this->valuesForIds($resourceType, array_values(array_unique($ids)), $locale);
        if ($translations === []) {
            return $records;
        }

        foreach ($records as $index => $record) {
            $id = $this->recordId($record);
            if ($id !== null && isset($translations[$id])) {
                $records[$index] = $this->applyTranslations($record, $translations[$id]);
            }
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    public function pageContent(?object $page, array $fallback, ?string $locale = null): array
    {
        if ($page === null) {
            return $fallback;
        }

        $page = $this->record('pages', $page, $locale);
        $content = site_decode_page_content(isset($page->content) ? (string) $page->content : null);

        return array_replace_recursive($fallback, $content);
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(array $settings, ?string $locale = null): array
    {
        $locale = $this->locale($locale);
        if ($locale === self::DEFAULT_LOCALE) {
            return $settings;
        }

        foreach ($this->settingTranslationRows($locale) as $key => $row) {
            $settings[$key] = $this->castSettingValue($row['value'], $row['type']);
        }

        return $settings;
    }

    /**
     * @return array<string, string>
     */
    public function values(string $resourceType, int $resourceId, ?string $locale = null): array
    {
        $locale = $this->locale($locale);
        if ($locale === self::DEFAULT_LOCALE) {
            return [];
        }

        $cacheKey = $this->cacheKey($resourceType, $locale);
        if (! isset($this->recordCache[$cacheKey][$resourceId])) {
            $this->valuesForIds($resourceType, [$resourceId], $locale);
        }

        return $this->recordCache[$cacheKey][$resourceId] ?? [];
    }

    /**
     * @param list<int> $resourceIds
     *
     * @return array<int, array<string, string>>
     */
    public function valuesForIds(string $resourceType, array $resourceIds, ?string $locale = null): array
    {
        $locale = $this->locale($locale);
        if ($locale === self::DEFAULT_LOCALE || $resourceIds === []) {
            return [];
        }

        $resourceIds = array_values(array_unique(array_filter($resourceIds, static fn (int $id): bool => $id > 0)));
        if ($resourceIds === []) {
            return [];
        }

        $cacheKey = $this->cacheKey($resourceType, $locale);
        $missingIds = array_values(array_filter(
            $resourceIds,
            fn (int $id): bool => ! array_key_exists($id, $this->recordCache[$cacheKey] ?? []),
        ));

        foreach ($missingIds as $id) {
            $this->recordCache[$cacheKey][$id] = [];
        }

        if ($missingIds !== []) {
            try {
                $rows = model(ContentTranslationModel::class, false)
                    ->forSite()
                    ->where('resource_type', $resourceType)
                    ->where('locale', $locale)
                    ->whereIn('resource_id', $missingIds)
                    ->findAll();
            } catch (Throwable) {
                $rows = [];
            }

            foreach ($rows as $row) {
                $value = trim((string) ($row->value ?? ''));
                if ($value === '') {
                    continue;
                }

                $id = (int) $row->resource_id;
                $field = (string) $row->field;
                $this->recordCache[$cacheKey][$id][$field] = $value;
            }
        }

        $result = [];
        foreach ($resourceIds as $id) {
            if (($this->recordCache[$cacheKey][$id] ?? []) !== []) {
                $result[$id] = $this->recordCache[$cacheKey][$id];
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $values
     */
    /**
     * @param list<string> $fields
     * @return list<int>
     */
    public function matchingResourceIds(string $resourceType, string $query, array $fields): array
    {
        $query = trim($query);
        if ($query === '' || $fields === [] || $this->isDefaultLocale()) {
            return [];
        }

        $rows = db_connect()->table('content_translations')
            ->select('resource_id')
            ->where('site_id', service('siteResolver')->activeSiteId())
            ->where('resource_type', $resourceType)
            ->where('locale', site_current_locale())
            ->whereIn('field', $fields)
            ->like('value', $query)
            ->get()
            ->getResultArray();

        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['resource_id'],
            $rows,
        )));
    }

    public function save(string $resourceType, int $resourceId, string $locale, array $values): void
    {
        $locale = $this->locale($locale);
        if ($locale === self::DEFAULT_LOCALE || $resourceId <= 0) {
            return;
        }

        $model = model(ContentTranslationModel::class, false);
        $siteId = service('siteResolver')->activeSiteId();

        foreach ($values as $field => $value) {
            $field = trim((string) $field);
            if ($field === '') {
                continue;
            }

            $value = is_array($value) ? '' : trim((string) $value);
            $existing = $model
                ->forSite($siteId)
                ->where('resource_type', $resourceType)
                ->where('resource_id', $resourceId)
                ->where('locale', $locale)
                ->where('field', $field)
                ->first();

            if ($value === '') {
                if ($existing !== null) {
                    $model->delete((int) $existing->id);
                }

                continue;
            }

            $data = [
                'site_id'       => $siteId,
                'resource_type' => $resourceType,
                'resource_id'   => $resourceId,
                'locale'        => $locale,
                'field'         => $field,
                'value'         => $value,
            ];

            if ($existing === null) {
                $model->insert($data);
            } else {
                $model->update((int) $existing->id, $data);
            }
        }

        unset($this->recordCache[$this->cacheKey($resourceType, $locale)]);
        $this->settingsRows = null;
        $this->settingsRowsSiteId = null;
    }

    private function recordId(object|array $record): ?int
    {
        $id = is_array($record) ? ($record['id'] ?? null) : ($record->id ?? null);

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * @param array<string, string> $translations
     */
    private function applyTranslations(object|array $record, array $translations): object|array
    {
        if (is_array($record)) {
            foreach ($translations as $field => $value) {
                $record[$field] = $value;
            }

            return $record;
        }

        $translated = clone $record;
        foreach ($translations as $field => $value) {
            $translated->{$field} = $value;
        }

        return $translated;
    }

    private function cacheKey(string $resourceType, string $locale): string
    {
        return service('siteResolver')->activeSiteId() . ':' . $locale . ':' . $resourceType;
    }

    /**
     * @return array<string, array{value: string, type: string}>
     */
    private function settingTranslationRows(string $locale): array
    {
        $siteId = service('siteResolver')->activeSiteId();

        if ($this->settingsRows !== null && $this->settingsRowsSiteId === $siteId) {
            return $this->settingsRows[$locale] ?? [];
        }

        $this->settingsRows = [];
        $this->settingsRowsSiteId = $siteId;

        try {
            $settings = model(SettingModel::class, false)->forSite()->findAll();
            $idsByKey = [];
            $typesById = [];

            foreach ($settings as $setting) {
                $id = (int) $setting->id;
                $idsByKey[$id] = (string) $setting->key;
                $typesById[$id] = (string) $setting->type;
            }

            if ($idsByKey === []) {
                return [];
            }

            $translations = $this->valuesForIds('settings', array_keys($idsByKey), $locale);
            foreach ($translations as $id => $fields) {
                if (! isset($fields['value'], $idsByKey[$id])) {
                    continue;
                }

                $this->settingsRows[$locale][$idsByKey[$id]] = [
                    'value' => $fields['value'],
                    'type'  => $typesById[$id] ?? 'string',
                ];
            }
        } catch (Throwable) {
            return [];
        }

        return $this->settingsRows[$locale] ?? [];
    }

    private function castSettingValue(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => in_array(strtolower($value), ['1', 'true', 'oui', 'yes'], true),
            'json'    => json_decode($value, true) ?: [],
            default   => $value,
        };
    }
}
