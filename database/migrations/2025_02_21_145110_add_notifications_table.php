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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type'); // 'appointment_booking', 'appointment_accepted', 'medical_record_created', 'message'
            $table->string('title');
            $table->text('message');
            $table->unsignedBigInteger('related_id')->nullable(); // ID of related entity (appointment, medical_record, message, etc.)
            $table->string('related_type')->nullable(); // Type of related entity (Appointment, MedicalRecord, Message, etc.)
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            // Index for faster queries
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
