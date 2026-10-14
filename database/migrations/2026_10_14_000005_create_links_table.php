<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('links', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->unsignedInteger('user_id');
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

    public function down(): void
    {
        Schema::dropIfExists('links');
    }
};
