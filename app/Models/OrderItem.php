<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'bike_id',
        'fitment_id',
        'quantity',
        'product_name',
        'product_price',
        'product_sku',
        'bike_name',
        'fitment_name',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function bike()
    {
        return $this->belongsTo(Bike::class)->withDefault();
    }
    
    public function fitment()
    {
        return $this->belongsTo(Fitment::class)->withDefault();
    }
}
