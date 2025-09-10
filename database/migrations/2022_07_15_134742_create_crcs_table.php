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
        Schema::create('crcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->json('summary_of_performance')->nullable();
            $table->json('bvn_report_detail')->nullable();
            $table->json('credit_score_details')->nullable();
            $table->json('credit_facilities_summary')->nullable();
            $table->json('contact_history')->nullable();
            $table->json('address_history')->nullable();
            $table->json('classification_institution_type')->nullable();
            $table->json('classification_product_type')->nullable();
            $table->json('profile_details')->nullable();
            $table->json('header')->nullable();
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
        Schema::dropIfExists('crcs');
    }
};
