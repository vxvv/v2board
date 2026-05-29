<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VirtualMachine extends Model
{
    protected $table = 'v2_virtual_machine';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];
    protected $hidden = ['password'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function node()
    {
        return $this->belongsTo(HypervisorNode::class, 'node_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function template()
    {
        return $this->belongsTo(VmTemplate::class, 'template_id');
    }

    public function natPorts()
    {
        return $this->hasMany(NatPortPool::class, 'vm_id');
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at < time();
    }

    public function isTrafficExceeded(): bool
    {
        if ($this->traffic_limit <= 0) return false;
        return ($this->traffic_up + $this->traffic_down) >= $this->traffic_limit;
    }
}
