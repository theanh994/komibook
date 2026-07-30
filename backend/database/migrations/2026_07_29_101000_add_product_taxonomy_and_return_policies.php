<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_policy_versions', function (Blueprint $table) {
            $table->id();
            $table->string('policy_key', 50);
            $table->unsignedInteger('version');
            $table->string('applies_to', 50);
            $table->boolean('is_returnable');
            $table->unsignedInteger('return_window_days')->nullable();
            $table->text('terms');
            $table->timestamp('active_from');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->unique(['policy_key', 'version']);
        });

        $now = now();
        DB::table('return_policy_versions')->insert([
            [
                'policy_key' => 'ebook_non_returnable',
                'version' => 1,
                'applies_to' => 'ebook',
                'is_returnable' => false,
                'return_window_days' => null,
                'terms' => 'Ebook không được trả lại sau khi mua; các điều chỉnh tài chính hợp lệ được xử lý riêng.',
                'active_from' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'policy_key' => 'physical_standard',
                'version' => 1,
                'applies_to' => 'physical_publisher_catalog',
                'is_returnable' => false,
                'return_window_days' => null,
                'terms' => 'Dữ liệu vật lý legacy không tự động được mở quyền trả hàng.',
                'active_from' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'policy_key' => 'used_book_return',
                'version' => 1,
                'applies_to' => 'physical_used_resale',
                'is_returnable' => true,
                'return_window_days' => 7,
                'terms' => 'Sách cũ đủ điều kiện được yêu cầu trả trong thời hạn chính sách.',
                'active_from' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::table('books', function (Blueprint $table) {
            $table->string('format', 20)->nullable()->after('type');
            $table->string('provenance', 30)->nullable()->after('format');
            $table->string('condition', 20)->nullable()->after('provenance');
            $table->string('fulfillment_mode', 40)->nullable()->after('condition');
            $table->foreignId('return_policy_version_id')
                ->nullable()
                ->after('fulfillment_mode')
                ->constrained('return_policy_versions')
                ->restrictOnDelete();
            $table->index(['format', 'provenance', 'status'], 'books_taxonomy_status_index');
        });

        $ebookPolicy = DB::table('return_policy_versions')->where('policy_key', 'ebook_non_returnable')->value('id');
        $physicalPolicy = DB::table('return_policy_versions')->where('policy_key', 'physical_standard')->value('id');

        DB::table('books')->where('type', 'ebook')->update([
            'format' => 'ebook',
            'provenance' => 'publisher_catalog',
            'condition' => null,
            'fulfillment_mode' => 'digital',
            'return_policy_version_id' => $ebookPolicy,
        ]);
        DB::table('books')->where('type', 'physical')->update([
            'format' => 'physical',
            'provenance' => 'publisher_catalog',
            'condition' => null,
            'fulfillment_mode' => 'vendor_warehouse',
            'return_policy_version_id' => $physicalPolicy,
        ]);
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex('books_taxonomy_status_index');
            $table->dropConstrainedForeignId('return_policy_version_id');
            $table->dropColumn(['format', 'provenance', 'condition', 'fulfillment_mode']);
        });
        Schema::dropIfExists('return_policy_versions');
    }
};
