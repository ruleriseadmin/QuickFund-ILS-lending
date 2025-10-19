<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->timestamp('first_central_reported_at')->nullable()->after('next_due_date');
            $table->timestamp('crc_reported_at')->nullable()->after('first_central_reported_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('first_central_reported_at');
            $table->dropColumn('crc_reported_at');
        });
    }
};
