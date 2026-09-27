<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddStorageJournalLinks extends Migration
{
    public function up()
    {
        // Additive, nullable, no backfill: existing rows keep their current values.
        Schema::table('storage_credentials', function (Blueprint $table) {
            $table->foreignId('storage_account_id')->nullable()->after('user_id')
                ->constrained('storage_accounts')->nullOnDelete();
        });
        Schema::table('images', function (Blueprint $table) {
            $table->foreignId('storage_account_id')->nullable()->after('storage_credential_id')
                ->constrained('storage_accounts')->nullOnDelete();
            $table->foreignId('upload_operation_id')->nullable()->unique()->after('storage_account_id')
                ->constrained('storage_operations')->nullOnDelete();
        });
    }

    public function down()
    {
        if (DB::table('storage_credentials')->whereNotNull('storage_account_id')->exists()
            || DB::table('images')->whereNotNull('storage_account_id')->exists()
            || DB::table('images')->whereNotNull('upload_operation_id')->exists()) {
            throw new RuntimeException('Refusing to drop populated storage journal links.');
        }
        Schema::table('images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('upload_operation_id');
            $table->dropConstrainedForeignId('storage_account_id');
        });
        Schema::table('storage_credentials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storage_account_id');
        });
    }
}
