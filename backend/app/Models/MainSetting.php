<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MainSetting extends Model
{
    use HasFactory;

    protected $connection = 'mdl_main';
    protected $table = 'settings';
    protected $primaryKey = 'enum';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'enum',
        'value',
    ];
}
