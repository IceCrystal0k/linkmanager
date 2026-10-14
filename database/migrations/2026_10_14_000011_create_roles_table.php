<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
        });
		
		$this->fillRoles();
    }
	
	private function fillRoles()
    {
        $data = [
            [
                'name' => 'Administrator',
                'slug' => 'admin',
            ],
            [
                'name' => 'User',
                'slug' => 'user',
            ],
        ];
        DB::table('roles')->insert($data);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
