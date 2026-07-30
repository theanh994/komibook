<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('author_commerce_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->json('capabilities');
            $table->timestamp('activated_at');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_commerce_profiles');
    }
};
