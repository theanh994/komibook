<?php

namespace App\Console\Commands;

use App\Models\TicketMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateTicketAttachmentsCommand extends Command
{
    /**
     * Tên và cú pháp của Artisan command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-ticket-attachments {--force : Thực thi cập nhật và di chuyển file thực sự}';

    /**
     * Mô tả của command.
     *
     * @var string
     */
    protected $description = 'Di chuyển file đính kèm support ticket từ public sang private storage (Mặc định chạy ở chế độ --dry-run)';

    /**
     * Thực thi Artisan command.
     */
    public function handle(): int
    {
        $isForce = $this->option('force');

        if (! $isForce) {
            $this->warn('[DRY-RUN MODE] Đang ở chế độ mô phỏng. Không thay đổi file hay DB. Thêm --force để thực thi.');
        } else {
            $this->info('[FORCE MODE] Bắt đầu di trú file đính kèm ticket sang private storage...');
        }

        $messages = TicketMessage::whereNotNull('attachment')->get();
        $movedCount = 0;
        $missingCount = 0;
        $alreadyPrivateCount = 0;
        $failedCount = 0;

        foreach ($messages as $msg) {
            $path = $msg->attachment;

            if (empty($path)) {
                continue;
            }

            // Đã ở đĩa private
            if (Storage::disk('private')->exists($path)) {
                $alreadyPrivateCount++;

                continue;
            }

            // Tồn tại ở đĩa public
            if (Storage::disk('public')->exists($path)) {
                $this->line("Phát hiện file public: {$path} (Message ID: {$msg->id})");

                if ($isForce) {
                    $publicFullPath = Storage::disk('public')->path($path);
                    $content = Storage::disk('public')->get($path);

                    // 1. Ghi file sang private disk
                    Storage::disk('private')->put($path, $content);
                    $privateFullPath = Storage::disk('private')->path($path);

                    // 2. Kiểm tra file đích tồn tại
                    if (! Storage::disk('private')->exists($path)) {
                        $this->error("LỖI: File đích không tồn tại sau khi copy: {$path}");
                        $failedCount++;

                        continue;
                    }

                    // 3. Đối chiếu kích thước và checksum SHA-256
                    $sourceSize = filesize($publicFullPath);
                    $destSize = filesize($privateFullPath);
                    $sourceHash = hash_file('sha256', $publicFullPath);
                    $destHash = hash_file('sha256', $privateFullPath);

                    if ($sourceSize !== $destSize || $sourceHash !== $destHash) {
                        $this->error("LỖI: Checksum không khớp cho file {$path}. Hủy thao tác xóa file nguồn.");
                        Storage::disk('private')->delete($path);
                        $failedCount++;

                        continue;
                    }

                    // 4. Chỉ xóa file nguồn ở public khi xác minh thành công 100%
                    Storage::disk('public')->delete($path);
                    $this->info("Đã di chuyển & xác minh thành công: {$path}");
                }

                $movedCount++;
            } else {
                $this->warn("File không tồn tại ở cả đĩa public lẫn private: {$path} (Message ID: {$msg->id})");
                $missingCount++;
            }
        }

        $this->info('TỔNG KẾT DI TRÚ FILE ĐÍNH KÈM TICKET:');
        $this->info("- File đã ở đĩa private: {$alreadyPrivateCount}");
        $this->info('- File '.($isForce ? 'đã di chuyển thành công' : 'sẵn sàng di chuyển').": {$movedCount}");
        $this->info("- File thất bại/lỗi checksum: {$failedCount}");
        $this->info("- File thiếu/không tìm thấy: {$missingCount}");

        if ($failedCount > 0) {
            $this->error("Có {$failedCount} file thất bại trong quá trình di trú.");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
