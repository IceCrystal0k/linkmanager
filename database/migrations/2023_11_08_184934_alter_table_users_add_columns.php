<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class AlterTableUsersAddColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->string('google_id')->nullable();
            $table->string('fb_id')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->unsignedTinyInteger('payment_status')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function ($table) {
            $table->dropColumn('google_id');
            $table->dropColumn('fb_id');
            $table->dropColumn('status');
            $table->dropColumn('payment_status');
        });
    }
}