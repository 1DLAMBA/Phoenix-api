<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds consultation-payment fields to appointments.
     *
     * payment_status lifecycle:
     *  - not_required : professional has no consultation fee configured (free consultation)
     *  - unpaid       : fee is due (set when the professional accepts a paid appointment)
     *  - pending      : a Paystack payment has been initialized, awaiting completion
     *  - paid         : payment verified/settled (85% professional, 15% platform)
     *  - failed       : the last payment attempt failed
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // Amount the client pays (snapshot of the professional's fee at acceptance time)
            $table->decimal('amount', 10, 2)->nullable()->after('symptoms');
            // Platform share (15%) and professional share (85%) of the amount
            $table->decimal('platform_fee', 10, 2)->nullable()->after('amount');
            $table->decimal('professional_amount', 10, 2)->nullable()->after('platform_fee');
            $table->string('payment_status')->default('not_required')->after('professional_amount');
            $table->string('payment_reference')->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_reference');

            $table->index('payment_reference');
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['payment_reference']);
            $table->dropIndex(['payment_status']);
            $table->dropColumn([
                'amount',
                'platform_fee',
                'professional_amount',
                'payment_status',
                'payment_reference',
                'paid_at',
            ]);
        });
    }
};
