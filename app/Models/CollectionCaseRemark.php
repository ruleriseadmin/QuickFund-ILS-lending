<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionCaseRemark extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'remark',
        'remarked_at',
        'promised_to_pay_at',
        'already_paid_at',
        'comment'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'collection_case_id' => 'integer',
        'user_id' => 'integer',
        'remarked_at' => 'datetime',
        'promised_to_pay_at' => 'datetime:Y-m-d',
        'already_paid_at' => 'datetime:Y-m-d'
    ];

    /**
     * The relationship with the User model
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The relationship with the CollectionCase model
     */
    public function collectionCase()
    {
        return $this->belongsTo(CollectionCase::class);
    }
}
