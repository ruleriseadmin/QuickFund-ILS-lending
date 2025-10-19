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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('amount');
            $table->string('interest');
            $table->string('default_interest');
            $table->json('fees')->nullable();
            $table->integer('tenure');
            $table->integer('cycles')->nullable();
            $table->string('currency');
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('default_fees_addition_days');
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
        Schema::dropIfExists('offers');
    }
};
