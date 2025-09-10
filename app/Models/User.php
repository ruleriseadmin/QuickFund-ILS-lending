<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    
    public const APPLICATION_ID = 1;

    /**
     * For the Interswitch user ID
     */
    public const INTERSWITCH_ID = 2;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'name',
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'role_id' => 'integer',
        'department_id' => 'integer',
        'email_verified_at' => 'datetime',
    ];

    /**
     * The scope for interswitch
     */
    public function scopeInterswitch($query)
    {
        return $query->where('id', self::INTERSWITCH_ID);
    }

    /**
     * The scope for staff
     */
    public function scopeUsers($query)
    {
        return $query->whereNotIn('id', [
            // self::APPLICATION_ID,
            self::INTERSWITCH_ID
        ]);
    }

    /**
     * The scope for staff
     */
    public function scopeStaff($query)
    {
        return $query->where(fn($query2) => $query2->whereNotIn('id', [
                                                        // self::APPLICATION_ID,
                                                        self::INTERSWITCH_ID
                                                    ])
                                                    ->whereNot('role_id', Role::ADMINISTRATOR));
    }

    /**
     * The relationship with the Role model
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * The relationship with the Department model
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The relationship with the ActivityLog model
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * The relationship with the CollectionCase model
     */
    public function collectionCases()
    {
        return $this->hasMany(CollectionCase::class);
    }

    /**
     * The relationship with the CollectionCaseRemark model
     */
    public function collectionCaseRemarks()
    {
        return $this->hasMany(CollectionCaseRemark::class);
    }
}
