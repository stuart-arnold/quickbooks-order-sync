<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;
    
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
