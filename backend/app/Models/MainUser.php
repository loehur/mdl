<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class MainUser extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $connection = 'mdl_main';
    protected $table = 'users';

    protected $fillable = [
        'name',
        'phone_number',
        'password',
        'active_until',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
