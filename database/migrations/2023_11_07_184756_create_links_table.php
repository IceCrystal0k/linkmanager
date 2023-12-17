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
        Schema::create('links', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('category_id');
            $table->string('name', 100);
            $table->string('url');
            $table->string('description')->nullable();
            $table->text('comment')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->unsignedInteger('visits')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->unsignedTinyInteger('verification_status')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('auth_info')->nullable();
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
        Schema::dropIfExists('links');
    }
};
