<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('used_book_seller_profiles')) {
            Schema::create('used_book_seller_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('catalog_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->string('status', 24)->default('active')->index();
                $table->json('capabilities');
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->text('last_reason')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('seller_fulfillment_addresses')) {
            Schema::create('seller_fulfillment_addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('recipient_name');
                $table->text('phone');
                $table->text('address_line');
                $table->string('ward')->nullable();
                $table->string('district')->nullable();
                $table->string('province');
                $table->string('postal_code', 20)->nullable();
                $table->string('status', 20)->default('verified');
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('retired_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
            });
        }

        if (! Schema::hasColumn('used_book_listings', 'seller_user_id')) {
            Schema::table('used_book_listings', function (Blueprint $table) {
                $table->foreignId('seller_user_id')->nullable()->after('book_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('seller_fulfillment_address_id')->nullable()->after('seller_user_id')
                    ->constrained('seller_fulfillment_addresses')->restrictOnDelete();
                $table->index(['seller_user_id', 'status']);
            });
        }

        if (! Schema::hasColumn('articles', 'created_by')) {
            Schema::table('articles', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->after('id')->constrained('users')->restrictOnDelete();
            });
        }

        if (Schema::hasTable('authors')) {
            $addressMap = [];
            $legacyAddresses = DB::table('author_fulfillment_addresses')
                ->join('authors', 'authors.id', '=', 'author_fulfillment_addresses.author_id')
                ->select('author_fulfillment_addresses.*', 'authors.user_id')
                ->orderBy('author_fulfillment_addresses.id')
                ->get();

            foreach ($legacyAddresses as $address) {
                $addressPayload = [
                    'user_id' => $address->user_id,
                    'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone,
                    'address_line' => $address->address_line,
                    'ward' => $address->ward,
                    'district' => $address->district,
                    'province' => $address->province,
                    'postal_code' => $address->postal_code,
                    'status' => $address->status,
                    'verified_at' => $address->verified_at,
                    'verified_by' => $address->verified_by,
                    'retired_at' => $address->retired_at,
                    'created_at' => $address->created_at,
                    'updated_at' => $address->updated_at,
                ];
                $newId = DB::table('seller_fulfillment_addresses')
                    ->where('user_id', $address->user_id)
                    ->where('recipient_name', $address->recipient_name)
                    ->where('phone', $address->phone)
                    ->where('address_line', $address->address_line)
                    ->where('province', $address->province)
                    ->value('id');
                $newId ??= DB::table('seller_fulfillment_addresses')->insertGetId($addressPayload);
                $addressMap[$address->id] = $newId;
            }

            $legacySellers = DB::table('used_book_listings')
                ->join('authors', 'authors.id', '=', 'used_book_listings.author_id')
                ->select('authors.id as legacy_profile_id', 'authors.user_id')
                ->distinct()
                ->get();

            foreach ($legacySellers as $seller) {
                $vendorId = Schema::hasTable('author_commerce_profiles')
                    ? DB::table('author_commerce_profiles')->where('author_id', $seller->legacy_profile_id)->value('vendor_id')
                    : null;
                $vendorId ??= DB::table('vendors')->where('user_id', $seller->user_id)->value('id');

                DB::table('used_book_seller_profiles')->updateOrInsert(
                    ['user_id' => $seller->user_id],
                    [
                        'catalog_vendor_id' => $vendorId,
                        'status' => 'active',
                        'capabilities' => json_encode(['used_resale']),
                        'activated_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                DB::table('used_book_listings')
                    ->where('author_id', $seller->legacy_profile_id)
                    ->update(['seller_user_id' => $seller->user_id]);
            }

            foreach ($addressMap as $legacyId => $newId) {
                DB::table('used_book_listings')
                    ->where('fulfillment_address_id', $legacyId)
                    ->update(['seller_fulfillment_address_id' => $newId]);
            }

            if (DB::table('used_book_listings')->whereNull('seller_user_id')->orWhereNull('seller_fulfillment_address_id')->exists()) {
                throw new RuntimeException('Không thể chuyển toàn bộ listing sách cũ sang chủ sở hữu trung tính.');
            }
        }

        DB::table('articles')->update(['created_by' => DB::raw('author_id')]);
        DB::table('books')->where('fulfillment_mode', 'author_registered_address')
            ->update(['fulfillment_mode' => 'seller_verified_address']);

        Schema::table('used_book_listings', function (Blueprint $table) {
            $table->dropForeign(['fulfillment_address_id']);
            $table->dropForeign(['author_id']);
            $table->dropIndex(['author_id', 'status']);
            $table->dropColumn(['fulfillment_address_id', 'author_id']);
        });
    }

    public function down(): void
    {
        DB::table('books')->where('fulfillment_mode', 'seller_verified_address')
            ->update(['fulfillment_mode' => 'author_registered_address']);

        Schema::table('used_book_listings', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->after('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fulfillment_address_id')->nullable()->after('author_id')
                ->constrained('author_fulfillment_addresses')->restrictOnDelete();
            $table->index(['author_id', 'status']);
        });

        if (Schema::hasTable('authors')) {
            $profiles = DB::table('authors')->pluck('id', 'user_id');
            foreach ($profiles as $userId => $profileId) {
                DB::table('used_book_listings')->where('seller_user_id', $userId)->update(['author_id' => $profileId]);
            }

            $legacyAddresses = DB::table('author_fulfillment_addresses')
                ->join('authors', 'authors.id', '=', 'author_fulfillment_addresses.author_id')
                ->select('author_fulfillment_addresses.id', 'authors.user_id')
                ->latest('author_fulfillment_addresses.id')
                ->get()
                ->unique('user_id');
            foreach ($legacyAddresses as $address) {
                DB::table('used_book_listings')->where('seller_user_id', $address->user_id)
                    ->update(['fulfillment_address_id' => $address->id]);
            }
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });

        Schema::table('used_book_listings', function (Blueprint $table) {
            $table->dropForeign(['seller_fulfillment_address_id']);
            $table->dropForeign(['seller_user_id']);
            $table->dropIndex(['seller_user_id', 'status']);
            $table->dropColumn(['seller_fulfillment_address_id', 'seller_user_id']);
        });

        Schema::dropIfExists('seller_fulfillment_addresses');
        Schema::dropIfExists('used_book_seller_profiles');
    }
};
