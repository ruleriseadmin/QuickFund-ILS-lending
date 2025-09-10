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
        Schema::create('loan_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('interest');
            $table->string('default_interest');
            $table->json('fees')->nullable();
            $table->integer('tenure');
            $table->string('currency');
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('default_fees_addition_days');
            $table->string('status');
            $table->string('channel_code')->nullable();
            $table->timestamp('last_requeried_at')->nullable();
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
        Schema::dropIfExists('loan_offers');
    }
};
