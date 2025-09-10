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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->index()->nullable();
            $table->string('phone_number')->unique();
            $table->string('hashed_phone_number')->nullable();
            $table->string('encrypted_pan')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('bvn')->index()->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('account_number')->nullable();
            $table->string('bank_code')->nullable();
            $table->string('gender')->nullable();
            $table->string('country_code')->nullable();
            $table->date('crc_check_last_requested_at')->nullable();
            $table->date('first_central_check_last_requested_at')->nullable();
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
        Schema::dropIfExists('customers');
    }
};
