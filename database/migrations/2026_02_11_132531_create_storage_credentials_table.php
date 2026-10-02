<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStorageCredentialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('storage_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Auto-generated random name (e.g., "crimson-wolf", "sapphire-dragon")
            $table->string('name')->unique();

            // Provider type
            $table->enum('provider', ['supabase', 'vercel']);

            // Default credential for uploads (only one per user should be true)
            $table->boolean('is_default')->default(false);

            // Supabase credentials (encrypted via model cast)
            $table->text('supabase_url')->nullable();
            $table->text('supabase_key')->nullable();
            $table->text('supabase_service_key')->nullable();
            $table->string('supabase_bucket')->nullable()->default('images');

            // Vercel Blob credentials (encrypted via model cast)
            $table->text('vercel_blob_token')->nullable();
            $table->string('vercel_blob_store_url')->nullable()->default('https://blob.vercel-storage.com');

            // Verification status
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['user_id', 'provider']);
            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('storage_credentials');
    }
}
