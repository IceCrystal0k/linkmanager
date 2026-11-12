<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('currency', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->increments('id');
            $table->string('name', 50);
            $table->string('flag', 60);
            $table->string('iso_code', 3);
            $table->string('html_code', 10);
            $table->string('symbol', 10);
            $table->unsignedTinyInteger('status');
        });
        $this->fillCurrencyTable();
    }

    private function fillCurrencyTable()
    {
        $data = [
            [
                'id' => 1,
                'name' => 'Euro',
                'flag' => 'european-union.svg',
                'iso_code' => 'EUR',
                'html_code' => '&euro;',
                'status' => 1,
                'symbol' => '€',
            ],
            [
                'id' => 2,
                'name' => 'US dollar',
                'flag' => 'united-states.svg',
                'iso_code' => 'USD',
                'html_code' => '$',
                'status' => 1,
                'symbol' => '$',
            ],
            [
                'id' => 3,
                'name' => 'Romanian LEU',
                'flag' => 'romania.svg',
                'iso_code' => 'RON',
                'html_code' => 'LEI',
                'status' => 1,
                'symbol' => 'LEI',
            ],
            [
                'id' => 4,
                'name' => 'British pound',
                'flag' => 'united-kingdom.svg',
                'iso_code' => 'GBP',
                'html_code' => '&pound;',
                'status' => 1,
                'symbol' => '£',
            ],
        ];
        DB::table('currency')->insert($data);
    }

    public function down(): void
    {
        Schema::dropIfExists('currency');
    }
};
