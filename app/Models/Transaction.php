<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    /**
     * For debit transactions
     */
    public const DEBIT = 'DEBIT';

    /**
     * For credit transactions
     */
    public const CREDIT = 'CREDIT';

    /**
     * For refund transactions
     */
    public const REFUND = 'REFUND';

    /**
     * For payment transactions
     */
    public const PAYMENT = 'PAYMENT';

    /**
     * For manual transactions
     */
    public const MANUAL = 'MANUAL';

    /**
     * Default state of transactions
     */
    public const NONE = 'NONE';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'interswitch_transaction_message',
        'interswitch_transaction_code',
        'interswitch_transaction_reference',
        'interswitch_payment_reference',
        'amount',
        'type'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'user_id'
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
        'loan_id' => 'integer',
        'transaction_date' => 'datetime'
    ];
    }

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'type' => self::NONE
    ];

    /**
     * The relationship with the Loan model
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }
}
