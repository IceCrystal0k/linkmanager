<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles_permissions', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('permission_id');
            $table->primary(['role_id', 'permission_id']);
            $table->index('permission_id', 'roles_permissions_permission_id_foreign');

            $table->foreign('permission_id', 'roles_permissions_permission_id_fk')
                ->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('role_id', 'roles_permissions_role_id_fk')
                ->references('id')->on('roles')->onDelete('cascade');
        });
		
		$this->fillRolesPermissions();
    }
	
	 private function fillRolesPermissions()
    {
        // fill roles permissions
        // admin role has all permissions
        // user role doesn't have permissions to admin pages
        $data = [];
        for ($roleId = 1; $roleId <= 2; $roleId++) {
            for ($permissionId = 1; $permissionId <= 8; $permissionId++) {
                if ($roleId === 1 || ($roleId === 2 && ($permissionId < 4 || $permissionId > 6))) {
                    array_push($data, ['role_id' => $roleId, 'permission_id' => $permissionId]);
                }
            }
        }
        DB::table('roles_permissions')->insert($data);
    }


    public function down(): void
    {
        Schema::dropIfExists('roles_permissions');
    }
};
