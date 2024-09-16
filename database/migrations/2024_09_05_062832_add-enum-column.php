<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       //
       Schema::table('messages', function (Blueprint $table) {
        $table->enum('status', ['pending', 'approved', 'rejected'])
              ->default('pending');
        // $table->unsignedBigInteger('conversation_id');
    });

    // Step 2: Rename the `content` column to `message`
    Schema::table('messages', function (Blueprint $table) {
        // Using raw DB statement to rename the column since MariaDB doesn't support `renameColumn` directly
        DB::statement('ALTER TABLE messages CHANGE content message TEXT');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('messages', function (Blueprint $table) {
            // Revert the column back to varchar
            $table->string('status')->change();
        });
    }
};
