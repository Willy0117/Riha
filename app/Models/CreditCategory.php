<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function rolePoints()
    {
        return $this->hasMany(CreditRolePoint::class, 'credit_category_id');
    }
}