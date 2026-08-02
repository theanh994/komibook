<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $policyId = DB::table('return_policy_versions')->insertGetId([
            'policy_key' => 'physical_standard',
            'version' => 2,
            'applies_to' => 'physical_publisher_catalog',
            'is_returnable' => true,
            'return_window_days' => 7,
            'terms' => 'Sách vật lý mới được yêu cầu trả hàng trong 7 ngày kể từ khi khách hàng xác nhận đã nhận hàng. Sản phẩm và số lượng phải thuộc đơn mua.',
            'active_from' => $now,
            'retired_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('books')
            ->where('format', 'physical')
            ->where('provenance', '!=', 'used_resale')
            ->update(['return_policy_version_id' => $policyId]);
    }

    public function down(): void
    {
        $v2 = DB::table('return_policy_versions')
            ->where('policy_key', 'physical_standard')
            ->where('version', 2)
            ->value('id');
        $v1 = DB::table('return_policy_versions')
            ->where('policy_key', 'physical_standard')
            ->where('version', 1)
            ->value('id');

        if ($v2 && $v1) {
            DB::table('books')->where('return_policy_version_id', $v2)->update([
                'return_policy_version_id' => $v1,
            ]);
        }

        DB::table('return_policy_versions')
            ->where('policy_key', 'physical_standard')
            ->where('version', 2)
            ->delete();
    }
};
