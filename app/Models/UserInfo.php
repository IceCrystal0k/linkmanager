<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInfo extends Model
{
    use HasFactory;

    // protected $casts = [
    //     'communication' => 'array',
    // ];

    public function timezoneRel()
    {
        return $this->hasOne(Timezone::class, 'id', 'timezone');
    }
}
