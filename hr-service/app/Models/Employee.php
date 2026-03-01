<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'last_name',
        'salary',
        'country',
        'attributes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'salary' => 'float',
        'attributes' => 'array',
    ];
}
