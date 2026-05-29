<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HypervisorNode extends Model
{
    protected $table = 'v2_hypervisor_node';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];
    protected $hidden = ['token_secret'];

    public function virtualMachines()
    {
        return $this->hasMany(VirtualMachine::class, 'node_id');
    }

    public function getAvailableCpuAttribute(): int
    {
        return $this->total_cpu - $this->allocated_cpu;
    }

    public function getAvailableRamAttribute(): int
    {
        return $this->total_ram - $this->allocated_ram;
    }

    public function getAvailableDiskAttribute(): int
    {
        return $this->total_disk - $this->allocated_disk;
    }

    public function canAllocate(int $cpu, int $ram, int $disk): bool
    {
        return $this->available_cpu >= $cpu
            && $this->available_ram >= $ram
            && $this->available_disk >= $disk
            && $this->status === 1;
    }
}
