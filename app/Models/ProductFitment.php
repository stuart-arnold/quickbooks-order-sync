<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductFitment extends Model
{
    protected $fillable = [
        'product_id',
        'bike_id',
        'fitment_id',
        'notes',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function bike()
    {
        return $this->belongsTo(Bike::class);
    }

    public function fitment()
    {
        return $this->belongsTo(Fitment::class);
    }
}
