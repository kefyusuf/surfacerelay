<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class A2Order extends Model
{
    protected $table = 'a2_orders';
    public $timestamps = false;
    protected $guarded = [];
}
