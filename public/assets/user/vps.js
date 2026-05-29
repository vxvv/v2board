(function() {
    'use strict';

    var API = '/api/v1/user';
    var authToken = localStorage.getItem('auth_token') || '';

    function headers() {
        return { 'Content-Type': 'application/json', 'Authorization': authToken };
    }

    function api(method, path, data) {
        var opts = { method: method, headers: headers() };
        var url = API + path;
        if (data && method !== 'GET') {
            opts.body = JSON.stringify(data);
        } else if (data && method === 'GET') {
            url += '?' + new URLSearchParams(data).toString();
        }
        return fetch(url, opts).then(function(r) {
            return r.json().then(function(j) {
                if (r.ok) return j;
                throw new Error(j.message || j.errors || 'Request failed');
            });
        });
    }

    function toast(msg, type) {
        type = type || 'info';
        var el = document.createElement('div');
        el.className = 'toast toast-' + type;
        el.textContent = msg;
        document.getElementById('toastContainer').appendChild(el);
        setTimeout(function() { el.style.opacity = '0'; el.style.transform = 'translateX(100%)'; }, 2500);
        setTimeout(function() { el.remove(); }, 3000);
    }

    function showModal(title, html) {
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalBody').innerHTML = html;
        document.getElementById('modalOverlay').classList.add('show');
    }

    function closeModal() {
        document.getElementById('modalOverlay').classList.remove('show');
    }

    function esc(s) {
        if (s == null) return '';
        var div = document.createElement('div');
        div.textContent = String(s);
        return div.innerHTML;
    }

    function formatBytes(b) {
        if (!b || b === 0) return '0 B';
        var units = ['B', 'KB', 'MB', 'GB', 'TB'];
        var i = Math.floor(Math.log(b) / Math.log(1024));
        return (b / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0) + ' ' + units[i];
    }

    function daysUntil(ts) {
        if (!ts) return null;
        return Math.ceil((ts * 1000 - Date.now()) / 86400000);
    }

    function formatExpiry(ts) {
        if (!ts) return '';
        var days = daysUntil(ts);
        if (days === null) return '';
        if (days < 0) return '<span style="color:var(--danger);font-weight:500">已过期</span>';
        if (days === 0) return '<span style="color:var(--danger);font-weight:500">今天到期</span>';
        if (days <= 3) return '<span style="color:var(--warning);font-weight:500">' + days + ' 天后到期</span>';
        if (days <= 7) return '<span style="color:var(--text-secondary)">' + days + ' 天后到期</span>';
        return '<span style="color:var(--text-muted)">' + days + ' 天后到期</span>';
    }

    function statusBadge(s) {
        var map = { running: 'success', stopped: 'default', suspended: 'warning', creating: 'info', error: 'error' };
        var labels = { running: '运行中', stopped: '已停止', suspended: '已暂停', creating: '创建中', error: '错误' };
        return '<span class="badge badge-' + (map[s] || 'default') + '">' + (labels[s] || s) + '</span>';
    }

    // ========== VM LIST ==========
    var refreshTimer = null;

    function loadVms() {
        var container = document.getElementById('vmCards');
        api('GET', '/vm/list').then(function(res) {
            var vms = res.data || [];
            if (!vms.length) {
                container.innerHTML =
                    '<div class="empty">' +
                        '<svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>' +
                        '<p>暂无虚拟机</p>' +
                        '<p style="font-size:13px;color:var(--text-muted)">购买VPS套餐后，虚拟机将自动创建</p>' +
                    '</div>';
                return;
            }

            container.innerHTML = vms.map(function(v) {
                var osIcon = (v.os_name || '').toLowerCase().indexOf('windows') >= 0 ?
                    '<svg viewBox="0 0 24 24" width="16" height="16" fill="var(--info)"><path d="M0 3.5l9.5-1.3v9.3H0V3.5zm10.5-1.4L24 0v11.5H10.5V2.1zM0 12.5h9.5v9.3L0 20.5v-8zm10.5.1H24V24l-13.5-1.9V12.6z"/></svg>' :
                    '<svg viewBox="0 0 24 24" width="16" height="16" fill="var(--text-muted)"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M12 8a2 2 0 100-4 2 2 0 000 4zm0 12s-6-4-6-8c0-2 1-3 3-3 1.5 0 2.5.8 3 2 .5-1.2 1.5-2 3-2 2 0 3 1 3 3 0 4-6 8-6 8z" fill="currentColor"/></svg>';

                return '<div class="vm-card" onclick="UserVPS.showDetail(' + v.id + ')">' +
                    '<div class="vm-card-header">' +
                        '<div class="vm-card-name">' + osIcon + ' ' + esc(v.hostname || 'VM-' + v.vmid) + '</div>' +
                        statusBadge(v.status) +
                    '</div>' +
                    '<div class="vm-card-body">' +
                        '<div class="vm-card-specs">' +
                            '<div class="vm-spec"><div class="spec-value">' + v.cpu + '</div><div class="spec-label">CPU 核心</div></div>' +
                            '<div class="vm-spec"><div class="spec-value">' + v.ram + '</div><div class="spec-label">内存 MB</div></div>' +
                            '<div class="vm-spec"><div class="spec-value">' + v.disk + '</div><div class="spec-label">磁盘 GB</div></div>' +
                        '</div>' +
                        '<div class="vm-card-info">' +
                            '<div class="info-item"><svg class="info-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>' + esc(v.internal_ip || '未分配') + '</div>' +
                            '<div class="info-item"><svg class="info-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>' + formatExpiry(v.expired_at) + '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="vm-card-footer">' +
                        (v.status === 'running' ?
                            '<button class="btn btn-sm btn-warning" onclick="event.stopPropagation();UserVPS.control(' + v.id + ',\'shutdown\')">关机</button>' +
                            '<button class="btn btn-sm btn-default" onclick="event.stopPropagation();UserVPS.control(' + v.id + ',\'reboot\')">重启</button>'
                        : v.status === 'stopped' ?
                            '<button class="btn btn-sm btn-success" onclick="event.stopPropagation();UserVPS.control(' + v.id + ',\'start\')">启动</button>'
                        : '') +
                        '<button class="btn btn-sm btn-default" onclick="event.stopPropagation();UserVPS.openConsole(' + v.id + ')">控制台</button>' +
                    '</div>' +
                '</div>';
            }).join('');
        }).catch(function(e) {
            container.innerHTML = '<div class="empty"><p>加载失败: ' + esc(e.message) + '</p></div>';
        });
    }

    // ========== VM DETAIL ==========
    function showDetail(id) {
        document.getElementById('vmListView').style.display = 'none';
        document.getElementById('vmDetailView').style.display = 'block';
        var content = document.getElementById('vmDetailContent');
        content.innerHTML = '<div class="loading">加载中</div>';
        clearInterval(refreshTimer);

        api('POST', '/vm/detail', { id: id }).then(function(res) {
            var v = res.data;
            var pve = v.pve_status || {};

            var trafficUsed = (v.traffic_up || 0) + (v.traffic_down || 0);
            var trafficPct = v.traffic_limit > 0 ? Math.min(100, Math.round(trafficUsed / v.traffic_limit * 100)) : 0;

            document.getElementById('vmDetailTitle').innerHTML = esc(v.hostname || 'VM-' + v.vmid) + ' ' + statusBadge(v.status);

            content.innerHTML =
                // Info + Controls
                '<div class="detail-section">' +
                    '<h3><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>基本信息</h3>' +
                    '<div class="detail-grid">' +
                        '<div class="detail-item"><div class="label">VMID</div><div class="value">' + v.vmid + '</div></div>' +
                        '<div class="detail-item"><div class="label">CPU</div><div class="value">' + v.cpu + ' 核</div></div>' +
                        '<div class="detail-item"><div class="label">内存</div><div class="value">' + v.ram + ' MB</div></div>' +
                        '<div class="detail-item"><div class="label">磁盘</div><div class="value">' + v.disk + ' GB</div></div>' +
                        '<div class="detail-item"><div class="label">带宽</div><div class="value">' + (v.bandwidth ? v.bandwidth + ' Mbps' : '不限') + '</div></div>' +
                        '<div class="detail-item"><div class="label">系统</div><div class="value">' + esc(v.os_name || '-') + '</div></div>' +
                        '<div class="detail-item"><div class="label">内网IP</div><div class="value">' + (v.internal_ip || '未分配') + '</div></div>' +
                        '<div class="detail-item"><div class="label">NAT端口</div><div class="value">' + (v.nat_port_start ? v.nat_port_start + ' - ' + v.nat_port_end : '-') + '</div></div>' +
                        '<div class="detail-item"><div class="label">到期时间</div><div class="value">' + formatExpiry(v.expired_at) + '</div></div>' +
                    '</div>' +
                    '<div class="detail-actions">' +
                        (v.status === 'running' ?
                            '<button class="btn btn-warning btn-sm" onclick="UserVPS.control(' + id + ',\'shutdown\')">关机</button>' +
                            '<button class="btn btn-default btn-sm" onclick="UserVPS.control(' + id + ',\'reboot\')">重启</button>' +
                            '<button class="btn btn-danger btn-sm" onclick="UserVPS.control(' + id + ',\'stop\')">强制停止</button>'
                        : '<button class="btn btn-success btn-sm" onclick="UserVPS.control(' + id + ',\'start\')">启动</button>') +
                        '<button class="btn btn-primary btn-sm" onclick="UserVPS.openConsole(' + id + ')">VNC 控制台</button>' +
                        '<button class="btn btn-default btn-sm" onclick="UserVPS.showRebuild(' + id + ')">重装系统</button>' +
                        '<button class="btn btn-default btn-sm" onclick="UserVPS.doSyncIp(' + id + ')">刷新IP</button>' +
                    '</div>' +
                '</div>' +

                // Traffic
                '<div class="detail-section">' +
                    '<h3><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>流量使用</h3>' +
                    '<div class="traffic-stats">' +
                        '<div class="traffic-stat"><div class="stat-value">' + formatBytes(v.traffic_up) + '</div><div class="stat-label">上行流量</div></div>' +
                        '<div class="traffic-stat"><div class="stat-value">' + formatBytes(v.traffic_down) + '</div><div class="stat-label">下行流量</div></div>' +
                        '<div class="traffic-stat"><div class="stat-value">' + (v.traffic_limit > 0 ? formatBytes(v.traffic_limit) : '不限') + '</div><div class="stat-label">流量限额</div></div>' +
                    '</div>' +
                    (v.traffic_limit > 0 ?
                        '<div class="usage-bar" style="margin-top:16px">' +
                            '<div class="usage-header"><span>已使用 ' + trafficPct + '%</span><span>' + formatBytes(trafficUsed) + ' / ' + formatBytes(v.traffic_limit) + '</span></div>' +
                            '<div class="usage-track"><div class="usage-fill" style="width:' + trafficPct + '%;background:' + (trafficPct > 85 ? 'var(--danger)' : trafficPct > 60 ? 'var(--warning)' : 'var(--success)') + '"></div></div>' +
                        '</div>' : '') +
                '</div>' +

                // PVE Status
                (!pve.error ?
                    '<div class="detail-section">' +
                        '<h3><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>实时状态' +
                        '<span style="margin-left:auto;font-size:12px;color:var(--text-muted);font-weight:400" id="refreshIndicator">自动刷新中</span></h3>' +
                        '<div class="detail-grid">' +
                            '<div class="detail-item"><div class="label">CPU 使用率</div><div class="value" id="pveCpu">' + (pve.cpu_usage !== undefined ? (pve.cpu_usage * 100).toFixed(1) + '%' : '-') + '</div></div>' +
                            '<div class="detail-item"><div class="label">内存使用</div><div class="value" id="pveRam">' + (pve.ram_usage ? formatBytes(pve.ram_usage) + ' / ' + formatBytes(pve.ram_total) : '-') + '</div></div>' +
                            '<div class="detail-item"><div class="label">运行时间</div><div class="value" id="pveUptime">' + (pve.uptime ? Math.floor(pve.uptime / 3600) + 'h ' + Math.floor((pve.uptime % 3600) / 60) + 'm' : '-') + '</div></div>' +
                            '<div class="detail-item"><div class="label">网络入流量</div><div class="value" id="pveNetIn">' + formatBytes(pve.net_in || 0) + '</div></div>' +
                            '<div class="detail-item"><div class="label">网络出流量</div><div class="value" id="pveNetOut">' + formatBytes(pve.net_out || 0) + '</div></div>' +
                        '</div>' +
                        (pve.cpu_usage !== undefined ?
                            '<div class="usage-bar" style="margin-top:16px">' +
                                '<div class="usage-header"><span>CPU</span><span id="pveCpuPct">' + (pve.cpu_usage * 100).toFixed(1) + '%</span></div>' +
                                '<div class="usage-track"><div class="usage-fill" id="pveCpuBar" style="width:' + (pve.cpu_usage * 100).toFixed(0) + '%;background:var(--primary)"></div></div>' +
                            '</div>' +
                            (pve.ram_usage && pve.ram_total ?
                                '<div class="usage-bar">' +
                                    '<div class="usage-header"><span>内存</span><span id="pveRamPct">' + Math.round(pve.ram_usage / pve.ram_total * 100) + '%</span></div>' +
                                    '<div class="usage-track"><div class="usage-fill" id="pveRamBar" style="width:' + Math.round(pve.ram_usage / pve.ram_total * 100) + '%;background:var(--info)"></div></div>' +
                                '</div>' : '')
                        : '') +
                    '</div>' : '');

            window._currentVmId = id;

            // Auto-refresh PVE stats every 10 seconds
            refreshTimer = setInterval(function() { refreshPveStats(id); }, 10000);
        }).catch(function(e) {
            content.innerHTML = '<div class="empty"><p>加载失败: ' + esc(e.message) + '</p></div>';
        });
    }

    function refreshPveStats(id) {
        api('POST', '/vm/detail', { id: id }).then(function(res) {
            var pve = (res.data || {}).pve_status || {};
            if (pve.error) return;
            var el;
            el = document.getElementById('pveCpu');
            if (el) el.textContent = pve.cpu_usage !== undefined ? (pve.cpu_usage * 100).toFixed(1) + '%' : '-';
            el = document.getElementById('pveRam');
            if (el) el.textContent = pve.ram_usage ? formatBytes(pve.ram_usage) + ' / ' + formatBytes(pve.ram_total) : '-';
            el = document.getElementById('pveUptime');
            if (el) el.textContent = pve.uptime ? Math.floor(pve.uptime / 3600) + 'h ' + Math.floor((pve.uptime % 3600) / 60) + 'm' : '-';
            el = document.getElementById('pveNetIn');
            if (el) el.textContent = formatBytes(pve.net_in || 0);
            el = document.getElementById('pveNetOut');
            if (el) el.textContent = formatBytes(pve.net_out || 0);
            el = document.getElementById('pveCpuPct');
            if (el) el.textContent = (pve.cpu_usage * 100).toFixed(1) + '%';
            el = document.getElementById('pveCpuBar');
            if (el) el.style.width = (pve.cpu_usage * 100).toFixed(0) + '%';
            if (pve.ram_usage && pve.ram_total) {
                el = document.getElementById('pveRamPct');
                if (el) el.textContent = Math.round(pve.ram_usage / pve.ram_total * 100) + '%';
                el = document.getElementById('pveRamBar');
                if (el) el.style.width = Math.round(pve.ram_usage / pve.ram_total * 100) + '%';
            }
        }).catch(function() {});
    }

    function backToList() {
        clearInterval(refreshTimer);
        document.getElementById('vmListView').style.display = 'block';
        document.getElementById('vmDetailView').style.display = 'none';
        window._currentVmId = null;
        loadVms();
    }

    // ========== VM ACTIONS ==========
    function control(id, action) {
        var labels = { start: '启动', stop: '强制停止', shutdown: '关机', reboot: '重启' };
        if (action === 'stop' && !confirm('确认强制停止？可能导致数据丢失。')) return;
        toast('正在' + (labels[action] || action) + '...', 'info');
        api('POST', '/vm/control', { id: id, action: action }).then(function() {
            toast(labels[action] + '成功', 'success');
            if (window._currentVmId === id) {
                setTimeout(function() { showDetail(id); }, 1500);
            } else {
                loadVms();
            }
        }).catch(function(e) { toast(e.message, 'error'); });
    }

    function showRebuild(vmId) {
        api('GET', '/vm/templates').then(function(res) {
            var tpls = res.data || [];
            var options = tpls.map(function(t) {
                return '<option value="' + t.id + '">' + esc(t.name) + ' (' + t.os_type + ', ' + t.min_cpu + '核/' + t.min_ram + 'MB/' + t.min_disk + 'GB)</option>';
            }).join('');

            showModal('重装系统',
                '<div style="margin-bottom:16px;padding:14px;background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.2);border-radius:8px;font-size:13px;color:#92400e;display:flex;gap:10px;align-items:flex-start">' +
                    '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' +
                    '<span>重装系统将<strong>清除所有数据</strong>，请确保已备份重要文件！</span>' +
                '</div>' +
                '<form id="rebuildForm">' +
                '<div class="form-group"><label>选择系统模板</label><select class="form-control" name="template_id" required>' + options + '</select></div>' +
                '<div class="form-actions">' +
                    '<button type="button" class="btn btn-default" onclick="UserVPS.closeModal()">取消</button>' +
                    '<button type="submit" class="btn btn-danger">确认重装</button>' +
                '</div></form>'
            );
            document.getElementById('rebuildForm').onsubmit = function(e) {
                e.preventDefault();
                if (!confirm('最后确认：重装系统将清除所有数据。继续？')) return;
                var templateId = parseInt(new FormData(this).get('template_id'));
                toast('正在重装系统...', 'info');
                api('POST', '/vm/rebuild', { id: vmId, template_id: templateId }).then(function(res) {
                    closeModal();
                    showModal('重装完成',
                        '<div style="text-align:center;padding:8px 0">' +
                        '<div style="width:48px;height:48px;border-radius:50%;background:rgba(34,197,94,0.1);display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px">' +
                            '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="var(--success)" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>' +
                        '</div>' +
                        '<p style="font-size:16px;font-weight:600;margin-bottom:16px">系统重装成功</p>' +
                        '<div style="padding:16px;background:rgba(34,197,94,0.06);border:1px solid rgba(34,197,94,0.2);border-radius:8px">' +
                            '<div style="font-size:13px;font-weight:600;margin-bottom:6px">新密码（仅显示一次）</div>' +
                            '<code style="font-size:20px;color:var(--text);letter-spacing:1px;cursor:pointer" onclick="navigator.clipboard.writeText(this.textContent);UserVPS.toast(\'已复制\',\'success\')" title="点击复制">' + esc(res.data.password) + '</code>' +
                            '<p style="font-size:11px;color:var(--text-muted);margin-top:8px">点击密码可复制到剪贴板</p>' +
                        '</div></div>'
                    );
                    if (window._currentVmId === vmId) {
                        setTimeout(function() { showDetail(vmId); }, 2000);
                    }
                }).catch(function(e) { toast(e.message, 'error'); });
            };
        }).catch(function(e) { toast(e.message, 'error'); });
    }

    function openConsole(id) {
        toast('正在获取控制台...', 'info');
        api('POST', '/vm/console', { id: id }).then(function(res) {
            var d = res.data;
            if (d.websocket_url) {
                showModal('VNC 控制台',
                    '<iframe class="console-frame" src="' + esc(d.websocket_url) + '" allowfullscreen></iframe>' +
                    '<div style="margin-top:12px;display:flex;gap:16px;font-size:12px;color:var(--text-muted)">' +
                        '<span>Host: <code>' + esc(d.host) + '</code></span>' +
                        '<span>Port: <code>' + d.port + '</code></span>' +
                    '</div>'
                );
            } else {
                showModal('VNC 控制台',
                    '<div class="detail-grid">' +
                        '<div class="detail-item"><div class="label">Host</div><div class="value">' + esc(d.host) + '</div></div>' +
                        '<div class="detail-item"><div class="label">Port</div><div class="value">' + d.port + '</div></div>' +
                    '</div>' +
                    '<div style="margin-top:16px">' +
                        '<div class="form-group"><label>WebSocket URL</label>' +
                        '<input class="form-control" value="' + esc(d.websocket_url || '') + '" readonly onclick="this.select()" style="font-size:12px">' +
                        '</div>' +
                    '</div>'
                );
            }
        }).catch(function(e) { toast(e.message, 'error'); });
    }

    function doSyncIp(id) {
        toast('正在获取IP...', 'info');
        api('POST', '/vm/syncIp', { id: id }).then(function(res) {
            var ip = res.data.ip;
            if (ip) {
                toast('IP: ' + ip, 'success');
                if (window._currentVmId === id) showDetail(id);
            } else {
                toast('未能获取IP，请确保QEMU Guest Agent已安装', 'warning');
            }
        }).catch(function(e) { toast(e.message, 'error'); });
    }

    // Init
    loadVms();

    window.UserVPS = {
        loadVms: loadVms,
        showDetail: showDetail,
        backToList: backToList,
        control: control,
        showRebuild: showRebuild,
        openConsole: openConsole,
        doSyncIp: doSyncIp,
        closeModal: closeModal,
        toast: toast
    };
})();
