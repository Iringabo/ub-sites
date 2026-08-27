<?php

namespace App\Services;

use CodeIgniter\Model;

class SlugService
{
    public function make(string $value): string
    {
        $value = trim($value);

        if (function_exists('transliterator_transliterate')) {
            $value = transliterator_transliterate('Any-Latin; Latin-ASCII', $value) ?: $value;
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'contenu';
    }

    public function unique(Model $model, string $value, ?int $ignoreId = null, string $field = 'slug', string $primaryKey = 'id'): string
    {
        $base = $this->make($value);
        $slug = $base;
        $i    = 2;

        while ($this->exists($model, $slug, $ignoreId, $field, $primaryKey)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function exists(Model $model, string $slug, ?int $ignoreId, string $field, string $primaryKey): bool
    {
        if (method_exists($model, 'forSite')) {
            $model->forSite();
        }

        $builder = $model->builder()->where($field, $slug);

        if ($ignoreId !== null) {
            $builder->where($primaryKey . ' !=', $ignoreId);
        }

        return $builder->countAllResults() > 0;
    }
}
