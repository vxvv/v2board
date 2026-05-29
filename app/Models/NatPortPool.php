<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NatPortPool extends Model
{
    protected $table = 'v2_nat_port_pool';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];
}
