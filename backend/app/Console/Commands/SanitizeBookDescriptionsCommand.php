<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\HtmlSanitizer;
use Illuminate\Console\Command;

class SanitizeBookDescriptionsCommand extends Command
{
    /**
     * Tên và cú pháp của Artisan command.
     *
     * @var string
     */
    protected $signature = 'app:sanitize-book-descriptions {--force : Thực thi cập nhật trực tiếp vào cơ sở dữ liệu}';

    /**
     * Mô tả của command.
     *
     * @var string
     */
    protected $description = 'Làm sạch mô tả sách bị nhiễm Stored XSS trong DB bằng HTMLPurifier (Mặc định chạy ở chế độ --dry-run)';

    /**
     * Thực thi Artisan command.
     */
    public function handle(): int
    {
        $isForce = $this->option('force');

        if (! $isForce) {
            $this->warn('Đang chạy ở chế độ DRY-RUN. Cơ sở dữ liệu sẽ KHÔNG bị thay đổi. Thêm --force để cập nhật.');
        } else {
            $this->info('Đang chạy ở chế độ FORCE. Cập nhật trực tiếp cơ sở dữ liệu...');
        }

        $books = Book::withoutGlobalScopes()->whereNotNull('description')->get();
        $updatedCount = 0;

        foreach ($books as $book) {
            $original = $book->description;
            $sanitized = HtmlSanitizer::sanitize($original);

            if ($original !== $sanitized) {
                $updatedCount++;
                $this->line("Book ID {$book->id} ('{$book->title}'): Cần làm sạch.");

                if ($isForce) {
                    $book->description = $sanitized;
                    $book->save();
                }
            }
        }

        if (! $isForce) {
            $this->info("DRY-RUN HOÀN TẤT: Phát hiện {$updatedCount}/{$books->count()} cuốn sách có mô tả chứa HTML không an toàn.");
        } else {
            $this->info("THỰC THI HOÀN TẤT: Đã dọn dẹp và cập nhật {$updatedCount}/{$books->count()} cuốn sách trong DB.");
        }

        return Command::SUCCESS;
    }
}
