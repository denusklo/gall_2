<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateStorageOperationsTable extends Migration
{
    public function up()
    {
        Schema::create('storage_operations', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storage_account_id')->constrained('storage_accounts')->cascadeOnDelete();
            $table->foreignId('storage_credential_id')->nullable()->constrained('storage_credentials')->nullOnDelete();
            $table->foreignId('image_id')->nullable()->constrained('images')->nullOnDelete();
            // One lifetime delete intent per image; retries reuse it.
            $table->unsignedBigInteger('delete_image_id')->nullable()->unique();
            $table->string('kind', 16);
            $table->string('state', 32);
            $table->string('namespace', 32);
            $table->string('provider', 16);
            $table->string('bucket', 191);
            $table->string('path', 191);
            $table->char('object_hash', 64);
            $table->char('upload_object_hash', 64)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('expected_size')->nullable();
            $table->string('expected_mime', 127)->nullable();
            $table->unsignedBigInteger('observed_size')->nullable();
            $table->string('observed_mime', 127)->nullable();
            $table->string('provider_version', 128)->nullable();
            $table->string('checksum_state', 16)->default('unverified');
            $table->timestamp('authorization_expires_at')->nullable();
            $table->timestamp('tombstone_until')->nullable();
            $table->timestamp('remote_started_at')->nullable();
            $table->timestamp('last_observed_at')->nullable();
            $table->timestamp('not_before')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('last_error_code', 64)->nullable();
            $table->char('lease_token', 36)->nullable();
            $table->unsignedInteger('lease_generation')->default(0);
            $table->timestamp('lease_expires_at')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->index(['state', 'not_before', 'id']);
            $table->index(['user_id', 'state']);
            $table->index(['storage_account_id', 'state']);
            $table->index('object_hash');
        });
    }

    public function down()
    {
        if (DB::table('storage_operations')->exists()) {
            throw new RuntimeException('Refusing to drop a populated storage_operations journal.');
        }
        Schema::dropIfExists('storage_operations');
    }
}
