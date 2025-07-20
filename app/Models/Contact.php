<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company',
        'email',
        'phone',
        'address',
        'city',
        'postal_code',
        'country',
        'website',
        'category',
        'additional_data',
    ];

    protected $casts = [
        'additional_data' => 'array',
    ];
}
