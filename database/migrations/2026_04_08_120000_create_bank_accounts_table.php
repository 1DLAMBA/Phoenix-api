<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_name');
            $table->text('account_number'); // encrypted
            $table->string('bank_code');
            $table->string('bank_name');
            $table->string('paystack_subaccount_code')->nullable();
            $table->decimal('consultation_fee', 10, 2)->default(0);
            $table->json('paystack_response')->nullable();
            $table->morphs('professionable'); // professionable_id, professionable_type
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
