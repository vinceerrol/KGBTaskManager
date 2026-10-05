<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function members()
    {
        return $this->belongsToMany(User::class, 'team_user')->withTimestamps();
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
