<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tạo bảng authors
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('pen_name');
            $table->text('bio')->nullable();
            $table->string('identity_document')->comment('Đường dẫn ảnh chứng thực CCCD/Passport');
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('bank_account_number');
            $table->string('bank_name');
            $table->string('bank_holder_name');
            $table->enum('status', ['pending', 'active', 'rejected'])->default('pending');
            $table->string('rejection_reason')->nullable()->comment('Lý do từ chối hồ sơ');
            $table->timestamps();
        });

        // 2. Tạo bảng book_chapters
        Schema::create('book_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->longText('content')->nullable();
            $table->integer('order')->default(1);
            $table->boolean('is_free')->default(false)->comment('Thiết lập đọc thử');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
        });

        // 3. Tạo bảng book_drm_settings
        Schema::create('book_drm_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->string('copyright_number')->nullable()->comment('Số đăng ký bản quyền');
            $table->string('copyright_owner')->nullable()->comment('Chủ sở hữu bản quyền');
            $table->boolean('social_drm')->default(true)->comment('Watermark thông tin người mua');
            $table->boolean('hard_drm')->default(false)->comment('Mã hóa tệp cứng');
            $table->integer('copy_limit_percent')->default(10)->comment('Giới hạn phần trăm sao chép');
            $table->boolean('allow_printing')->default(false)->comment('Cho phép in ấn');
            $table->string('license_type')->default('all_rights_reserved');
            $table->timestamps();
        });

        // 4. Tạo bảng inventory_audits
        Schema::create('inventory_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->string('audit_period')->comment('Ví dụ: Q3 2026');
            $table->foreignId('audited_by')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['draft', 'completed'])->default('draft');
            $table->timestamps();
        });

        // 5. Tạo bảng inventory_audit_items
        Schema::create('inventory_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_audit_id')->constrained('inventory_audits')->onDelete('cascade');
            $table->foreignId('book_id')->constrained('books')->onDelete('cascade');
            $table->integer('system_qty');
            $table->integer('physical_qty');
            $table->integer('difference');
            $table->timestamps();
        });

        // 6. Tạo bảng stock_transfers
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->onDelete('cascade');
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->string('transfer_code')->unique();
            $table->text('reason')->nullable();
            $table->enum('status', ['draft', 'shipped', 'received'])->default('draft');
            $table->timestamps();
        });

        // 7. Tạo bảng stock_transfer_items
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->onDelete('cascade');
            $table->foreignId('book_id')->constrained('books')->onDelete('cascade');
            $table->integer('quantity');
            $table->timestamps();
        });

        // 8. Tạo bảng support_tickets
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('subject');
            $table->string('category');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['open', 'pending', 'resolved'])->default('open');
            $table->timestamps();
        });

        // 9. Tạo bảng ticket_messages
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->string('attachment')->nullable();
            $table->timestamps();
        });

        // 10. Tạo bảng help_articles
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('category_name');
            $table->string('title');
            $table->text('content');
            $table->integer('views_count')->default(0);
            $table->integer('helpful_count')->default(0);
            $table->enum('status', ['draft', 'published'])->default('published');
            $table->timestamps();
        });

        // 11. Tạo bảng membership_tiers
        Schema::create('membership_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('min_points');
            $table->integer('discount_percent');
            $table->text('benefits')->nullable();
            $table->timestamps();
        });

        // 12. Cập nhật bảng users để tích hợp điểm và hạng thành viên
        Schema::table('users', function (Blueprint $table) {
            $table->integer('points')->default(0)->after('role');
            $table->foreignId('membership_tier_id')->nullable()->after('points')->constrained('membership_tiers')->onDelete('set null');
        });

        // 13. Cập nhật bảng orders để tích hợp thông tin vận đơn và hãng vận chuyển
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_carrier')->nullable()->after('status');
            $table->string('shipping_tracking_code')->nullable()->after('shipping_carrier');
            $table->string('shipping_status')->nullable()->after('shipping_tracking_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['shipping_carrier', 'shipping_tracking_code', 'shipping_status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['membership_tier_id']);
            $table->dropColumn(['points', 'membership_tier_id']);
        });

        Schema::dropIfExists('membership_tiers');
        Schema::dropIfExists('help_articles');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('inventory_audit_items');
        Schema::dropIfExists('inventory_audits');
        Schema::dropIfExists('book_drm_settings');
        Schema::dropIfExists('book_chapters');
        Schema::dropIfExists('authors');
    }
};
