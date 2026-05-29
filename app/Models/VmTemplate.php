<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VmTemplate extends Model
{
    protected $table = 'v2_vm_template';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];
}
