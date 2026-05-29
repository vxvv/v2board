<?php

namespace App\Http\Controllers\V1\Admin\Vps;

use App\Http\Controllers\Controller;
use App\Models\HypervisorNode;
use App\Models\VmTemplate;
use App\Services\ProxmoxService;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function fetch(Request $request)
    {
        $templates = VmTemplate::orderBy('sort', 'ASC')->get();
        return response(['data' => $templates]);
    }

    public function save(Request $request)
    {
        $params = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:iso,template',
            'os_type' => 'required|string|max:32',
            'file' => 'required|string|max:512',
            'storage' => 'string|max:64',
            'min_cpu' => 'integer|min:1',
            'min_ram' => 'integer|min:256',
            'min_disk' => 'integer|min:1',
            'show' => 'boolean',
            'sort' => 'nullable|integer',
        ]);

        if ($request->input('id')) {
            $template = VmTemplate::find($request->input('id'));
            if (!$template) abort(500, '模板不存在');
            $template->update($params);
        } else {
            VmTemplate::create($params);
        }

        return response(['data' => true]);
    }

    public function drop(Request $request)
    {
        $template = VmTemplate::find($request->input('id'));
        if (!$template) abort(500, '模板不存在');
        $template->delete();
        return response(['data' => true]);
    }

    public function listIsos(Request $request)
    {
        $nodeId = $request->input('node_id');
        $node = HypervisorNode::find($nodeId);
        if (!$node) abort(500, '节点不存在');

        try {
            $proxmox = ProxmoxService::fromNode($node);
            $isos = $proxmox->listIsos($request->input('storage', 'local'));
            return response(['data' => $isos]);
        } catch (\Exception $e) {
            abort(500, '获取ISO列表失败: ' . $e->getMessage());
        }
    }
}
