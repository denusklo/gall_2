<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoriesTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    // database/migrations/xxxx_xx_xx_create_categories_table.php
    public function up() {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Add category_id column to galleries table
        Schema::table('galleries', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down() {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
        Schema::dropIfExists('categories');
    }
}
