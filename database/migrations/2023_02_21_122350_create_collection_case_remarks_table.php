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
        Schema::create('collection_case_remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('remark')->nullable();
            $table->timestamp('remarked_at')->nullable();
            $table->longText('comment')->nullable();
            $table->date('promised_to_pay_at')->nullable();
            $table->date('already_paid_at')->nullable();
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
        Schema::dropIfExists('collection_case_remarks');
    }
};
