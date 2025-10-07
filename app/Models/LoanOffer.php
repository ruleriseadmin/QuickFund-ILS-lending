<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Offers\Manager as OfferManager;

class LoanOffer extends Model
{
    use HasFactory, OfferManager;

    public const PENDING = 'PENDING';
    public const ACCEPTED = 'ACCEPTED';
    public const DECLINED = 'DECLINED';
    public const FAILED = 'FAILED';
    public const OVERDUE = 'OVERDUE';
    public const OPEN = 'OPEN';
    public const CLOSED = 'CLOSED';
    public const NONE = 'NONE';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'offer_id',
        'amount',
        'interest',
        'default_interest',
        'fees',
        'tenure',
        'currency',
        'expiry_date',
        'is_test',
        'default_fees_addition_days',
        'channel_code'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'offer_id' => 'integer',
        'customer_id' => 'integer',
        'amount' => 'integer',
        'fees' => 'array',
        'expiry_date' => 'date:Y-m-d',
        'interest' => 'float',
        'default_interest' => 'float',
        'tenure' => 'integer',
        'is_test' => 'boolean',
        'default_fees_addition_days' => 'integer'
    ];

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => self::NONE,
    ];

    /**
     * The relationship with the Offer model
     */
    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * The relationship with the Customer model
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The relationship with the Loan model
     */
    public function loan()
    {
        return $this->hasOne(Loan::class);
    }

    /**
     * The relationship with the CollectionCase model
     */
    public function collectionCase()
    {
        return $this->hasOne(CollectionCase::class);
    }

}
