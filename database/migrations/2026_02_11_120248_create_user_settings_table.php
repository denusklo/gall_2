<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->unique();
            $table->string('storage_provider', 20)->default('supabase');

            // Supabase credentials (encrypted)
            $table->text('supabase_url')->nullable();
            $table->text('supabase_key')->nullable();
            $table->text('supabase_service_key')->nullable();
            $table->string('supabase_bucket', 255)->nullable()->default('images');

            // Vercel Blob credentials (encrypted)
            $table->text('vercel_blob_token')->nullable();
            $table->string('vercel_blob_store_url', 255)->nullable()->default('https://blob.vercel-storage.com');

            // Metadata
            $table->boolean('credentials_verified')->default(false);
            $table->timestamp('last_verified_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_settings');
    }
}
