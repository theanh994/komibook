<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ProductionMediaIntegrityService
{
    /**
     * @return array{checked:int, missing_count:int, missing:list<string>}
     */
    public function inspect(): array
    {
        $checked = 0;
        $missing = [];

        foreach (config('production_safety.public_media_references', []) as $reference) {
            $table = (string) ($reference['table'] ?? '');
            if ($table === '' || ! Schema::hasTable($table)) {
                continue;
            }

            $columns = array_values(array_filter(
                (array) ($reference['columns'] ?? []),
                fn (string $column) => Schema::hasColumn($table, $column),
            ));
            $jsonColumns = array_values(array_filter(
                (array) ($reference['json_columns'] ?? []),
                fn (string $column) => Schema::hasColumn($table, $column),
            ));
            $selectedColumns = array_values(array_unique(array_merge(['id'], $columns, $jsonColumns)));

            if (count($selectedColumns) === 1) {
                continue;
            }

            $query = DB::table($table)->select($selectedColumns);
            foreach ((array) ($reference['where'] ?? []) as $column => $value) {
                if (Schema::hasColumn($table, (string) $column)) {
                    $query->where((string) $column, $value);
                }
            }

            $query->orderBy('id')->chunkById(250, function ($rows) use ($table, $columns, $jsonColumns, &$checked, &$missing): void {
                foreach ($rows as $row) {
                    foreach ($columns as $column) {
                        $this->inspectValue($row->{$column} ?? null, $table, $column, $row->id, $checked, $missing);
                    }

                    foreach ($jsonColumns as $column) {
                        $values = $this->decodeList($row->{$column} ?? null);
                        foreach ($values as $value) {
                            $this->inspectValue($value, $table, $column, $row->id, $checked, $missing);
                        }
                    }
                }
            });
        }

        return [
            'checked' => $checked,
            'missing_count' => count($missing),
            'missing' => array_slice($missing, 0, 25),
        ];
    }

    /** @return list<mixed> */
    private function decodeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /** @param list<string> $missing */
    private function inspectValue(mixed $value, string $table, string $column, mixed $id, int &$checked, array &$missing): void
    {
        $path = $this->storagePath($value);
        if ($path === null) {
            return;
        }

        $checked++;
        if (! Storage::disk('public')->exists($path)) {
            $missing[] = "{$table}.{$column}#{$id}:{$path}";
        }
    }

    private function storagePath(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || str_starts_with($value, 'data:') || str_starts_with($value, 'blob:')) {
            return null;
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            $path = parse_url($value, PHP_URL_PATH);
            if (! is_string($path) || ! str_starts_with($path, '/storage/')) {
                return null;
            }
            $value = $path;
        }

        $value = str_replace('\\', '/', $value);
        $value = explode('#', explode('?', $value, 2)[0], 2)[0];

        if (str_starts_with($value, '/storage/')) {
            $value = substr($value, strlen('/storage/'));
        } elseif (str_starts_with($value, 'storage/')) {
            $value = substr($value, strlen('storage/'));
        } elseif (str_starts_with($value, '/')) {
            return null;
        }

        $value = ltrim($value, '/');

        return $value === '' || str_contains($value, '../') ? null : $value;
    }
}
