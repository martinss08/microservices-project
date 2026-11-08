<?php
namespace App\Infrastructure\Redis;

use Illuminate\Support\Facades\Redis;

class RedisService
{

    public static function iniciarProcessamento($key) 
    {
        return Redis::set($key, 'processing', 'EX', 1500, 'NX');
    }

    public static function armazenarResultado($key, $response)
    {
        return Redis::setex($key, 3600, json_encode($response));
    }
}
