<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('nurses', function (Blueprint $table) {
            $table->string('signature')->nullable()->after('degree_file');
            $table->string('id_card')->nullable()->after('signature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nurses', function (Blueprint $table) {
            $table->dropColumn(['signature', 'id_card']);
        });
    }
};
