<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateImageCategoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create the pivot table
        Schema::create('image_category', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('image_id');
            $table->unsignedBigInteger('category_id');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('image_id')->references('id')->on('images')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');

            // Unique constraint to prevent duplicate associations
            $table->unique(['image_id', 'category_id']);
        });

        // Migrate existing category_id data from images table to pivot table
        DB::statement('
            INSERT INTO image_category (image_id, category_id, created_at, updated_at)
            SELECT id, category_id, NOW(), NOW()
            FROM images
            WHERE category_id IS NOT NULL
        ');

        // Drop the category_id column from images table
        Schema::table('images', function (Blueprint $table) {
            $table->dropForeign('galleries_category_id_foreign');
            $table->dropColumn('category_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Re-add category_id column to images table
        Schema::table('images', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->after('description');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
        });

        // Migrate data back from pivot table (take first category if multiple exist)
        DB::statement('
            UPDATE images
            INNER JOIN (
                SELECT image_id, MIN(category_id) as category_id
                FROM image_category
                GROUP BY image_id
            ) as ic ON images.id = ic.image_id
            SET images.category_id = ic.category_id
        ');

        // Drop the pivot table
        Schema::dropIfExists('image_category');
    }
}
