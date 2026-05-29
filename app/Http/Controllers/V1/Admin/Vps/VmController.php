<?php

namespace App\Http\Controllers\V1\Admin\Vps;

use App\Http\Controllers\Controller;
use App\Models\HypervisorNode;
use App\Models\Plan;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Services\VmService;
use Illuminate\Http\Request;

class VmController extends Controller
{
    public function fetch(Request $request)
    {
        $builder = VirtualMachine::with(['user:id,email', 'node:id,name', 'plan:id,name']);

        if ($request->input('user_id')) {
            $builder->where('user_id', $request->input('user_id'));
        }
        if ($request->input('node_id')) {
            $builder->where('node_id', $request->input('node_id'));
        }
        if ($request->input('status')) {
            $builder->where('status', $request->input('status'));
        }

        $total = $builder->count();
        $pageSize = $request->input('pageSize', 15);
        $current = $request->input('current', 1);
        $vms = $builder->orderBy('id', 'DESC')
            ->forPage($current, $pageSize)
            ->get();

        return response([
            'data' => $vms,
            'total' => $total,
        ]);
    }

    public function detail(Request $request)
    {
        $vm = VirtualMachine::with(['user:id,email', 'node:id,name,host', 'plan:id,name', 'natPorts'])->find($request->input('id'));
        if (!$vm) abort(500, '虚拟机不存在');

        $vmService = new VmService();
        try {
            $status = $vmService->status($vm);
            $vm->pve_status = $status;
        } catch (\Exception $e) {
            $vm->pve_status = ['error' => $e->getMessage()];
        }

        return response(['data' => $vm]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'plan_id' => 'required|integer',
            'template_id' => 'required|integer',
            'node_id' => 'nullable|integer',
        ]);

        $user = User::find($request->input('user_id'));
        if (!$user) abort(500, '用户不存在');

        $plan = Plan::find($request->input('plan_id'));
        if (!$plan || $plan->plan_type !== 1) abort(500, '无效的VPS套餐');

        $vmService = new VmService();
        try {
            $vm = $vmService->create($user, $plan, $request->input('template_id'), null, $request->input('node_id'));
            return response([
                'data' => [
                    'id' => $vm->id,
                    'vmid' => $vm->vmid,
                    'password' => $vm->plain_password,
                ]
            ]);
        } catch (\Exception $e) {
            abort(500, '创建失败: ' . $e->getMessage());
        }
    }

    public function control(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'action' => 'required|in:start,stop,shutdown,reboot,rebuild',
        ]);

        $vm = VirtualMachine::find($request->input('id'));
        if (!$vm) abort(500, '虚拟机不存在');

        $vmService = new VmService();
        $action = $request->input('action');

        try {
            if ($action === 'rebuild') {
                $request->validate(['template_id' => 'required|integer']);
                $vmService->rebuild($vm, $request->input('template_id'));
                return response([
                    'data' => [
                        'success' => true,
                        'password' => $vm->plain_password,
                    ]
                ]);
            }

            $vmService->$action($vm);
            return response(['data' => true]);
        } catch (\Exception $e) {
            abort(500, '操作失败: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request)
    {
        $vm = VirtualMachine::find($request->input('id'));
        if (!$vm) abort(500, '虚拟机不存在');

        $vmService = new VmService();
        try {
            $vmService->destroy($vm);
            return response(['data' => true]);
        } catch (\Exception $e) {
            abort(500, '删除失败: ' . $e->getMessage());
        }
    }

    public function console(Request $request)
    {
        $vm = VirtualMachine::find($request->input('id'));
        if (!$vm) abort(500, '虚拟机不存在');

        $vmService = new VmService();
        try {
            $data = $vmService->console($vm);
            return response(['data' => $data]);
        } catch (\Exception $e) {
            abort(500, '获取控制台失败: ' . $e->getMessage());
        }
    }
}
