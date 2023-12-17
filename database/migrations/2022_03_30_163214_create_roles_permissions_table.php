<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRolesPermissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('roles_permissions', function (Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('permission_id');

            // foreign key constraints
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');

            $table->primary(['role_id', 'permission_id']);
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

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('roles_permissions');
    }
}
