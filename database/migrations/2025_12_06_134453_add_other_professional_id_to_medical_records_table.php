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
        Schema::table('medical_records', function (Blueprint $table) {
            // Make assigned_doctor_id nullable to support other_professionals
            $table->foreignId('assigned_doctor_id')->nullable()->change();
            // Add other_professional_id column
            $table->foreignId('other_professional_id')->nullable()->after('assigned_doctor_id')->constrained('other_professionals')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropForeign(['other_professional_id']);
            $table->dropColumn('other_professional_id');
            // Revert assigned_doctor_id to not nullable (if needed)
            $table->foreignId('assigned_doctor_id')->nullable(false)->change();
        });
    }
};
