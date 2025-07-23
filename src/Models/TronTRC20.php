<?php

namespace ItHealer\LaravelTron\Models;

use Illuminate\Database\Eloquent\Model;
use ItHealer\LaravelTron\Api\TRC20Contract;
use ItHealer\LaravelTron\Facades\Tron;

class TronTRC20 extends Model
{
    public $timestamps = false;

    protected $table = 'tron_trc20';

    protected $fillable = [
        'address',
        'name',
        'symbol',
        'decimals',
    ];

    protected $casts = [
        'decimals' => 'integer',
    ];
}
