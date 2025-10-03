<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckFirstCentral extends Model
{
    use HasFactory;

    protected $table = 'checks_first_central';

    protected $fillable = [
        'customer_id',
        'bvn',
        'response',
        'timestamp',
    ];

    protected $casts = [
        'response' => 'array',
        'timestamp' => 'datetime',
    ];

    public static function createCheck(?int $customerId, ?string $bvn, $response = null)
    {
        return self::create([
            'customer_id' => $customerId,
            'bvn' => $bvn,
            'response' => $response, //Laravel handle JSON encoding
            'timestamp' => now(),
        ]);
    }
}
