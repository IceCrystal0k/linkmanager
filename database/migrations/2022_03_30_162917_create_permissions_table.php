<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name'); // edit posts
            $table->string('slug'); //edit-posts
            $table->timestamps();
        });

        $this->fillPermissions();
    }

    private function fillPermissions()
    {
        $data = [
            [
                'name' => 'Dashboard',
                'slug' => 'dashboard',
            ],
            [
                'name' => 'Profile',
                'slug' => 'profile',
            ],
            [
                'name' => 'Settings',
                'slug' => 'settings',
            ],
            [
                'name' => 'Roles',
                'slug' => 'roles',
            ],
            [
                'name' => 'Permissions',
                'slug' => 'permissions',
            ],
            [
                'name' => 'Users',
                'slug' => 'users',
            ],
            [
                'name' => 'Tasks',
                'slug' => 'tasks',
            ],
            [
                'name' => 'Statistics',
                'slug' => 'statistics',
            ],
        ];
        DB::table('permissions')->insert($data);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('permissions');
    }
}
