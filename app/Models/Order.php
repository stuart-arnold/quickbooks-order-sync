<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'status',
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
