<?php

namespace App\Console\Commands;

use App\Models\VirtualMachine;
use App\Services\ProxmoxService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VpsExpireCheck extends Command
{
    protected $signature = 'vps:expire-check';
    protected $description = 'Check and suspend expired VPS instances';

    public function handle()
    {
        $expiredVms = VirtualMachine::where('expired_at', '<=', time())
            ->where('expired_at', '>', 0)
            ->whereIn('status', ['running', 'stopped'])
            ->get();

        foreach ($expiredVms as $vm) {
            try {
                $node = $vm->node;
                if (!$node) continue;

                $proxmox = ProxmoxService::fromNode($node);

                if ($vm->status === 'running') {
                    $proxmox->shutdownVm($vm->vmid);
                }

                $vm->update([
                    'status' => 'suspended',
                    'suspended_at' => time(),
                ]);

                $this->info("Suspended VM #{$vm->id} (VMID: {$vm->vmid}) for user #{$vm->user_id}");
            } catch (\Exception $e) {
                Log::error("Failed to suspend VM #{$vm->id}: " . $e->getMessage());
                $this->error("Failed to suspend VM #{$vm->id}: " . $e->getMessage());
            }
        }

        // Auto-delete VMs suspended for more than configured days (default 7)
        $autoDeleteDays = (int)config('v2board.vps_auto_delete_days', 0);
        if ($autoDeleteDays > 0) {
            $deleteThreshold = time() - ($autoDeleteDays * 86400);
            $toDelete = VirtualMachine::where('status', 'suspended')
                ->where('suspended_at', '<=', $deleteThreshold)
                ->where('suspended_at', '>', 0)
                ->get();

            foreach ($toDelete as $vm) {
                try {
                    $vmService = new \App\Services\VmService();
                    $vmService->destroy($vm);
                    $this->info("Auto-deleted VM #{$vm->id} (suspended > {$autoDeleteDays} days)");
                } catch (\Exception $e) {
                    Log::error("Failed to auto-delete VM #{$vm->id}: " . $e->getMessage());
                }
            }
        }

        $this->info('VPS expire check completed');
    }
}
