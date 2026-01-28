<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFirebaseSyncFieldsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Add Firebase UID to link Firebase users
            $table->string('firebase_uid')->nullable()->unique()->after('id');
            // Store Firebase ID token for API access
            $table->text('firebase_id_token')->nullable()->after('firebase_uid');
            // Track which auth provider was used
            $table->enum('auth_provider', ['firebase', 'sanctum', 'both'])->default('sanctum')->after('firebase_id_token');
            // Firebase refresh token (optional)
            $table->text('firebase_refresh_token')->nullable()->after('auth_provider');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['firebase_uid', 'firebase_id_token', 'auth_provider', 'firebase_refresh_token']);
        });
    }
}
