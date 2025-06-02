<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuickBooksToken extends Model
{
    use HasFactory;

    protected $table = 'quickbooks_tokens'; 

    protected $fillable = ['realm_id', 'access_token', 'refresh_token'];
}
