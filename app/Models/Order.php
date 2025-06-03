<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'status',
        'customer_name',
        'customer_email',
        'customer_phone',
        'address_line_1',
        'address_line_2',
        'city',
        'postcode',
        'country',
        'order_comments',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /*
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function markProcessed(): void
    {
        $this->status = 'processed';
        $this->save();
    }
    */
}
