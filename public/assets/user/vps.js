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
                throw new Error(j.message || 'Request failed');
            });
        });
    }

    function toast(msg, type) {
        type = type || 'info';
        var el = document.createElement('div');
        el.className = 'toast toast-' + type;
        el.textContent = msg;
        document.getElementById('toastContainer').appendChild(el);
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

    function statusBadge(s) {
        var map = { running: 'success', stopped: 'default', suspended: 'warning', creating: 'info', error: 'error' };
        var labels = { running: '运行中', stopped: '已停止', suspended: '已暂停', creating: '创建中', error: '错误' };
        return '<span class="badge badge-' + (map[s] || 'default') + '">' + (labels[s] || s) + '</span>';
    }

    function formatBytes(bytes) {
        if (!bytes) return '0 B';
        var units = ['B', 'KB', 'MB', 'GB', 'TB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
    }

    function formatTime(ts) {
        if (!ts) return '-';
        var d = new Date(ts * 1000);
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    }

    function daysUntil(ts) {
        if (!ts) return null;
        return Math.ceil((ts * 1000 - Date.now()) / 86400000);
    }

    // ========== VM LIST ==========
    function loadVms() {
        var container = document.getElementById('vmCards');
        container.innerHTML = '<div class="loading">加载中...</div>';

        api('GET', '/vm/fetch').then(function(res) {
            var vms = res.data || [];
            if (!vms.length) {
                container.innerHTML = '<div class="empty"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="#ccc" stroke-width="1.5"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg><p>暂无虚拟机</p></div>';
                return;
            }
            container.innerHTML = vms.map(function(v) {
                var days = daysUntil(v.expired_at);
                var expiryText = days === null ? '永久' : days > 0 ? days + '天后到期' : '已过期';
                var expiryClass = days === null ? '' : days > 7 ? '' : days > 0 ? 'style="color:#faad14"' : 'style="color:#ff4d4f"';
                return '<div class="vm-card" onclick="UserVPS.showDetail(' + v.id + ')">' +
                    '<div class="vm-card-header">' +
                        '<span class="vm-card-name">' + esc(v.hostname || v.name) + '</span>' +
                        statusBadge(v.status) +
                    '</div>' +
                    '<div class="vm-card-body">' +
                        '<div class="vm-card-specs">' +
                            '<div class="vm-spec"><div class="spec-value">' + v.cpu + '</div><div class="spec-label">CPU核心</div></div>' +
                            '<div class="vm-spec"><div class="spec-value">' + (v.ram >= 1024 ? (v.ram/1024).toFixed(0) + 'G' : v.ram + 'M') + '</div><div class="spec-label">内存</div></div>' +
                            '<div class="vm-spec"><div class="spec-value">' + v.disk + 'G</div><div class="spec-label">磁盘</div></div>' +
                        '</div>' +
                        '<div class="vm-card-info">' +
                            '<span class="info-item">📍 ' + esc(v.node_name || '-') + '</span>' +
                            '<span class="info-item">💿 ' + esc(v.os_name || '-') + '</span>' +
                            (v.internal_ip ? '<span class="info-item">🌐 ' + v.internal_ip + '</span>' : '') +
                            '<span class="info-item" ' + expiryClass + '>⏱ ' + expiryText + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div class="vm-card-footer">' +
                        '<button class="btn btn-sm btn-success" onclick="event.stopPropagation();UserVPS.control(' + v.id + ',\'start\')">启动</button>' +
                        '<button class="btn btn-sm btn-warning" onclick="event.stopPropagation();UserVPS.control(' + v.id + ',\'shutdown\')">关机</button>' +
                        '<button class="btn btn-sm btn-default" onclick="event.stopPropagation();UserVPS.control(' + v.id + ',\'reboot\')">重启</button>' +
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
        content.innerHTML = '<div class="loading">加载中...</div>';

        api('POST', '/vm/detail', { id: id }).then(function(res) {
            var v = res.data;
            var pve = v.pve_status || {};
            var days = daysUntil(v.expired_at);

            document.getElementById('vmDetailTitle').textContent = v.hostname || v.name;

            var trafficUsed = (v.traffic_up || 0) + (v.traffic_down || 0);
            var trafficPct = v.traffic_limit > 0 ? Math.min(100, Math.round(trafficUsed / v.traffic_limit * 100)) : 0;

            content.innerHTML =
                // Status & Controls
                '<div class="detail-section">' +
                    '<h3>状态与控制</h3>' +
                    '<div class="detail-grid">' +
                        '<div class="detail-item"><div class="label">状态</div><div class="value">' + statusBadge(v.status) + '</div></div>' +
                        '<div class="detail-item"><div class="label">VMID</div><div class="value">' + v.vmid + '</div></div>' +
                        '<div class="detail-item"><div class="label">到期时间</div><div class="value">' + formatTime(v.expired_at) + (days !== null ? ' (' + (days > 0 ? days + '天)' : '<span style="color:#ff4d4f">已过期</span>)') : '') + '</div></div>' +
                    '</div>' +
                    '<div class="detail-actions">' +
                        '<button class="btn btn-success" onclick="UserVPS.control(' + v.id + ',\'start\')">启动</button>' +
                        '<button class="btn btn-warning" onclick="UserVPS.control(' + v.id + ',\'shutdown\')">关机</button>' +
                        '<button class="btn btn-default" onclick="UserVPS.control(' + v.id + ',\'reboot\')">重启</button>' +
                        '<button class="btn btn-danger btn-sm" onclick="UserVPS.control(' + v.id + ',\'stop\')">强制停止</button>' +
                        '<button class="btn btn-primary" onclick="UserVPS.openConsole(' + v.id + ')">VNC控制台</button>' +
                        '<button class="btn btn-default" onclick="UserVPS.showRebuild(' + v.id + ')">重装系统</button>' +
                        '<button class="btn btn-default btn-sm" onclick="UserVPS.doSyncIp(' + v.id + ')">刷新IP</button>' +
                    '</div>' +
                '</div>' +

                // Specs
                '<div class="detail-section">' +
                    '<h3>配置信息</h3>' +
                    '<div class="detail-grid">' +
                        '<div class="detail-item"><div class="label">CPU</div><div class="value">' + v.cpu + ' 核</div></div>' +
                        '<div class="detail-item"><div class="label">内存</div><div class="value">' + v.ram + ' MB</div></div>' +
                        '<div class="detail-item"><div class="label">磁盘</div><div class="value">' + v.disk + ' GB</div></div>' +
                        '<div class="detail-item"><div class="label">带宽</div><div class="value">' + (v.bandwidth ? v.bandwidth + ' Mbps' : '不限') + '</div></div>' +
                        '<div class="detail-item"><div class="label">系统</div><div class="value">' + esc(v.os_name || '-') + '</div></div>' +
                        '<div class="detail-item"><div class="label">套餐</div><div class="value">' + esc(v.plan_name || '-') + '</div></div>' +
                    '</div>' +
                '</div>' +

                // Network
                '<div class="detail-section">' +
                    '<h3>网络信息</h3>' +
                    '<div class="detail-grid">' +
                        '<div class="detail-item"><div class="label">内网IP</div><div class="value">' + (v.internal_ip || '未分配') + '</div></div>' +
                        '<div class="detail-item"><div class="label">NAT端口范围</div><div class="value">' + (v.nat_port_start ? v.nat_port_start + ' - ' + v.nat_port_end : '-') + '</div></div>' +
                    '</div>' +
                '</div>' +

                // Traffic
                '<div class="detail-section">' +
                    '<h3>流量使用</h3>' +
                    '<div class="traffic-stats">' +
                        '<div class="traffic-stat"><div class="stat-value">' + formatBytes(v.traffic_up) + '</div><div class="stat-label">上行</div></div>' +
                        '<div class="traffic-stat"><div class="stat-value">' + formatBytes(v.traffic_down) + '</div><div class="stat-label">下行</div></div>' +
                        '<div class="traffic-stat"><div class="stat-value">' + (v.traffic_limit > 0 ? formatBytes(v.traffic_limit) : '不限') + '</div><div class="stat-label">限额</div></div>' +
                    '</div>' +
                    (v.traffic_limit > 0 ?
                        '<div class="usage-bar" style="margin-top:16px">' +
                            '<div class="usage-header"><span>已使用 ' + trafficPct + '%</span><span>' + formatBytes(trafficUsed) + ' / ' + formatBytes(v.traffic_limit) + '</span></div>' +
                            '<div class="usage-track"><div class="usage-fill" style="width:' + trafficPct + '%;background:' + (trafficPct > 85 ? '#ff4d4f' : trafficPct > 60 ? '#faad14' : '#52c41a') + '"></div></div>' +
                        '</div>' : '') +
                '</div>' +

                // PVE Status
                (!pve.error ?
                    '<div class="detail-section">' +
                        '<h3>实时状态</h3>' +
                        '<div class="detail-grid">' +
                            '<div class="detail-item"><div class="label">CPU使用率</div><div class="value">' + (pve.cpu_usage !== undefined ? (pve.cpu_usage * 100).toFixed(1) + '%' : '-') + '</div></div>' +
                            '<div class="detail-item"><div class="label">内存使用</div><div class="value">' + (pve.ram_usage ? formatBytes(pve.ram_usage) + ' / ' + formatBytes(pve.ram_total) : '-') + '</div></div>' +
                            '<div class="detail-item"><div class="label">运行时间</div><div class="value">' + (pve.uptime ? Math.floor(pve.uptime / 3600) + ' 小时' : '-') + '</div></div>' +
                            '<div class="detail-item"><div class="label">网络入流量</div><div class="value">' + formatBytes(pve.net_in || 0) + '</div></div>' +
                            '<div class="detail-item"><div class="label">网络出流量</div><div class="value">' + formatBytes(pve.net_out || 0) + '</div></div>' +
                        '</div>' +
                    '</div>' : '');

            window._currentVmId = id;
        }).catch(function(e) {
            content.innerHTML = '<div class="empty"><p>加载失败: ' + esc(e.message) + '</p></div>';
        });
    }

    function backToList() {
        document.getElementById('vmListView').style.display = 'block';
        document.getElementById('vmDetailView').style.display = 'none';
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
                return '<option value="' + t.id + '">' + esc(t.name) + ' (' + t.os_type + ', 最低 ' + t.min_cpu + '核/' + t.min_ram + 'MB/' + t.min_disk + 'GB)</option>';
            }).join('');

            showModal('重装系统',
                '<div style="margin-bottom:16px;padding:12px;background:#fff7e6;border:1px solid #ffd591;border-radius:6px;font-size:13px;color:#ad6800">' +
                    '⚠ 重装将清除所有数据，请确保已备份重要文件！' +
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
                        '<div style="text-align:center">' +
                        '<p style="font-size:18px;color:#52c41a;margin-bottom:16px">系统重装成功</p>' +
                        '<div style="padding:12px;background:#f6ffed;border:1px solid #b7eb8f;border-radius:6px">' +
                            '<p style="font-weight:600;margin-bottom:4px">新密码（仅显示一次）</p>' +
                            '<code style="font-size:18px;color:#333">' + esc(res.data.password) + '</code>' +
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
            showModal('VNC控制台',
                '<div style="text-align:center">' +
                '<p style="margin-bottom:16px">VNC连接信息：</p>' +
                '<div class="detail-grid">' +
                    '<div class="detail-item"><div class="label">Host</div><div class="value" style="font-size:14px;word-break:break-all">' + esc(d.host) + '</div></div>' +
                    '<div class="detail-item"><div class="label">Port</div><div class="value">' + d.port + '</div></div>' +
                '</div>' +
                '<div style="margin-top:16px">' +
                    '<p style="font-size:12px;color:#999;margin-bottom:4px">WebSocket URL</p>' +
                    '<code style="font-size:11px;word-break:break-all;display:block;padding:8px;background:#f5f5f5;border-radius:4px">' + esc(d.websocket_url) + '</code>' +
                '</div></div>'
            );
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
        closeModal: closeModal
    };
})();
