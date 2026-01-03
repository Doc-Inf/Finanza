<?php 
// app/Models/Stock.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = [
        'symbol',
        'name',
        'current_price',
        'change',
        'change_percent',
        'data',
        'last_updated'
    ];

    protected $casts = [
        'data' => 'array',
        'last_updated' => 'datetime'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_stocks');
    }
}