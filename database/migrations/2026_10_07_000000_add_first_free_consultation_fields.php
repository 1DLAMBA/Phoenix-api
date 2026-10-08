<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "First consultation free": each client's first otherwise-chargeable consultation
     * is free. Tracked by a one-time credit flag on the client, and marked on the
     * appointment that consumed it.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('free_consultation_used')->default(false)->after('date_of_birth');
            $table->timestamp('free_consultation_used_at')->nullable()->after('free_consultation_used');
            $table->unsignedBigInteger('free_consultation_appointment_id')->nullable()->after('free_consultation_used_at');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('is_free_first')->default(false)->after('payment_status');
        });

        // Backfill: any client who has already PAID for a consultation has effectively
        // used their first-timer credit, so the promo only benefits genuine first-timers.
        DB::table('clients')->whereIn('id', function ($q) {
            $q->select('client_id')->from('appointments')->where('payment_status', 'paid');
        })->update([
            'free_consultation_used' => true,
            'free_consultation_used_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['free_consultation_used', 'free_consultation_used_at', 'free_consultation_appointment_id']);
        });
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('is_free_first');
        });
    }
};
