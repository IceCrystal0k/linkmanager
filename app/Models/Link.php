<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Link extends Model
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_verified_at' => 'datetime',
    ];

    public function tags()
    {
        return $this->belongsToMany(Tags::class, 'links_tags');
    }

    public function getStatusNameAttribute()
    {
        $verificationStatus = $this->verification_status ?? 0;
        return  __('status.LinkStatus.' . config('settings.link_status')[$verificationStatus]);
    }
}
