<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Extensions\CustomerManager;

class Customer extends Model
{
    use HasFactory, CustomerManager;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'hashed_phone_number',
        'encrypted_pan',
        'date_of_birth',
        'bvn',
        'address',
        'city',
        'state',
        'account_number',
        'bank_code',
        'gender',
        'country_code',
        'crc_check_last_requested_at',
        'first_central_check_last_requested_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
        'crc_check_last_requested_at' => 'datetime:Y-m-d',
        'first_central_check_last_requested_at' => 'datetime:Y-m-d',
    ];
    }

    /**
     * The relationship with the Loan model
     */
    public function loans()
    {
        return $this->hasManyThrough(
            Loan::class,       // Final related model
            LoanOffer::class,  // Intermediate model
            'customer_id',     // Foreign key on loan_offers table
            'loan_offer_id',   // Foreign key on loans table
            'id',              // Local key on customers table
            'id'               // Local key on loan_offers table
        );
    }

    /**
     * The relationship with the LoanOffer model
     */
    public function loanOffers()
    {
        return $this->hasMany(LoanOffer::class);
    }

    /**
     * The relationship with the Crc model
     */
    public function crc()
    {
        return $this->hasOne(Crc::class);
    }

    /**
     * The relationship with the FirstCentral model
     */
    public function firstCentral()
    {
        return $this->hasOne(FirstCentral::class);
    }

    /**
     * The relationship with the VirtualAccount model
     */
    public function virtualAccount()
    {
        return $this->hasOne(VirtualAccount::class);
    }

    /**
     * The relationship with the CreditScore model
     */
    public function creditScore()
    {
        return $this->hasOne(CreditScore::class);
    }

    /**
     * The relationship with the LoanOffer model in relation to the latest loan
     */
    public function latestLoanOffer()
    {
        return $this->hasOne(LoanOffer::class)
            ->ofMany([
                'id' => 'max'
            ], fn($query) => $query->whereIn('status', [
                    LoanOffer::OPEN,
                    LoanOffer::CLOSED,
                    LoanOffer::OVERDUE
                ]));
    }

    public function getFormattedCustomerIdAttribute()
    {
        return str_pad($this->id, 10, '0', STR_PAD_LEFT);
    }
}
