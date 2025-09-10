<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VirtualAccount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'payable_code',
        'account_name',
        'account_number',
        'bank_name',
        'bank_code'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer'
    ];

    /**
     * The relationship with the Customer model
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
