<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStorageCredentialIdToImagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('images', function (Blueprint $table) {
            // Which storage credential this image was uploaded with.
            // Nullable: legacy images and default-credential uploads leave it null
            // and resolve to the user's default credential at read time.
            // nullOnDelete so removing a credential doesn't delete the image;
            // reads then fall back to the default credential.
            $table->foreignId('storage_credential_id')
                ->nullable()
                ->after('storage_provider')
                ->constrained('storage_credentials')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storage_credential_id');
        });
    }
}
