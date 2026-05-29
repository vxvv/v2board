<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>VPS管理 - {{$title}}</title>
    <link rel="stylesheet" href="/assets/admin/vps.css?v={{$version}}">
</head>
<body>
<div id="app">
    <div class="sidebar">
        <div class="sidebar-brand">
            @if($logo)<img src="{{$logo}}" alt="" class="sidebar-logo">@endif
            <span>VPS管理</span>
        </div>
        <nav class="sidebar-nav">
            <a href="#nodes" class="nav-item active" data-tab="nodes">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                节点管理
            </a>
            <a href="#templates" class="nav-item" data-tab="templates">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                系统模板
            </a>
            <a href="#vms" class="nav-item" data-tab="vms">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                虚拟机
            </a>
            <div class="nav-divider"></div>
            <a href="/{{$secure_path}}" class="nav-item nav-back">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                返回管理面板
            </a>
        </nav>
    </div>
    <div class="main">
        <div class="topbar">
            <h2 class="page-title" id="pageTitle">节点管理</h2>
        </div>
        <div class="content">
            <!-- Nodes Tab -->
            <div id="tab-nodes" class="tab-panel active">
                <div class="toolbar">
                    <button class="btn btn-primary" onclick="VPS.showNodeForm()">+ 添加节点</button>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>名称</th>
                                <th>地址</th>
                                <th>CPU</th>
                                <th>内存</th>
                                <th>磁盘</th>
                                <th>虚拟机数</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="nodeTableBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Templates Tab -->
            <div id="tab-templates" class="tab-panel">
                <div class="toolbar">
                    <button class="btn btn-primary" onclick="VPS.showTemplateForm()">+ 添加模板</button>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>名称</th>
                                <th>类型</th>
                                <th>OS类型</th>
                                <th>文件</th>
                                <th>最低配置</th>
                                <th>显示</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="templateTableBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- VMs Tab -->
            <div id="tab-vms" class="tab-panel">
                <div class="toolbar">
                    <button class="btn btn-primary" onclick="VPS.showVmCreateForm()">+ 创建虚拟机</button>
                    <div class="toolbar-filters">
                        <select id="vmFilterStatus" onchange="VPS.loadVms()">
                            <option value="">全部状态</option>
                            <option value="running">运行中</option>
                            <option value="stopped">已停止</option>
                            <option value="suspended">已暂停</option>
                            <option value="creating">创建中</option>
                            <option value="error">错误</option>
                        </select>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>VMID</th>
                                <th>用户</th>
                                <th>节点</th>
                                <th>配置</th>
                                <th>IP</th>
                                <th>NAT端口</th>
                                <th>状态</th>
                                <th>到期</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="vmTableBody"></tbody>
                    </table>
                </div>
                <div class="pagination" id="vmPagination"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modalOverlay" onclick="VPS.closeModal()">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 id="modalTitle">操作</h3>
            <button class="modal-close" onclick="VPS.closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<!-- Toast -->
<div class="toast-container" id="toastContainer"></div>

<script>
window.vpsConfig = {
    securePath: '{{$secure_path}}',
    apiBase: '/api/v1/{{$secure_path}}'
};
</script>
<script src="/assets/admin/vps.js?v={{$version}}"></script>
</body>
</html>
