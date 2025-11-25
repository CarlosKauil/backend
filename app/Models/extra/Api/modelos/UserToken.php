<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'type',
        'user_agent',
        'expires_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
