<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('first_centrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->json('subject_list')->nullable();
            $table->json('personal_details_summary')->nullable();
            $table->json('scoring')->nullable();
            $table->json('credit_summary')->nullable();
            $table->json('performance_classification')->nullable();
            $table->json('enquiry_details')->nullable();
            $table->string('passes_recent_check')->nullable();
            $table->integer('total_delinquencies')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('first_centrals');
    }
};
