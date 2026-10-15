<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users_permissions', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('permission_id');
            $table->primary(['user_id', 'permission_id']);
            $table->index('permission_id', 'users_permissions_permission_id_foreign');

            $table->foreign('permission_id', 'users_permissions_permission_id_fk')
                ->references('id')->on('permissions');
            $table->foreign('user_id', 'users_permissions_user_id_fk')
                ->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users_permissions');
    }
};
