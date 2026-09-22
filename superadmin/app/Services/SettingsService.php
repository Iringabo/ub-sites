<?php

namespace App\Services;

use App\Models\SettingModel;
use Throwable;

class SettingsService
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $settings = null;

    private ?int $settingsSiteId = null;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $siteId = service('siteResolver')->activeSiteId();

        if ($this->settings !== null && $this->settingsSiteId === $siteId) {
            return $this->settings;
        }

        $settings = $this->defaults();

        try {
            $rows = model(SettingModel::class, false)->forSite($siteId)->findAll();
        } catch (Throwable) {
            $this->settingsSiteId = $siteId;

            return $this->settings = $settings;
        }

        foreach ($rows as $row) {
            $settings[$row->key] = $this->castValue((string) $row->value, (string) $row->type);
        }

        $this->settingsSiteId = $siteId;

        return $this->settings = $settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function reset(): void
    {
        $this->settings = null;
        $this->settingsSiteId = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        $missing = lang('Home.configurationMissing');
        $site = service('siteResolver')->activeSite();
        $siteName = trim((string) ($site->name ?? '')) ?: $missing;
        $siteShortName = strtoupper(trim((string) ($site->identifier ?? ''))) ?: $missing;

        return [
            'institution.faculty_name' => $siteName,
            'institution.short_name'   => $siteShortName,
            'institution.university'   => 'Université du Burundi',
            'contact.address_line'     => trim((string) ($site->address ?? '')) ?: $missing,
            'contact.address_commune'  => $missing,
            'contact.address_province' => $missing,
            'contact.address_country'  => 'Burundi',
            'contact.phone'            => trim((string) ($site->phone ?? '')) ?: $missing,
            'contact.email'            => trim((string) ($site->contact_email ?? '')) ?: $missing,
            'contact.hours'            => $missing,
            'footer.text'              => $missing,
            'footer.copyright'         => $missing,
            'assets.logo'              => trim((string) ($site->logo ?? '')) ?: 'assets/images/logo-placeholder.png',
            'seo.default_title'        => $missing,
            'seo.default_description'  => $missing,
            'seo.theme_color'          => trim((string) ($site->primary_color ?? '')) ?: '#0D9B49',
            'seo.og_image'             => 'assets/images/logo-placeholder.png',
            'home.hero_indicator_size' => '1',
            'social.links'             => [],
        ];
    }

    private function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => in_array(strtolower($value), ['1', 'true', 'oui'], true),
            'json'    => json_decode($value, true) ?: [],
            default   => $value,
        };
    }
}
