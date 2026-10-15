<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_infos', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('avatar')->nullable();
            $table->unsignedInteger('timezone')->nullable();
            $table->unsignedInteger('date_format')->nullable();
            $table->unsignedInteger('date_format_separator')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_infos');
    }
};
