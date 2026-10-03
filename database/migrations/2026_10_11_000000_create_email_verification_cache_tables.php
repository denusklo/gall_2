<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verification_cache', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });
        Schema::create('email_verification_cache_locks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_cache_locks');
        Schema::dropIfExists('email_verification_cache');
    }
};
