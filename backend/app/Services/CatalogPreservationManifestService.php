<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CatalogPreservationManifestService
{
    public function build(): array
    {
        $books = DB::table('books')->orderBy('id')->get();
        $isbnGroups = $this->duplicates($books, fn (object $book) => $this->normalizeIsbn($book->isbn));
        $slugGroups = $this->duplicates($books, fn (object $book) => $this->normalizeSlug($book->slug));
        $warehouse = $this->warehouseInventory();
        $usedBookLinks = $this->countsBy('used_book_listings', 'book_id');

        $records = $books->map(function (object $book) use ($isbnGroups, $slugGroups, $warehouse, $usedBookLinks): array {
            $isbn = $this->normalizeIsbn($book->isbn);
            $slug = $this->normalizeSlug($book->slug);
            $stock = (int) $book->stock;
            $warehouseQuantity = (int) ($warehouse['quantities'][$book->id] ?? 0);
            $warehouseRows = (int) ($warehouse['rows'][$book->id] ?? 0);
            $conflicts = [];
            $warnings = [];

            if ($isbn === null && $slug === null) {
                $conflicts[] = 'missing_stable_key';
            }
            if ($isbn !== null && isset($isbnGroups[$isbn])) {
                $conflicts[] = 'duplicate_isbn';
            }
            if ($slug !== null && isset($slugGroups[$slug])) {
                $conflicts[] = 'duplicate_slug';
            }
            if (isset($warehouse['tenant_mismatch_book_ids'][$book->id])) {
                $conflicts[] = 'warehouse_stock_tenant_mismatch';
            }
            if (($book->type ?? 'physical') !== 'ebook' && $stock !== $warehouseQuantity) {
                $conflicts[] = 'stock_total_mismatch';
            }
            if (blank($book->cover_image)) {
                $warnings[] = 'missing_cover';
            }

            return [
                'book_id' => (int) $book->id,
                'vendor_id' => (int) $book->vendor_id,
                'stable_key' => $isbn !== null ? "isbn:{$isbn}" : ($slug !== null ? "slug:{$slug}" : null),
                'isbn_normalized' => $isbn,
                'slug_normalized' => $slug,
                'classification' => $conflicts === [] ? 'preserve_existing' : 'conflict_review',
                'protected_content_fingerprint' => hash('sha256', json_encode([
                    'title' => $book->title,
                    'description' => $book->description,
                    'price' => (string) $book->price,
                    'sale_price' => $book->sale_price === null ? null : (string) $book->sale_price,
                    'stock' => $stock,
                    'cover_image' => $book->cover_image,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'inventory' => [
                    'book_stock' => $stock,
                    'warehouse_quantity' => $warehouseQuantity,
                    'warehouse_rows' => $warehouseRows,
                ],
                'related_records' => [
                    'used_book_listings' => (int) ($usedBookLinks[$book->id] ?? 0),
                ],
                'conflicts' => $conflicts,
                'warnings' => $warnings,
            ];
        })->all();

        $conflictCounts = collect($records)->flatMap(fn (array $record) => $record['conflicts'])->countBy()->all();

        return [
            'mode' => 'dry_run',
            'writes_performed' => false,
            'import_source_supplied' => false,
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'books' => count($records),
                'preserve_existing' => collect($records)->where('classification', 'preserve_existing')->count(),
                'conflict_review' => collect($records)->where('classification', 'conflict_review')->count(),
                'conflicts' => $conflictCounts,
                'related_records' => $this->relatedRecordSummary(),
            ],
            'decision' => $conflictCounts === []
                ? 'ready_for_additive_import_after_source_review'
                : 'stop_for_conflict_review_before_import',
            'records' => $records,
        ];
    }

    private function duplicates(Collection $books, callable $normalizer): array
    {
        return $books->map($normalizer)
            ->filter()
            ->countBy()
            ->filter(fn (int $count) => $count > 1)
            ->all();
    }

    private function normalizeIsbn(?string $isbn): ?string
    {
        $normalized = preg_replace('/[^0-9X]/i', '', trim((string) $isbn));

        return $normalized === '' ? null : strtoupper($normalized);
    }

    private function normalizeSlug(?string $slug): ?string
    {
        $normalized = mb_strtolower(trim((string) $slug));

        return $normalized === '' ? null : $normalized;
    }

    private function warehouseInventory(): array
    {
        if (! Schema::hasTable('warehouse_stocks') || ! Schema::hasTable('warehouses')) {
            return ['quantities' => [], 'rows' => [], 'tenant_mismatch_book_ids' => []];
        }

        $quantities = DB::table('warehouse_stocks')->select('book_id', DB::raw('SUM(quantity) as total'))
            ->groupBy('book_id')->pluck('total', 'book_id')->all();
        $rows = DB::table('warehouse_stocks')->select('book_id', DB::raw('COUNT(*) as total'))
            ->groupBy('book_id')->pluck('total', 'book_id')->all();
        $mismatches = DB::table('warehouse_stocks as stocks')
            ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->join('books', 'books.id', '=', 'stocks.book_id')
            ->whereColumn('warehouses.vendor_id', '!=', 'books.vendor_id')
            ->pluck('stocks.book_id')->mapWithKeys(fn ($id) => [(int) $id => true])->all();

        return ['quantities' => $quantities, 'rows' => $rows, 'tenant_mismatch_book_ids' => $mismatches];
    }

    private function countsBy(string $table, string $column): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        return DB::table($table)->select($column, DB::raw('COUNT(*) as total'))
            ->groupBy($column)->pluck('total', $column)->all();
    }

    private function relatedRecordSummary(): array
    {
        return [
            'used_book_listings' => Schema::hasTable('used_book_listings') ? DB::table('used_book_listings')->count() : 0,
            'warehouses' => Schema::hasTable('warehouses') ? DB::table('warehouses')->count() : 0,
            'warehouse_stock_rows' => Schema::hasTable('warehouse_stocks') ? DB::table('warehouse_stocks')->count() : 0,
        ];
    }
}
