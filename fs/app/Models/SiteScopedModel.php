<?php

namespace App\Models;

use CodeIgniter\Model;
use Throwable;

abstract class SiteScopedModel extends Model
{
    /**
     * @var list<string>
     */
    protected $beforeInsert = ['attachActiveSiteId'];

    protected function initialize(): void
    {
        if (! in_array('site_id', $this->allowedFields, true)) {
            $this->allowedFields[] = 'site_id';
        }

        $this->casts['site_id'] ??= 'integer';
    }

    public function forSite(?int $siteId = null): static
    {
        $siteId ??= service('siteResolver')->activeSiteId();

        if ($siteId > 0 && $this->hasSiteIdColumn()) {
            $this->where($this->table . '.site_id', $siteId);
        }

        return $this;
    }

    public function forActiveSite(): static
    {
        return $this->forSite();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function attachActiveSiteId(array $data): array
    {
        if (! $this->hasSiteIdColumn()) {
            return $data;
        }

        if (! isset($data['data']) || ! is_array($data['data'])) {
            return $data;
        }

        if (! array_key_exists('site_id', $data['data']) || (int) ($data['data']['site_id'] ?? 0) <= 0) {
            $data['data']['site_id'] = service('siteResolver')->activeSiteId();
        }

        return $data;
    }

    protected function hasSiteIdColumn(): bool
    {
        try {
            if ($this->db->tableExists($this->table) && $this->db->fieldExists('site_id', $this->table)) {
                return true;
            }

            $prefixedTable = $this->db->prefixTable($this->table);

            return $prefixedTable !== $this->table
                && $this->db->tableExists($prefixedTable)
                && $this->db->fieldExists('site_id', $prefixedTable);
        } catch (Throwable) {
            return false;
        }
    }
}
