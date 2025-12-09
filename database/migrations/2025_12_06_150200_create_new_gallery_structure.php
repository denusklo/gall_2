<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNewGalleryStructure extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Step 1: Rename existing 'galleries' table to 'images'
        // Foreign keys will be preserved with their original names
        Schema::rename('galleries', 'images');

        // Step 2: Create new 'galleries' table for albums
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('cover_image_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys with explicit names to avoid conflicts
            $table->foreign('cover_image_id', 'galleries_cover_image_fk')
                  ->references('id')
                  ->on('images')
                  ->onDelete('set null');

            $table->foreign('user_id', 'galleries_user_fk')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });

        // Step 3: Create pivot table 'gallery_images'
        Schema::create('gallery_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gallery_id');
            $table->unsignedBigInteger('image_id');
            $table->integer('order')->default(0);
            $table->timestamps();

            // Foreign keys
            $table->foreign('gallery_id')
                  ->references('id')
                  ->on('galleries')
                  ->onDelete('cascade');

            $table->foreign('image_id')
                  ->references('id')
                  ->on('images')
                  ->onDelete('cascade');

            // Unique constraint - an image can only be added once to a gallery
            $table->unique(['gallery_id', 'image_id'], 'unique_gallery_image');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop new tables first (to avoid foreign key constraints)
        Schema::dropIfExists('gallery_images');
        Schema::dropIfExists('galleries');

        // Rename images table back to galleries
        // Foreign keys will be preserved with their original names
        Schema::rename('images', 'galleries');
    }
}
