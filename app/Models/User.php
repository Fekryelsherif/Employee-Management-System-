<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'fname',
        'lname',
        'email',
        'password',
        'phone',
        'department',
        'position',
        'type',
        'branch_id',
        'region_id',
        'branch_manager_id',
        'salary',
        'commission_salary',
        'address',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

   // 🔹 الموظف أو المدير يتبع فرع
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // 🔹 الموظف يتبع مدير فرع
    public function branchManager()
    {
        return $this->belongsTo(User::class, 'branch_manager_id')
                    ->where('type', 'branch-manager');
    }

    // 🔹 مدير الفرع يشوف موظفيه
    public function employees()
    {
        return $this->hasMany(User::class, 'branch_manager_id')
                    ->where('type', 'employee');
    }
    // 🔹 التحديات اللي أنشأها مدير الفرع
    public function challengesCreated()
    {
        return $this->hasMany(Challenge::class, 'branch_manager_id');
    }

    // 🔹 مشاركة الموظف في التحديات
    public function challengeParticipations()
    {
        return $this->hasMany(ChallengeParticipant::class, 'employee_id');
    }

    // 🔹 الإشعارات
    // public function notifications()
    // {
    //     return $this->morphMany(\Illuminate\Notifications\DatabaseNotification::class, 'notifiable');
    // }

    // 🔹 العلاقات الأخرى (الملاحظات - السلف - الكوميشن)
    public function sentNotifications()
    {
        return $this->hasMany(AppNotification::class, 'sender_id');
    }

    public function receivedNotifications()
    {
        return $this->hasMany(AppNotification::class, 'receiver_id');
    }

    public function notesSent()
    {
        return $this->hasMany(Note::class, 'employee_id');
    }

    public function notesReceived()
    {
        return $this->hasMany(Note::class, 'branch_manager_id');
    }

    public function loansSent()
    {
        return $this->hasMany(Loan::class, 'employee_id');
    }

    public function loansReceived()
    {
        return $this->hasMany(Loan::class, 'branch_manager_id');
    }

    public function serviceCommission()
    {
        return $this->hasMany(ServiceCommission::class, 'branch_manager_id');
    }

    public function region()
{
    return $this->hasOne(Region::class, 'region_manager_id');
}

public function managedBranch()
{
    return $this->hasOne(Branch::class, 'branch_manager_id');
}


public function targetParticipants()
{
    return $this->hasMany(TargetParticipant::class, 'employee_id', 'id');
}

public function challenges()
{
    return $this->belongsToMany(
        Challenge::class,
        'challenge_participants',
        'employee_id',
        'challenge_id',
);
}

// User.php
public function sales()
{
    return $this->hasMany(Sale::class, 'employee_id');
}


public function managedBranches()
{
    return $this->hasMany(Branch::class, 'region_manager_id');
}




}
