<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeToken extends Model
{
    use HasFactory;

    public $timestamps = false;

     /**
     * The attributes that are mass assignable. I.E. when using model::create(['name' => 'some])
     *
     * @var array
     */
    protected $fillable = [
        'name', 'user_id', 'token', 'expires_at'
    ];
}
