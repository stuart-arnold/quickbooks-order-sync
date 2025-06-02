<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'price',
    ];

    public function supplierPartNumbers()
    {
        return $this->hasMany(SupplierPartNumber::class);
    }

    public function fitments()
    {
        return $this->hasMany(ProductFitment::class);
    }
}
