<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->unsignedInteger('order_index');
            $table->tinyInteger('enabled')->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
