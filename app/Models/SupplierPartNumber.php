<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPartNumber extends Model
{
    protected $fillable = [
        'product_id',
        'supplier_id',
        'supplier_part_number',
        'packs_needed',
        'cost',
        'stock',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
