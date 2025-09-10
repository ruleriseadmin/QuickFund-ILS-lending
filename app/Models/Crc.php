<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Crc extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'summary_of_performance',
        'bvn_report_detail',
        'credit_score_details',
        'credit_facilities_summary',
        'contact_history',
        'address_history',
        'classification_institution_type',
        'classification_product_type',
        'profile_details',
        'header',
        'passes_recent_check',
        'total_delinquencies'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'customer_id' => 'integer',
        'summary_of_performance' => 'array',
        'bvn_report_detail' => 'array',
        'credit_score_details' => 'array',
        'credit_facilities_summary' => 'array',
        'contact_history' => 'array',
        'address_history' => 'array',
        'classification_institution_type' => 'array',
        'classification_product_type' => 'array',
        'profile_details' => 'array',
        'header' => 'array',
        'total_delinquencies' => 'integer'
    ];

    /**
     * The relationship with the Customer model
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The relationship with the CrcHistory model
     */
    public function crcHistory()
    {
        return $this->hasMany(CrcHistory::class);
    }

}
