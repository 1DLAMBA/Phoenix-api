<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow stub doctor/nurse/other_professional rows before profile completion.
     */
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('license_number')->nullable()->change();
            $table->string('med_school')->nullable()->change();
            $table->string('specialization')->nullable()->change();
            $table->integer('grad_year')->nullable()->change();
        });

        Schema::table('nurses', function (Blueprint $table) {
            $table->string('license_number')->nullable()->change();
            $table->string('med_school')->nullable()->change();
            $table->string('specialization')->nullable()->change();
            $table->integer('grad_year')->nullable()->change();
        });

        Schema::table('other_professionals', function (Blueprint $table) {
            $table->string('professional_type')->nullable()->change();
            $table->string('med_school')->nullable()->change();
            $table->string('specialization')->nullable()->change();
            $table->integer('grad_year')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('license_number')->nullable(false)->change();
            $table->string('med_school')->nullable(false)->change();
            $table->string('specialization')->nullable(false)->change();
            $table->integer('grad_year')->nullable(false)->change();
        });

        Schema::table('nurses', function (Blueprint $table) {
            $table->string('license_number')->nullable(false)->change();
            $table->string('med_school')->nullable(false)->change();
            $table->string('specialization')->nullable(false)->change();
            $table->integer('grad_year')->nullable(false)->change();
        });

        Schema::table('other_professionals', function (Blueprint $table) {
            $table->string('professional_type')->nullable(false)->change();
            $table->string('med_school')->nullable(false)->change();
            $table->string('specialization')->nullable(false)->change();
            $table->integer('grad_year')->nullable(false)->change();
        });
    }
};
