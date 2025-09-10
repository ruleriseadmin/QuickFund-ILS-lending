<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionCase extends Model
{
    use HasFactory;

    /**
     * For open cases
     */
    public const OPEN = 'OPEN';

    /**
     * For closed cases
     */
    public const CLOSED = 'CLOSED';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assigned_at' => 'datetime',
        'user_id',
    ];

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => self::OPEN
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'loan_offer_id' => 'integer',
        'assigned_at' => 'datetime',
    ];

    /**
     * The relationship with the LoanOffer model
     */
    public function loanOffer()
    {
        return $this->belongsTo(LoanOffer::class);
    }

    /**
     * The relationship with the User model
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The relationship with the CollectionCaseRemark model
     */
    public function collectionCaseRemarks()
    {
        return $this->hasMany(CollectionCaseRemark::class);
    }
}
