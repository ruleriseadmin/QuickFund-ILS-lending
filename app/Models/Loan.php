<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Extensions\LoanManager;
use App\Traits\Extensions\DisableTimestamps;

class Loan extends Model
{
    use HasFactory, LoanManager;
    // use HasFactory, LoanManager, DisableTimestamps;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'amount',
        'amount_payable',
        'amount_remaining',
        'destination_account_number',
        'destination_bank_code',
        'token',
        'reference_id',
        'due_date',
        'next_due_date',
        'defaults'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'loan_offer_id' => 'integer',
        'due_date' => 'date:Y-m-d',
        'next_due_date' => 'date:Y-m-d'
    ];

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'defaults' => 0,
        'penalty' => 0,
        'penalty_remaining' => 0
    ];

    /**
     * The relationship with the Customer model
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The relationship with the LoanOffer model
     */
    public function loanOffer()
    {
        return $this->belongsTo(LoanOffer::class);
    }

    /**
     * The relationship with the Transaction model
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The relationship with the Transaction model in latest terms
     */
    public function latestTransaction()
    {
        return $this->hasOne(Transaction::class)->latestOfMany();
    }
}
