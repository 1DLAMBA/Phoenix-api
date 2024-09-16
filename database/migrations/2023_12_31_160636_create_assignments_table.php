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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assigned_doctor_id')->constrained('doctors');
            $table->foreignId('assigned_nurse_id')->constrained('nurses');
            $table->foreignId('assigned_client_id')->constrained('clients');
            $table->string('status');
            $table->string('assignment_message');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
