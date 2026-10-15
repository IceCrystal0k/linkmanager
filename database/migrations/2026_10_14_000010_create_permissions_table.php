<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('name');
            $table->string('slug');
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

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
