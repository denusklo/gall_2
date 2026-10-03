<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_change_audits', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('actor_uid', 128);
            $table->string('target_uid', 128)->index();
            $table->string('action', 32);
            $table->boolean('before_admin');
            $table->boolean('before_owner');
            $table->boolean('after_admin')->nullable();
            $table->boolean('after_owner')->nullable();
            $table->string('status', 16)->index(); // pending | succeeded | failed
            $table->string('reason', 64)->nullable(); // short safe code only, never provider text
            $table->timestamps();
        });

        // Single stable row that every role mutation locks (SELECT ... FOR UPDATE
        // on a dedicated connection) so mutations serialise across instances.
        Schema::create('role_mutation_lock', function (Blueprint $table) {
            $table->engine = 'InnoDB'; // row locks require a transactional engine
            $table->unsignedTinyInteger('id')->primary();
            $table->timestamp('seeded_at')->nullable();
        });
        DB::table('role_mutation_lock')->insert(['id' => 1, 'seeded_at' => now()]);
    }

    public function down(): void
    {
        // Audit records are evidence: refuse to roll back while any exist.
        if (Schema::hasTable('role_change_audits') && DB::table('role_change_audits')->exists()) {
            throw new \RuntimeException('Refusing to drop populated role_change_audits table.');
        }
        Schema::dropIfExists('role_mutation_lock');
        Schema::dropIfExists('role_change_audits');
    }
};
