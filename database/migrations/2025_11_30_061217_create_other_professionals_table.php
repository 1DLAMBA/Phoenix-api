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
        Schema::create('other_professionals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('professional_type'); // e.g., "Public Health", "Physiologist", etc.
            $table->string('license_number')->nullable(); // Optional for other professionals
            $table->string('med_school');
            $table->string('specialization');
            $table->integer('grad_year');
            $table->string('degree_file')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('other_professionals');
    }
};
