<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirstCentral extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'subject_list',
        'personal_details_summary',
        'scoring',
        'credit_summary',
        'performance_classification',
        'enquiry_details',
        'passes_recent_check',
        'total_delinquencies'
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
        'customer_id' => 'integer',
        'subject_list' => 'array', 
        'personal_details_summary' => 'array', 
        'scoring' => 'array', 
        'credit_summary' => 'array', 
        'performance_classification' => 'array', 
        'enquiry_details' => 'array',
        'total_delinquencies' => 'integer'
    ];
    }

    /**
     * The relationship with the Customer model
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The relationship with the FirstCentralHistory model
     */
    public function firstCentralHistory()
    {
        return $this->hasMany(FirstCentralHistory::class);
    }

}
