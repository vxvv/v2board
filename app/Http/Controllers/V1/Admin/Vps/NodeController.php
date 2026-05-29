<?php

namespace App\Http\Controllers\V1\Admin\Vps;

use App\Http\Controllers\Controller;
use App\Models\HypervisorNode;
use App\Models\NatPortPool;
use App\Services\ProxmoxService;
use Illuminate\Http\Request;

class NodeController extends Controller
{
    public function fetch(Request $request)
    {
        $nodes = HypervisorNode::orderBy('sort', 'ASC')->get();
        foreach ($nodes as $node) {
            $node->vm_count = $node->virtualMachines()->count();
            $node->available_cpu = $node->available_cpu;
            $node->available_ram = $node->available_ram;
            $node->available_disk = $node->available_disk;
        }
        return response(['data' => $nodes]);
    }

    public function save(Request $request)
    {
        $params = $request->validate([
            'name' => 'required|string|max:255',
            'host' => 'required|url|max:255',
            'token_id' => 'required|string|max:255',
            'token_secret' => 'required|string',
            'storage' => 'string|max:64',
            'network_bridge' => 'string|max:32',
            'nat_interface' => 'string|max:32',
            'nat_gateway' => 'nullable|ip',
            'nat_subnet' => 'string|max:45',
            'dhcp_range_start' => 'ip',
            'dhcp_range_end' => 'ip',
            'total_cpu' => 'integer|min:0',
            'total_ram' => 'integer|min:0',
            'total_disk' => 'integer|min:0',
            'sort' => 'nullable|integer',
        ]);

        if ($request->input('id')) {
            $node = HypervisorNode::find($request->input('id'));
            if (!$node) abort(500, '节点不存在');
            $node->update($params);
        } else {
            $node = HypervisorNode::create($params);
        }

        return response(['data' => true]);
    }

    public function drop(Request $request)
    {
        $node = HypervisorNode::find($request->input('id'));
        if (!$node) abort(500, '节点不存在');
        if ($node->virtualMachines()->exists()) {
            abort(500, '该节点下存在虚拟机，无法删除');
        }
        $node->delete();
        return response(['data' => true]);
    }

    public function testConnection(Request $request)
    {
        $node = HypervisorNode::find($request->input('id'));
        if (!$node) abort(500, '节点不存在');

        try {
            $proxmox = ProxmoxService::fromNode($node);
            $status = $proxmox->getNodeStatus();

            // Auto-fill resource info
            if (isset($status['cpuinfo']['cores'])) {
                $node->update([
                    'total_cpu' => $status['cpuinfo']['cores'] * ($status['cpuinfo']['sockets'] ?? 1),
                    'total_ram' => intval(($status['memory']['total'] ?? 0) / 1048576),
                ]);
            }

            return response([
                'data' => [
                    'success' => true,
                    'cpu' => $status['cpuinfo'] ?? null,
                    'memory' => $status['memory'] ?? null,
                    'uptime' => $status['uptime'] ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            return response([
                'data' => [
                    'success' => false,
                    'error' => $e->getMessage(),
                ]
            ]);
        }
    }

    public function syncResources(Request $request)
    {
        $node = HypervisorNode::find($request->input('id'));
        if (!$node) abort(500, '节点不存在');

        try {
            $proxmox = ProxmoxService::fromNode($node);
            $status = $proxmox->getNodeStatus();
            $storage = $proxmox->getNodeStorage($node->storage);

            $node->update([
                'total_cpu' => ($status['cpuinfo']['cores'] ?? 0) * ($status['cpuinfo']['sockets'] ?? 1),
                'total_ram' => intval(($status['memory']['total'] ?? 0) / 1048576),
                'total_disk' => intval(($storage['total'] ?? 0) / 1073741824),
            ]);

            return response(['data' => true]);
        } catch (\Exception $e) {
            abort(500, '同步失败: ' . $e->getMessage());
        }
    }

    public function initNatPorts(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'start' => 'required|integer|min:10000|max:65535',
            'end' => 'required|integer|min:10000|max:65535',
            'ports_per_vm' => 'required|integer|min:1|max:1000',
        ]);

        $node = HypervisorNode::find($request->input('id'));
        if (!$node) abort(500, '节点不存在');

        $start = $request->input('start');
        $end = $request->input('end');
        $portsPerVm = $request->input('ports_per_vm');

        if ($end <= $start) abort(500, '结束端口必须大于起始端口');

        $created = 0;
        for ($port = $start; $port + $portsPerVm - 1 <= $end; $port += $portsPerVm) {
            NatPortPool::create([
                'node_id' => $node->id,
                'port_start' => $port,
                'port_end' => $port + $portsPerVm - 1,
                'vm_id' => null,
            ]);
            $created++;
        }

        return response(['data' => ['created' => $created]]);
    }
}
