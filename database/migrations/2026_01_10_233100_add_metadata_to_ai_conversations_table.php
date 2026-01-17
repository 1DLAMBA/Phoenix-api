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
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->integer('token_count')->nullable()->after('last_message');
            $table->string('query_type')->nullable()->after('token_count');
            $table->decimal('temperature_used', 3, 2)->nullable()->after('query_type');
            $table->string('model_version')->nullable()->after('temperature_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn(['token_count', 'query_type', 'temperature_used', 'model_version']);
        });
    }
};
