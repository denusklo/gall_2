<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddStorageUrlLongColumnToGalleries extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add new text column
        Schema::table('galleries', function (Blueprint $table) {
            $table->text('storage_url_text')->nullable()->after('storage_url');
        });

        // Copy data from old column to new column
        DB::statement('UPDATE galleries SET storage_url_text = storage_url');

        // Drop old column
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('storage_url');
        });
        
        // Add new storage_url column as TEXT
        Schema::table('galleries', function (Blueprint $table) {
            $table->text('storage_url')->nullable()->after('storage_url_text');
        });
        
        // Copy data back
        DB::statement('UPDATE galleries SET storage_url = storage_url_text');
        
        // Drop temporary column
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('storage_url_text');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // For rollback, convert back to string
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('storage_url_old', 255)->nullable()->after('storage_url');
        });

        DB::statement('UPDATE galleries SET storage_url_old = SUBSTRING(storage_url, 1, 255)');

        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('storage_url');
        });
        
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('storage_url', 255)->nullable()->after('storage_url_old');
        });
        
        DB::statement('UPDATE galleries SET storage_url = storage_url_old');
        
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('storage_url_old');
        });
    }
}