<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateStorageAccountsTable extends Migration
{
    public function up()
    {
        Schema::create('storage_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 16);
            // Supabase project host or Vercel store id. Never a key or token.
            $table->string('identity_key', 191);
            $table->char('identity_hash', 64);
            $table->boolean('trusted_hosted')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'provider', 'identity_hash']);
            $table->index('identity_hash');
        });
    }

    public function down()
    {
        if (DB::table('storage_accounts')->exists()) {
            throw new RuntimeException('Refusing to drop a populated storage_accounts journal.');
        }
        Schema::dropIfExists('storage_accounts');
    }
}
