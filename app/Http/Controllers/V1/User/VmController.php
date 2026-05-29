<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Models\VirtualMachine;
use App\Models\VmTemplate;
use App\Services\VmService;
use Illuminate\Http\Request;

class VmController extends Controller
{
    public function fetch(Request $request)
    {
        $userId = $request->user['id'];
        $vms = VirtualMachine::where('user_id', $userId)
            ->with(['node:id,name', 'plan:id,name'])
            ->orderBy('id', 'DESC')
            ->get()
            ->map(function ($vm) {
                return [
                    'id' => $vm->id,
                    'vmid' => $vm->vmid,
                    'name' => $vm->name,
                    'hostname' => $vm->hostname,
                    'os_name' => $vm->os_name,
                    'cpu' => $vm->cpu,
                    'ram' => $vm->ram,
                    'disk' => $vm->disk,
                    'bandwidth' => $vm->bandwidth,
                    'status' => $vm->status,
                    'internal_ip' => $vm->internal_ip,
                    'nat_port_start' => $vm->nat_port_start,
                    'nat_port_end' => $vm->nat_port_end,
                    'traffic_up' => $vm->traffic_up,
                    'traffic_down' => $vm->traffic_down,
                    'traffic_limit' => $vm->traffic_limit,
                    'expired_at' => $vm->expired_at,
                    'node_name' => $vm->node->name ?? null,
                    'plan_name' => $vm->plan->name ?? null,
                    'created_at' => $vm->created_at,
                ];
            });

        return response(['data' => $vms]);
    }

    public function detail(Request $request)
    {
        $vm = $this->getUserVm($request);

        $vmService = new VmService();
        try {
            $status = $vmService->status($vm);
        } catch (\Exception $e) {
            $status = ['error' => '无法获取实时状态'];
        }

        return response([
            'data' => [
                'id' => $vm->id,
                'vmid' => $vm->vmid,
                'name' => $vm->name,
                'hostname' => $vm->hostname,
                'os_name' => $vm->os_name,
                'cpu' => $vm->cpu,
                'ram' => $vm->ram,
                'disk' => $vm->disk,
                'bandwidth' => $vm->bandwidth,
                'status' => $vm->status,
                'internal_ip' => $vm->internal_ip,
                'nat_port_start' => $vm->nat_port_start,
                'nat_port_end' => $vm->nat_port_end,
                'traffic_up' => $vm->traffic_up,
                'traffic_down' => $vm->traffic_down,
                'traffic_limit' => $vm->traffic_limit,
                'expired_at' => $vm->expired_at,
                'created_at' => $vm->created_at,
                'pve_status' => $status,
            ]
        ]);
    }

    public function control(Request $request)
    {
        $request->validate([
            'action' => 'required|in:start,stop,shutdown,reboot',
        ]);

        $vm = $this->getUserVm($request);

        if ($vm->isExpired()) {
            abort(500, '虚拟机已过期，请续费后操作');
        }

        $vmService = new VmService();
        $action = $request->input('action');

        try {
            $vmService->$action($vm);
            return response(['data' => true]);
        } catch (\Exception $e) {
            abort(500, '操作失败: ' . $e->getMessage());
        }
    }

    public function rebuild(Request $request)
    {
        $request->validate([
            'template_id' => 'required|integer',
        ]);

        $vm = $this->getUserVm($request);

        if ($vm->isExpired()) {
            abort(500, '虚拟机已过期，请续费后操作');
        }

        $vmService = new VmService();
        try {
            $vmService->rebuild($vm, $request->input('template_id'));
            return response([
                'data' => [
                    'success' => true,
                    'password' => $vm->plain_password,
                ]
            ]);
        } catch (\Exception $e) {
            abort(500, '重装失败: ' . $e->getMessage());
        }
    }

    public function console(Request $request)
    {
        $vm = $this->getUserVm($request);

        if ($vm->isExpired()) {
            abort(500, '虚拟机已过期，请续费后操作');
        }

        $vmService = new VmService();
        try {
            $data = $vmService->console($vm);
            return response(['data' => $data]);
        } catch (\Exception $e) {
            abort(500, '获取控制台失败: ' . $e->getMessage());
        }
    }

    public function templates(Request $request)
    {
        $templates = VmTemplate::where('show', 1)->orderBy('sort', 'ASC')->get([
            'id', 'name', 'type', 'os_type', 'min_cpu', 'min_ram', 'min_disk'
        ]);
        return response(['data' => $templates]);
    }

    public function syncIp(Request $request)
    {
        $vm = $this->getUserVm($request);

        $vmService = new VmService();
        $ip = $vmService->syncIp($vm);

        return response(['data' => ['ip' => $ip]]);
    }

    private function getUserVm(Request $request): VirtualMachine
    {
        $vm = VirtualMachine::where('id', $request->input('id'))
            ->where('user_id', $request->user['id'])
            ->first();
        if (!$vm) abort(500, '虚拟机不存在');
        return $vm;
    }
}
