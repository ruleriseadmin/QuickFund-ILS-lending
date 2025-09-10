<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Offers\Manager as OfferManager;

class Offer extends Model
{
    use HasFactory, OfferManager;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'amount',
        'interest',
        'default_interest',
        'fees',
        'tenure',
        'cycles',
        'currency',
        'expiry_date',
        'default_fees_addition_days'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'integer',
        'fees' => 'array',
        'expiry_date' => 'date:Y-m-d',
        'interest' => 'float',
        'default_interest' => 'float',
        'tenure' => 'integer',
        'cycles' => 'integer',
        'default_fees_addition_days' => 'integer'
    ];

    /**
     * The relationship with the LoanOffer model
     */
    public function loanOffers()
    {
        return $this->hasMany(LoanOffer::class);
    }

}
