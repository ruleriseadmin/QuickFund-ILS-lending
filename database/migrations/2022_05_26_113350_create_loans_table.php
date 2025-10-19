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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('amount_payable');
            $table->bigInteger('amount_remaining');
            $table->string('destination_account_number')->nullable();
            $table->string('destination_bank_code')->nullable();
            $table->string('token')->nullable();
            $table->string('reference_id')->nullable();
            $table->date('due_date');
            $table->date('next_due_date')->nullable();
            $table->unsignedBigInteger('penalty')->nullable();
            $table->unsignedBigInteger('penalty_remaining')->nullable();
            $table->unsignedBigInteger('defaults');
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
        Schema::dropIfExists('loans');
    }
};
