<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateStorageMaintenanceRunsTable extends Migration
{
    public function up()
    {
        Schema::create('storage_maintenance_runs', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('kind', 16);
            $table->string('scope_key', 191);
            $table->string('slot_key', 64);
            $table->string('status', 16);
            $table->char('lease_token', 36)->nullable();
            $table->unsignedInteger('lease_generation')->default(0);
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('counters')->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->timestamps();
            $table->unique(['kind', 'scope_key', 'slot_key']);
        });
    }

    public function down()
    {
        if (DB::table('storage_maintenance_runs')->exists()) {
            throw new RuntimeException('Refusing to drop populated storage_maintenance_runs.');
        }
        Schema::dropIfExists('storage_maintenance_runs');
    }
}
