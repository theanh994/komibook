<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('data_mode', 32)->default('real')->after('status')->index();
            $table->string('public_source_url')->nullable()->after('website');
            $table->date('public_source_checked_at')->nullable()->after('public_source_url');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('primary_organization_id')->index();
            $table->string('demo_wallet_code', 64)->nullable()->after('is_demo')->unique();
        });

        Schema::table('vendor_organization_relationships', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('status')->index();
            $table->string('evidence_mode', 32)->default('real_document')->after('is_demo');
            $table->string('demo_reference', 128)->nullable()->after('evidence_document');
        });

        Schema::table('organization_distribution_agreements', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('status')->index();
            $table->string('evidence_mode', 32)->default('real_document')->after('is_demo');
            $table->string('demo_reference', 128)->nullable()->after('evidence_document');
        });

        $demoOrganizationIds = DB::table('organizations')
            ->where('slug', 'like', '%-demo')
            ->pluck('id');

        if ($demoOrganizationIds->isNotEmpty()) {
            DB::table('organizations')->whereIn('id', $demoOrganizationIds)->update([
                'data_mode' => 'demo',
                'status' => 'demo_accepted',
                'verified_by' => null,
                'verified_at' => null,
                'last_review_reason' => 'Dữ liệu mô phỏng phục vụ trình diễn; không phải xác minh pháp lý.',
            ]);

            DB::table('vendors')->whereIn('primary_organization_id', $demoOrganizationIds)->orderBy('id')->each(function ($vendor) {
                DB::table('vendors')->where('id', $vendor->id)->update([
                    'is_demo' => true,
                    'demo_wallet_code' => 'DEMO-VENDOR-'.str_pad((string) $vendor->id, 4, '0', STR_PAD_LEFT),
                    'payout_bank_status' => 'demo_disabled',
                    'payout_bank_verified_at' => null,
                    'payout_bank_verified_by' => null,
                ]);
            });

            DB::table('vendor_organization_relationships')
                ->whereIn('organization_id', $demoOrganizationIds)
                ->orderBy('id')
                ->each(function ($relationship) {
                    DB::table('vendor_organization_relationships')->where('id', $relationship->id)->update([
                        'is_demo' => true,
                        'evidence_mode' => 'demo_statement',
                        'demo_reference' => 'DEMO-REL-'.str_pad((string) $relationship->id, 6, '0', STR_PAD_LEFT),
                        'status' => 'demo_accepted',
                        'verified_at' => null,
                        'reviewed_by' => null,
                        'last_review_reason' => 'Quan hệ mô phỏng phục vụ báo cáo và kiểm thử nghiệp vụ.',
                    ]);
                });

            DB::table('organization_distribution_agreements')
                ->whereIn('publisher_organization_id', $demoOrganizationIds)
                ->whereIn('distributor_organization_id', $demoOrganizationIds)
                ->orderBy('id')
                ->each(function ($agreement) {
                    DB::table('organization_distribution_agreements')->where('id', $agreement->id)->update([
                        'is_demo' => true,
                        'evidence_mode' => 'demo_statement',
                        'demo_reference' => 'DEMO-AGR-'.str_pad((string) $agreement->id, 6, '0', STR_PAD_LEFT),
                        'status' => 'demo_accepted',
                        'verified_at' => null,
                        'reviewed_by' => null,
                        'last_review_reason' => 'Thỏa thuận mô phỏng; không có giá trị pháp lý.',
                    ]);
                });

            $this->createDemoPartnership('ipm-demo', 'nxb-lao-dong-demo');
            $this->createDemoPartnership('ipm-demo', 'nxb-ha-noi-demo');
            $this->createDemoPartnership('fahasa-demo', 'nxb-kim-dong-demo');
            $this->createDemoPartnership('fahasa-demo', 'nxb-tre-demo');
            $this->createDemoPartnership('fahasa-demo', 'nxb-giao-duc-demo');
        }
    }

    public function down(): void
    {
        DB::table('organization_distribution_agreements')->where('operation_key', 'like', 'demo-mapping:%')->delete();
        DB::table('vendor_organization_relationships')->where('operation_key', 'like', 'demo-mapping:%')->delete();
        DB::table('organization_distribution_agreements')->where('is_demo', true)->update(['status' => 'draft']);
        DB::table('vendor_organization_relationships')->where('is_demo', true)->update(['status' => 'draft']);
        DB::table('organizations')->where('data_mode', 'demo')->update(['status' => 'draft']);
        DB::table('vendors')->where('is_demo', true)->update(['payout_bank_status' => 'unverified']);

        Schema::table('organization_distribution_agreements', function (Blueprint $table) {
            $table->dropIndex(['is_demo']);
            $table->dropColumn(['is_demo', 'evidence_mode', 'demo_reference']);
        });
        Schema::table('vendor_organization_relationships', function (Blueprint $table) {
            $table->dropIndex(['is_demo']);
            $table->dropColumn(['is_demo', 'evidence_mode', 'demo_reference']);
        });
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropUnique(['demo_wallet_code']);
            $table->dropIndex(['is_demo']);
            $table->dropColumn(['is_demo', 'demo_wallet_code']);
        });
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex(['data_mode']);
            $table->dropColumn(['data_mode', 'public_source_url', 'public_source_checked_at']);
        });
    }

    private function createDemoPartnership(string $distributorSlug, string $publisherSlug): void
    {
        $distributor = DB::table('organizations')->where('slug', $distributorSlug)->first();
        $publisher = DB::table('organizations')->where('slug', $publisherSlug)->first();
        if (! $distributor || ! $publisher) {
            return;
        }
        $vendor = DB::table('vendors')->where('primary_organization_id', $distributor->id)->first();
        if (! $vendor) {
            return;
        }

        $reference = strtoupper(str_replace('-demo', '', $distributorSlug).'-'.str_replace('-demo', '', $publisherSlug));
        DB::table('vendor_organization_relationships')->updateOrInsert(
            ['operation_key' => "demo-mapping:relationship:{$distributorSlug}:{$publisherSlug}"],
            [
                'vendor_id' => $vendor->id,
                'organization_id' => $publisher->id,
                'role' => 'publisher_partner',
                'status' => 'demo_accepted',
                'is_demo' => true,
                'evidence_mode' => 'demo_statement',
                'demo_reference' => "DEMO-REL-{$reference}",
                'scope' => json_encode(['coverage' => 'catalog', 'notice' => 'simulated']),
                'last_review_reason' => 'Quan hệ mô phỏng phục vụ báo cáo và kiểm thử nghiệp vụ.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('organization_distribution_agreements')->updateOrInsert(
            ['operation_key' => "demo-mapping:agreement:{$distributorSlug}:{$publisherSlug}"],
            [
                'publisher_organization_id' => $publisher->id,
                'distributor_organization_id' => $distributor->id,
                'status' => 'demo_accepted',
                'is_demo' => true,
                'evidence_mode' => 'demo_statement',
                'demo_reference' => "DEMO-AGR-{$reference}",
                'scope' => json_encode(['coverage' => 'catalog', 'notice' => 'simulated']),
                'last_review_reason' => 'Thỏa thuận mô phỏng; không có giá trị pháp lý.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
};
