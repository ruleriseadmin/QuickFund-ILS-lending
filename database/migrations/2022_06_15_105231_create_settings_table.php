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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('minimum_loan_amount');
            $table->unsignedBigInteger('maximum_loan_amount');
            $table->json('loan_tenures');
            $table->string('percentage_increase_for_loyal_customers');
            $table->string('loan_interest');
            $table->string('default_interest');
            $table->unsignedBigInteger('days_to_attach_late_payment_fees');
            $table->boolean('use_credit_score_check');
            $table->boolean('use_crc_check');
            $table->boolean('use_first_central_check');
            $table->string('minimum_credit_score');
            $table->bigInteger('days_to_make_crc_check');
            $table->bigInteger('days_to_make_first_central_check');
            $table->unsignedBigInteger('total_amount_credited_per_day');
            $table->bigInteger('maximum_amount_for_first_timers');
            $table->boolean('use_crc_credit_score_check')->nullable();
            $table->boolean('use_first_central_credit_score_check')->nullable(); 
            $table->integer('minimum_credit_bureau_credit_score')->nullable();
            $table->integer('maximum_outstanding_loans_to_qualify')->nullable();
            $table->boolean('should_give_loans');
            $table->json('emails_to_report')->nullable();
            $table->json('bucket_offers')->nullable();
            $table->integer('days_to_stop_penalty_from_accruing')->nullable();
            $table->integer('minimum_days_for_demotion')->nullable();
            $table->integer('maximum_days_for_demotion')->nullable();
            $table->integer('days_to_blacklist_customer')->nullable();
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
        Schema::dropIfExists('settings');
    }
};
