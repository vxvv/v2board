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
            @if($logo)<img src="{{$logo}}" alt="">@endif
            <span>VPS管理</span>
        </div>
        <nav class="sidebar-nav">
            <a href="#dashboard" class="nav-item active" data-tab="dashboard">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                <span>仪表盘</span>
            </a>
            <a href="#nodes" class="nav-item" data-tab="nodes">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                <span>节点管理</span>
            </a>
            <a href="#templates" class="nav-item" data-tab="templates">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span>系统模板</span>
            </a>
            <a href="#vms" class="nav-item" data-tab="vms">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                <span>虚拟机</span>
            </a>
            <div class="nav-divider"></div>
            <a href="/{{$secure_path}}" class="nav-item nav-back">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                <span>返回管理面板</span>
            </a>
        </nav>
    </div>

    <div class="main">
        <div class="topbar">
            <div>
                <h2 class="page-title" id="pageTitle">仪表盘</h2>
                <p class="page-subtitle" id="pageSubtitle">VPS资源概览</p>
            </div>
        </div>
        <div class="content">

            <!-- Dashboard Tab -->
            <div id="tab-dashboard" class="tab-panel active">
                <div class="stats-row" id="dashboardStats">
                    <div class="stat-card">
                        <div class="stat-icon icon-primary">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                        </div>
                        <div class="stat-value" id="statTotalVm">-</div>
                        <div class="stat-label">虚拟机总数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon icon-success">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                        <div class="stat-value" id="statRunning">-</div>
                        <div class="stat-label">运行中</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon icon-warning">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                        </div>
                        <div class="stat-value" id="statNodes">-</div>
                        <div class="stat-label">活跃节点</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon icon-danger">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </div>
                        <div class="stat-value" id="statExpired">-</div>
                        <div class="stat-label">即将过期</div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div class="table-wrap">
                        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
                            <h3 style="font-size:15px;font-weight:600">节点资源使用</h3>
                            <button class="btn btn-ghost btn-sm" onclick="VPS.loadDashboard()">刷新</button>
                        </div>
                        <table class="table">
                            <thead><tr><th>节点</th><th>CPU</th><th>内存</th><th>磁盘</th><th>VM数</th></tr></thead>
                            <tbody id="dashboardNodeBody"><tr><td colspan="5" class="loading">加载中</td></tr></tbody>
                        </table>
                    </div>
                    <div class="table-wrap">
                        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
                            <h3 style="font-size:15px;font-weight:600">最近虚拟机</h3>
                            <button class="btn btn-ghost btn-sm" onclick="VPS.switchTab('vms')">查看全部</button>
                        </div>
                        <table class="table">
                            <thead><tr><th>ID</th><th>VMID</th><th>用户</th><th>状态</th><th>到期</th></tr></thead>
                            <tbody id="dashboardVmBody"><tr><td colspan="5" class="loading">加载中</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Nodes Tab -->
            <div id="tab-nodes" class="tab-panel">
                <div class="toolbar">
                    <button class="btn btn-primary" onclick="VPS.showNodeForm()">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        添加节点
                    </button>
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
                                <th>VM数</th>
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
                    <button class="btn btn-primary" onclick="VPS.showTemplateForm()">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        添加模板
                    </button>
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
                    <button class="btn btn-primary" onclick="VPS.showVmCreateForm()">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        创建虚拟机
                    </button>
                    <div class="toolbar-right">
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
