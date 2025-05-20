<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateGalleriesTableForSupabase extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::table('galleries', function (Blueprint $table) {
            // Remove Vercel Blob specific columns
            $table->dropColumn(['blob_url', 'blob_id']);

            // Add Supabase specific columns
            $table->string('storage_path')->after('description');
            $table->string('storage_bucket')->after('storage_path');
            $table->string('storage_url')->after('storage_bucket');
            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn(['storage_path', 'storage_bucket', 'storage_url']);
            $table->string('blob_url');
            $table->string('blob_id');
        });
    }
}
