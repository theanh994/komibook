<?php

namespace App\Services;

use App\Models\Book;
use App\Models\EbookVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EbookVersionService
{
    public function release(Book $book, ?User $actor = null, ?string $releaseNotes = null): EbookVersion
    {
        abort_unless($book->isEbook(), 422, 'Chỉ ebook mới có phiên bản nội dung số.');

        return DB::transaction(function () use ($book, $actor, $releaseNotes) {
            $locked = Book::withoutGlobalScopes()->whereKey($book->id)->lockForUpdate()->firstOrFail();
            $next = (int) EbookVersion::where('book_id', $locked->id)->max('version') + 1;

            return EbookVersion::create([
                'book_id' => $locked->id,
                'version' => $next,
                'file_path' => $locked->file_path,
                'chapter_snapshot' => $locked->chapters()
                    ->orderBy('order')
                    ->get(['id', 'title', 'content', 'order', 'is_free', 'current_revision'])
                    ->toArray(),
                'release_notes' => $releaseNotes,
                'published_by' => $actor?->id,
                'published_at' => now(),
            ]);
        });
    }

    public function currentOrCreate(Book $book): EbookVersion
    {
        return EbookVersion::where('book_id', $book->id)->latest('version')->first()
            ?? $this->release($book, null, 'Khởi tạo phiên bản tương thích cho ebook đã xuất bản.');
    }
}
