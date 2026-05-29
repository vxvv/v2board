<?php

namespace App\Services;

use App\Models\HypervisorNode;
use App\Models\NatPortPool;
use App\Models\Plan;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VmService
{
    /**
     * Provision a new VM for a user based on plan specs.
     */
    public function create(User $user, Plan $plan, int $templateId, ?int $orderId = null, ?int $preferredNodeId = null): VirtualMachine
    {
        $node = $this->selectNode($plan, $preferredNodeId);
        if (!$node) {
            throw new \RuntimeException('没有可用的节点资源');
        }

        $template = VmTemplate::find($templateId);
        if (!$template) {
            throw new \RuntimeException('模板不存在');
        }

        if ($plan->cpu_cores < $template->min_cpu || $plan->ram_mb < $template->min_ram || $plan->disk_gb < $template->min_disk) {
            throw new \RuntimeException('套餐配置不满足模板最低要求');
        }

        $proxmox = ProxmoxService::fromNode($node);

        DB::beginTransaction();
        try {
            $vmid = $proxmox->getNextVmid();

            // Allocate NAT ports
            $natPorts = $this->allocateNatPorts($node, $plan->nat_ports);
            $portStart = $natPorts[0]->port_start ?? null;
            $portEnd = $natPorts[count($natPorts) - 1]->port_end ?? null;

            $password = Str::random(12);

            // Create VM record
            $vm = VirtualMachine::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'order_id' => $orderId,
                'node_id' => $node->id,
                'template_id' => $templateId,
                'vmid' => $vmid,
                'name' => 'vm-' . $vmid,
                'hostname' => 'vps-' . $user->id . '-' . $vmid,
                'cpu' => $plan->cpu_cores,
                'ram' => $plan->ram_mb,
                'disk' => $plan->disk_gb,
                'bandwidth' => $plan->bandwidth_mbps,
                'status' => 'creating',
                'os_name' => $template->name,
                'nat_port_start' => $portStart,
                'nat_port_end' => $portEnd,
                'password' => encrypt($password),
                'traffic_limit' => $plan->traffic_limit * 1073741824, // GB to bytes
                'expired_at' => null, // set by OrderService
            ]);

            // Link NAT ports to VM
            NatPortPool::whereIn('id', $natPorts->pluck('id'))->update(['vm_id' => $vm->id]);

            // Update node allocation
            $node->increment('allocated_cpu', $plan->cpu_cores);
            $node->increment('allocated_ram', $plan->ram_mb);
            $node->increment('allocated_disk', $plan->disk_gb);

            // Determine ISO path
            $isoPath = '';
            if ($template->type === 'iso') {
                $isoPath = $template->storage . ':iso/' . $template->file;
            }

            // Create VM on PVE
            $proxmox->createVm([
                'vmid' => $vmid,
                'name' => $vm->hostname,
                'cpu' => $plan->cpu_cores,
                'ram' => $plan->ram_mb,
                'disk' => $plan->disk_gb,
                'storage' => $node->storage,
                'bridge' => $node->network_bridge,
                'ostype' => $template->os_type,
                'iso' => $isoPath,
                'bandwidth' => $plan->bandwidth_mbps > 0 ? $plan->bandwidth_mbps : null,
            ]);

            // Start VM
            $proxmox->startVm($vmid);
            $vm->update(['status' => 'running']);

            DB::commit();

            // Attach the plain password for response (not persisted in plain)
            $vm->plain_password = $password;

            return $vm;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VM creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Start a stopped VM.
     */
    public function start(VirtualMachine $vm): bool
    {
        $node = $vm->node;
        $proxmox = ProxmoxService::fromNode($node);

        $proxmox->startVm($vm->vmid);
        $vm->update(['status' => 'running']);
        return true;
    }

    /**
     * Graceful shutdown.
     */
    public function shutdown(VirtualMachine $vm): bool
    {
        $proxmox = ProxmoxService::fromNode($vm->node);
        $proxmox->shutdownVm($vm->vmid);
        $vm->update(['status' => 'stopped']);
        return true;
    }

    /**
     * Force stop.
     */
    public function stop(VirtualMachine $vm): bool
    {
        $proxmox = ProxmoxService::fromNode($vm->node);
        $proxmox->stopVm($vm->vmid);
        $vm->update(['status' => 'stopped']);
        return true;
    }

    /**
     * Reboot.
     */
    public function reboot(VirtualMachine $vm): bool
    {
        $proxmox = ProxmoxService::fromNode($vm->node);
        $proxmox->rebootVm($vm->vmid);
        $vm->update(['status' => 'running']);
        return true;
    }

    /**
     * Rebuild / reinstall OS (DD support).
     */
    public function rebuild(VirtualMachine $vm, int $templateId): bool
    {
        $template = VmTemplate::find($templateId);
        if (!$template) {
            throw new \RuntimeException('模板不存在');
        }

        $proxmox = ProxmoxService::fromNode($vm->node);

        // Stop VM if running
        try {
            $proxmox->stopVm($vm->vmid);
            sleep(5);
        } catch (\Exception $e) {
            // may already be stopped
        }

        if ($template->type === 'iso') {
            $isoPath = $template->storage . ':iso/' . $template->file;
            $proxmox->mountIso($vm->vmid, $isoPath);
        }

        $proxmox->startVm($vm->vmid);

        $newPassword = Str::random(12);
        $vm->update([
            'template_id' => $templateId,
            'os_name' => $template->name,
            'password' => encrypt($newPassword),
            'status' => 'running',
        ]);

        $vm->plain_password = $newPassword;
        return true;
    }

    /**
     * Destroy VM and release resources.
     */
    public function destroy(VirtualMachine $vm): bool
    {
        $node = $vm->node;
        $proxmox = ProxmoxService::fromNode($node);

        DB::beginTransaction();
        try {
            $proxmox->destroyVm($vm->vmid);

            // Release NAT ports
            NatPortPool::where('vm_id', $vm->id)->update(['vm_id' => null]);

            // Release node allocation
            $node->decrement('allocated_cpu', $vm->cpu);
            $node->decrement('allocated_ram', $vm->ram);
            $node->decrement('allocated_disk', $vm->disk);

            $vm->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VM destroy failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get VM status from PVE.
     */
    public function status(VirtualMachine $vm): array
    {
        $proxmox = ProxmoxService::fromNode($vm->node);
        $pveStatus = $proxmox->getVmStatus($vm->vmid);

        return [
            'id' => $vm->id,
            'vmid' => $vm->vmid,
            'name' => $vm->name,
            'hostname' => $vm->hostname,
            'status' => $pveStatus['status'] ?? $vm->status,
            'cpu_usage' => $pveStatus['cpu'] ?? 0,
            'ram_usage' => $pveStatus['mem'] ?? 0,
            'ram_total' => $pveStatus['maxmem'] ?? 0,
            'disk_usage' => $pveStatus['disk'] ?? 0,
            'disk_total' => $pveStatus['maxdisk'] ?? 0,
            'uptime' => $pveStatus['uptime'] ?? 0,
            'net_in' => $pveStatus['netin'] ?? 0,
            'net_out' => $pveStatus['netout'] ?? 0,
        ];
    }

    /**
     * Get VNC console access.
     */
    public function console(VirtualMachine $vm): array
    {
        $proxmox = ProxmoxService::fromNode($vm->node);
        $vncData = $proxmox->createVncProxy($vm->vmid);

        return [
            'host' => rtrim($vm->node->host, '/'),
            'port' => $vncData['port'] ?? 0,
            'ticket' => $vncData['ticket'] ?? '',
            'websocket_url' => $proxmox->getVncWebsocket(
                $vm->vmid,
                $vncData['port'] ?? 0,
                $vncData['ticket'] ?? ''
            ),
        ];
    }

    /**
     * Detect and update VM's internal IP from QEMU guest agent.
     */
    public function syncIp(VirtualMachine $vm): ?string
    {
        $proxmox = ProxmoxService::fromNode($vm->node);
        $interfaces = $proxmox->getVmNetworkInterfaces($vm->vmid);

        if (empty($interfaces) || !is_array($interfaces)) {
            return null;
        }

        foreach ($interfaces as $iface) {
            if (($iface['name'] ?? '') === 'lo') continue;
            foreach ($iface['ip-addresses'] ?? [] as $addr) {
                if (($addr['ip-address-type'] ?? '') === 'ipv4') {
                    $ip = $addr['ip-address'];
                    $vm->update(['internal_ip' => $ip]);
                    return $ip;
                }
            }
        }
        return null;
    }

    // --- Private helpers ---

    private function selectNode(Plan $plan, ?int $preferredNodeId): ?HypervisorNode
    {
        if ($preferredNodeId) {
            $node = HypervisorNode::find($preferredNodeId);
            if ($node && $node->canAllocate($plan->cpu_cores, $plan->ram_mb, $plan->disk_gb)) {
                return $node;
            }
        }

        return HypervisorNode::where('status', 1)
            ->orderBy('allocated_cpu', 'ASC')
            ->get()
            ->first(function ($node) use ($plan) {
                return $node->canAllocate($plan->cpu_cores, $plan->ram_mb, $plan->disk_gb);
            });
    }

    private function allocateNatPorts(HypervisorNode $node, int $count)
    {
        $ports = NatPortPool::where('node_id', $node->id)
            ->whereNull('vm_id')
            ->limit($count)
            ->lockForUpdate()
            ->get();

        if ($ports->count() < $count) {
            throw new \RuntimeException("可用NAT端口不足，需要 {$count} 个，当前可用 {$ports->count()} 个");
        }

        return $ports;
    }
}
