(function() {
    'use strict';

    var API = window.vpsConfig.apiBase;
    var authToken = localStorage.getItem('admin_auth_token') || '';

    function headers() {
        return {
            'Content-Type': 'application/json',
            'Authorization': authToken
        };
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

    function statusBadge(s) {
        var map = {
            running: 'success', stopped: 'default', suspended: 'warning',
            creating: 'info', error: 'error', '1': 'success', '0': 'error'
        };
        var labels = {
            running: '运行中', stopped: '已停止', suspended: '已暂停',
            creating: '创建中', error: '错误', '1': '启用', '0': '禁用'
        };
        var cls = map[String(s)] || 'default';
        var label = labels[String(s)] || s;
        return '<span class="badge badge-' + cls + '">' + label + '</span>';
    }

    function resBar(used, total, unit) {
        if (!total) return '-';
        var pct = Math.round(used / total * 100);
        var cls = pct < 60 ? 'res-low' : pct < 85 ? 'res-mid' : 'res-high';
        return '<div class="res-bar"><div class="res-bar-track"><div class="res-bar-fill ' + cls + '" style="width:' + pct + '%"></div></div>' +
            '<span>' + used + '/' + total + ' ' + (unit || '') + '</span></div>';
    }

    function formatTime(ts) {
        if (!ts) return '-';
        var d = new Date(ts * 1000);
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0') + ' ' +
            String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    }

    function esc(s) {
        if (s == null) return '';
        var div = document.createElement('div');
        div.textContent = String(s);
        return div.innerHTML;
    }

    // ========== NODES ==========
    function loadNodes() {
        var tbody = document.getElementById('nodeTableBody');
        tbody.innerHTML = '<tr><td colspan="9" class="loading">加载中...</td></tr>';
        api('GET', '/vps/node/fetch').then(function(res) {
            var nodes = res.data || [];
            if (!nodes.length) {
                tbody.innerHTML = '<tr><td colspan="9" class="empty">暂无节点</td></tr>';
                return;
            }
            tbody.innerHTML = nodes.map(function(n) {
                return '<tr>' +
                    '<td>' + n.id + '</td>' +
                    '<td><strong>' + esc(n.name) + '</strong></td>' +
                    '<td><code style="font-size:12px">' + esc(n.host) + '</code></td>' +
                    '<td>' + resBar(n.allocated_cpu, n.total_cpu, '核') + '</td>' +
                    '<td>' + resBar(n.allocated_ram, n.total_ram, 'MB') + '</td>' +
                    '<td>' + resBar(n.allocated_disk, n.total_disk, 'GB') + '</td>' +
                    '<td>' + (n.vm_count || 0) + '</td>' +
                    '<td>' + statusBadge(n.status) + '</td>' +
                    '<td class="actions">' +
                        '<button class="btn btn-sm btn-default" onclick="VPS.showNodeForm(' + n.id + ')">编辑</button>' +
                        '<button class="btn btn-sm btn-success" onclick="VPS.testNode(' + n.id + ')">测试</button>' +
                        '<button class="btn btn-sm btn-warning" onclick="VPS.syncNode(' + n.id + ')">同步</button>' +
                        '<button class="btn btn-sm btn-info" onclick="VPS.showNatInit(' + n.id + ')">端口</button>' +
                        '<button class="btn btn-sm btn-danger" onclick="VPS.dropNode(' + n.id + ')">删除</button>' +
                    '</td></tr>';
            }).join('');
        }).catch(function(e) {
            tbody.innerHTML = '<tr><td colspan="9" class="empty">加载失败: ' + esc(e.message) + '</td></tr>';
        });
    }

    function showNodeForm(id) {
        var isEdit = !!id;
        var title = isEdit ? '编辑节点' : '添加节点';

        function render(node) {
            node = node || {};
            showModal(title,
                '<form id="nodeForm">' +
                (isEdit ? '<input type="hidden" name="id" value="' + id + '">' : '') +
                '<div class="form-group"><label><span class="required">*</span> 名称</label><input class="form-control" name="name" value="' + esc(node.name || '') + '" required></div>' +
                '<div class="form-group"><label><span class="required">*</span> PVE地址</label><input class="form-control" name="host" placeholder="https://192.168.1.100:8006" value="' + esc(node.host || '') + '" required></div>' +
                '<div class="form-row">' +
                    '<div class="form-group"><label><span class="required">*</span> API Token ID</label><input class="form-control" name="token_id" placeholder="root@pam!v2board" value="' + esc(node.token_id || '') + '" required></div>' +
                    '<div class="form-group"><label><span class="required">*</span> Token Secret</label><input class="form-control" name="token_secret" type="password" value="' + (isEdit ? '' : '') + '"></div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group"><label>存储池</label><input class="form-control" name="storage" value="' + esc(node.storage || 'local-lvm') + '"></div>' +
                    '<div class="form-group"><label>网桥</label><input class="form-control" name="network_bridge" value="' + esc(node.network_bridge || 'vmbr1') + '"></div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group"><label>NAT接口</label><input class="form-control" name="nat_interface" value="' + esc(node.nat_interface || 'vmbr0') + '"></div>' +
                    '<div class="form-group"><label>NAT网关</label><input class="form-control" name="nat_gateway" value="' + esc(node.nat_gateway || '') + '"></div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group"><label>NAT子网</label><input class="form-control" name="nat_subnet" value="' + esc(node.nat_subnet || '10.0.0.0/24') + '"></div>' +
                    '<div class="form-group"><label>排序</label><input class="form-control" name="sort" type="number" value="' + (node.sort || '') + '"></div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group"><label>DHCP起始</label><input class="form-control" name="dhcp_range_start" value="' + esc(node.dhcp_range_start || '10.0.0.100') + '"></div>' +
                    '<div class="form-group"><label>DHCP结束</label><input class="form-control" name="dhcp_range_end" value="' + esc(node.dhcp_range_end || '10.0.0.250') + '"></div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group"><label>总CPU核数</label><input class="form-control" name="total_cpu" type="number" value="' + (node.total_cpu || 0) + '"></div>' +
                    '<div class="form-group"><label>总内存(MB)</label><input class="form-control" name="total_ram" type="number" value="' + (node.total_ram || 0) + '"></div>' +
                '</div>' +
                '<div class="form-group"><label>总磁盘(GB)</label><input class="form-control" name="total_disk" type="number" value="' + (node.total_disk || 0) + '"></div>' +
                '<div class="form-actions">' +
                    '<button type="button" class="btn btn-default" onclick="VPS.closeModal()">取消</button>' +
                    '<button type="submit" class="btn btn-primary">保存</button>' +
                '</div></form>'
            );
            document.getElementById('nodeForm').onsubmit = function(e) {
                e.preventDefault();
                var fd = new FormData(this);
                var data = {};
                fd.forEach(function(v, k) { if (v !== '') data[k] = v; });
                ['sort','total_cpu','total_ram','total_disk'].forEach(function(k) { if (data[k]) data[k] = parseInt(data[k]); });
                api('POST', '/vps/node/save', data).then(function() {
                    toast('保存成功', 'success');
                    closeModal();
                    loadNodes();
                }).catch(function(e) { toast(e.message, 'error'); });
            };
        }

        if (isEdit) {
            api('GET', '/vps/node/fetch').then(function(res) {
                var node = (res.data || []).find(function(n) { return n.id === id; });
                render(node);
            });
        } else {
            render();
        }
    }

    function testNode(id) {
        toast('正在测试连接...', 'info');
        api('POST', '/vps/node/testConnection', { id: id }).then(function(res) {
            var d = res.data;
            if (d.success) {
                toast('连接成功! CPU: ' + (d.cpu ? d.cpu.cores + '核' : '-') + ', 内存: ' + (d.memory ? Math.round(d.memory.total / 1048576) + 'MB' : '-'), 'success');
                loadNodes();
            } else {
                toast('连接失败: ' + (d.error || '未知错误'), 'error');
            }
        }).catch(function(e) { toast(e.message, 'error'); });
    }

    function syncNode(id) {
        api('POST', '/vps/node/syncResources', { id: id }).then(function() {
            toast('同步成功', 'success');
            loadNodes();
        }).catch(function(e) { toast(e.message, 'error'); });
    }

    function showNatInit(nodeId) {
        showModal('初始化NAT端口池',
            '<form id="natForm">' +
            '<input type="hidden" name="id" value="' + nodeId + '">' +
            '<div class="form-row">' +
                '<div class="form-group"><label>起始端口</label><input class="form-control" name="start" type="number" value="10000" min="10000" max="65535"></div>' +
                '<div class="form-group"><label>结束端口</label><input class="form-control" name="end" type="number" value="60000" min="10000" max="65535"></div>' +
            '</div>' +
            '<div class="form-group"><label>每VM端口数</label><input class="form-control" name="ports_per_vm" type="number" value="20" min="1" max="1000"></div>' +
            '<div class="form-actions">' +
                '<button type="button" class="btn btn-default" onclick="VPS.closeModal()">取消</button>' +
                '<button type="submit" class="btn btn-primary">初始化</button>' +
            '</div></form>'
        );
        document.getElementById('natForm').onsubmit = function(e) {
            e.preventDefault();
            var fd = new FormData(this);
            var data = {};
            fd.forEach(function(v, k) { data[k] = parseInt(v); });
            api('POST', '/vps/node/initNatPorts', data).then(function(res) {
                toast('已创建 ' + res.data.created + ' 个端口段', 'success');
                closeModal();
            }).catch(function(e) { toast(e.message, 'error'); });
        };
    }

    function dropNode(id) {
        if (!confirm('确认删除此节点？')) return;
        api('POST', '/vps/node/drop', { id: id }).then(function() {
            toast('删除成功', 'success');
            loadNodes();
        }).catch(function(e) { toast(e.message, 'error'); });
    }

    // ========== TEMPLATES ==========
    function loadTemplates() {
        var tbody = document.getElementById('templateTableBody');
        tbody.innerHTML = '<tr><td colspan="8" class="loading">加载中...</td></tr>';
        api('GET', '/vps/template/fetch').then(function(res) {
            var tpls = res.data || [];
            if (!tpls.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="empty">暂无模板</td></tr>';
                return;
            }
            tbody.innerHTML = tpls.map(function(t) {
                return '<tr>' +
                    '<td>' + t.id + '</td>' +
                    '<td><strong>' + esc(t.name) + '</strong></td>' +
                    '<td>' + esc(t.type) + '</td>' +
                    '<td>' + esc(t.os_type) + '</td>' +
                    '<td><code style="font-size:12px">' + esc(t.file) + '</code></td>' +
                    '<td>' + t.min_cpu + '核 / ' + t.min_ram + 'MB / ' + t.min_disk + 'GB</td>' +
                    '<td>' + (t.show ? '<span class="badge badge-success">显示</span>' : '<span class="badge badge-default">隐藏</span>') + '</td>' +
                    '<td class="actions">' +
                        '<button class="btn btn-sm btn-default" onclick="VPS.showTemplateForm(' + t.id + ')">编辑</button>' +
                        '<button class="btn btn-sm btn-danger" onclick="VPS.dropTemplate(' + t.id + ')">删除</button>' +
                    '</td></tr>';
            }).join('');
        }).catch(function(e) {
            tbody.innerHTML = '<tr><td colspan="8" class="empty">加载失败</td></tr>';
        });
    }

    function showTemplateForm(id) {
        var isEdit = !!id;
        function render(t) {
            t = t || {};
            showModal(isEdit ? '编辑模板' : '添加模板',
                '<form id="tplForm">' +
                (isEdit ? '<input type="hidden" name="id" value="' + id + '">' : '') +
                '<div class="form-group"><label><span class="required">*</span> 名称</label><input class="form-control" name="name" value="' + esc(t.name || '') + '" required placeholder="Ubuntu 22.04"></div>' +
                '<div class="form-row">' +
                    '<div class="form-group"><label><span class="required">*</span> 类型</label><select class="form-control" name="type"><option value="iso"' + (t.type === 'iso' ? ' selected' : '') + '>ISO</option><option value="template"' + (t.type === 'template' ? ' selected' : '') + '>模板</option></select></div>' +
                    '<div class="form-group"><label><span class="required">*</span> OS类型</label><select class="form-control" name="os_type">' +
                        '<option value="l26"' + (t.os_type === 'l26' ? ' selected' : '') + '>Linux 2.6+</option>' +
                        '<option value="l24"' + (t.os_type === 'l24' ? ' selected' : '') + '>Linux 2.4</option>' +
                        '<option value="win10"' + (t.os_type === 'win10' ? ' selected' : '') + '>Windows 10/11</option>' +
                        '<option value="win7"' + (t.os_type === 'win7' ? ' selected' : '') + '>Windows 7</option>' +
                        '<option value="wxp"' + (t.os_type === 'wxp' ? ' selected' : '') + '>Windows XP</option>' +
                        '<option value="other"' + (t.os_type === 'other' ? ' selected' : '') + '>Other</option>' +
                    '</select></div>' +
                '</div>' +
                '<div class="form-group"><label><span class="required">*</span> 文件名</label><input class="form-control" name="file" value="' + esc(t.file || '') + '" required placeholder="ubuntu-22.04-server-amd64.iso"></div>' +
                '<div class="form-group"><label>存储</label><input class="form-control" name="storage" value="' + esc(t.storage || 'local') + '"></div>' +
                '<div class="form-row">' +
                    '<div class="form-group"><label>最低CPU</label><input class="form-control" name="min_cpu" type="number" value="' + (t.min_cpu || 1) + '" min="1"></div>' +
                    '<div class="form-group"><label>最低内存(MB)</label><input class="form-control" name="min_ram" type="number" value="' + (t.min_ram || 512) + '" min="256"></div>' +
                '</div>' +
                '<div class="form-row">' +
                    '<div class="form-group"><label>最低磁盘(GB)</label><input class="form-control" name="min_disk" type="number" value="' + (t.min_disk || 10) + '" min="1"></div>' +
                    '<div class="form-group"><label>排序</label><input class="form-control" name="sort" type="number" value="' + (t.sort || '') + '"></div>' +
                '</div>' +
                '<div class="form-group"><label>显示</label><select class="form-control" name="show"><option value="1"' + (t.show !== 0 ? ' selected' : '') + '>显示</option><option value="0"' + (t.show === 0 ? ' selected' : '') + '>隐藏</option></select></div>' +
                '<div class="form-actions">' +
                    '<button type="button" class="btn btn-default" onclick="VPS.closeModal()">取消</button>' +
                    '<button type="submit" class="btn btn-primary">保存</button>' +
                '</div></form>'
            );
            document.getElementById('tplForm').onsubmit = function(e) {
                e.preventDefault();
                var fd = new FormData(this);
                var data = {};
                fd.forEach(function(v, k) { if (v !== '') data[k] = v; });
                ['min_cpu','min_ram','min_disk','sort','show'].forEach(function(k) { if (data[k] !== undefined) data[k] = parseInt(data[k]); });
                api('POST', '/vps/template/save', data).then(function() {
                    toast('保存成功', 'success');
                    closeModal();
                    loadTemplates();
                }).catch(function(e) { toast(e.message, 'error'); });
            };
        }

        if (isEdit) {
            api('GET', '/vps/template/fetch').then(function(res) {
                var t = (res.data || []).find(function(t) { return t.id === id; });
                render(t);
            });
        } else {
            render();
        }
    }

    function dropTemplate(id) {
        if (!confirm('确认删除此模板？')) return;
        api('POST', '/vps/template/drop', { id: id }).then(function() {
            toast('删除成功', 'success');
            loadTemplates();
        }).catch(function(e) { toast(e.message, 'error'); });
    }

    // ========== VMs ==========
    var vmPage = 1;

    function loadVms() {
        var tbody = document.getElementById('vmTableBody');
        tbody.innerHTML = '<tr><td colspan="10" class="loading">加载中...</td></tr>';
        var params = { current: vmPage, pageSize: 15 };
        var status = document.getElementById('vmFilterStatus').value;
        if (status) params.status = status;

        api('GET', '/vps/vm/fetch', params).then(function(res) {
            var vms = res.data || [];
            var total = res.total || 0;
            if (!vms.length) {
                tbody.innerHTML = '<tr><td colspan="10" class="empty">暂无虚拟机</td></tr>';
                document.getElementById('vmPagination').innerHTML = '';
                return;
            }
            tbody.innerHTML = vms.map(function(v) {
                var userEmail = v.user ? v.user.email : v.user_id;
                var nodeName = v.node ? v.node.name : v.node_id;
                var specs = v.cpu + '核 / ' + v.ram + 'MB / ' + v.disk + 'GB';
                var natPorts = v.nat_port_start ? v.nat_port_start + '-' + v.nat_port_end : '-';
                return '<tr>' +
                    '<td>' + v.id + '</td>' +
                    '<td>' + v.vmid + '</td>' +
                    '<td>' + esc(userEmail) + '</td>' +
                    '<td>' + esc(nodeName) + '</td>' +
                    '<td><code style="font-size:12px">' + specs + '</code></td>' +
                    '<td>' + (v.internal_ip || '-') + '</td>' +
                    '<td><code style="font-size:12px">' + natPorts + '</code></td>' +
                    '<td>' + statusBadge(v.status) + '</td>' +
                    '<td>' + formatTime(v.expired_at) + '</td>' +
                    '<td class="actions">' +
                        '<button class="btn btn-sm btn-default" onclick="VPS.vmDetail(' + v.id + ')">详情</button>' +
                        '<button class="btn btn-sm btn-success" onclick="VPS.vmControl(' + v.id + ', \'start\')" title="启动">▶</button>' +
                        '<button class="btn btn-sm btn-warning" onclick="VPS.vmControl(' + v.id + ', \'shutdown\')" title="关机">⏹</button>' +
                        '<button class="btn btn-sm btn-default" onclick="VPS.vmControl(' + v.id + ', \'reboot\')" title="重启">↻</button>' +
                        '<button class="btn btn-sm btn-danger" onclick="VPS.vmDestroy(' + v.id + ')">删除</button>' +
                    '</td></tr>';
            }).join('');

            // Pagination
            var pages = Math.ceil(total / 15);
            var pag = document.getElementById('vmPagination');
            if (pages <= 1) { pag.innerHTML = ''; return; }
            var html = '';
            for (var i = 1; i <= pages; i++) {
                html += '<button class="' + (i === vmPage ? 'active' : '') + '" onclick="VPS.vmGoPage(' + i + ')">' + i + '</button>';
            }
            pag.innerHTML = html;
        }).catch(function(e) {
            tbody.innerHTML = '<tr><td colspan="10" class="empty">加载失败</td></tr>';
        });
    }

    function vmGoPage(p) { vmPage = p; loadVms(); }

    function vmDetail(id) {
        api('POST', '/vps/vm/detail', { id: id }).then(function(res) {
            var v = res.data;
            var pve = v.pve_status || {};
            var natPorts = (v.nat_ports || []).map(function(p) {
                return p.port_start + '-' + p.port_end;
            }).join(', ') || (v.nat_port_start ? v.nat_port_start + '-' + v.nat_port_end : '-');

            showModal('虚拟机详情 #' + v.id,
                '<div class="detail-grid">' +
                    '<div class="detail-item"><div class="label">VMID</div><div class="value">' + v.vmid + '</div></div>' +
                    '<div class="detail-item"><div class="label">名称</div><div class="value">' + esc(v.hostname) + '</div></div>' +
                    '<div class="detail-item"><div class="label">状态</div><div class="value">' + statusBadge(v.status) + '</div></div>' +
                    '<div class="detail-item"><div class="label">用户</div><div class="value">' + (v.user ? esc(v.user.email) : v.user_id) + '</div></div>' +
                    '<div class="detail-item"><div class="label">节点</div><div class="value">' + (v.node ? esc(v.node.name) : '-') + '</div></div>' +
                    '<div class="detail-item"><div class="label">系统</div><div class="value">' + esc(v.os_name || '-') + '</div></div>' +
                    '<div class="detail-item"><div class="label">CPU</div><div class="value">' + v.cpu + ' 核</div></div>' +
                    '<div class="detail-item"><div class="label">内存</div><div class="value">' + v.ram + ' MB</div></div>' +
                    '<div class="detail-item"><div class="label">磁盘</div><div class="value">' + v.disk + ' GB</div></div>' +
                    '<div class="detail-item"><div class="label">带宽</div><div class="value">' + (v.bandwidth || '不限') + ' Mbps</div></div>' +
                    '<div class="detail-item"><div class="label">内网IP</div><div class="value">' + (v.internal_ip || '-') + '</div></div>' +
                    '<div class="detail-item"><div class="label">NAT端口</div><div class="value">' + natPorts + '</div></div>' +
                '</div>' +
                (pve.error ? '<p style="color:#ff4d4f;margin-top:16px">PVE状态: ' + esc(pve.error) + '</p>' :
                    '<div class="detail-grid" style="margin-top:16px">' +
                    '<div class="detail-item"><div class="label">CPU使用</div><div class="value">' + (pve.cpu_usage ? (pve.cpu_usage * 100).toFixed(1) + '%' : '-') + '</div></div>' +
                    '<div class="detail-item"><div class="label">内存使用</div><div class="value">' + (pve.ram_usage ? Math.round(pve.ram_usage / 1048576) + ' / ' + Math.round(pve.ram_total / 1048576) + ' MB' : '-') + '</div></div>' +
                    '<div class="detail-item"><div class="label">运行时间</div><div class="value">' + (pve.uptime ? Math.floor(pve.uptime / 3600) + 'h' : '-') + '</div></div>' +
                '</div>') +
                '<div style="margin-top:20px;display:flex;gap:8px;flex-wrap:wrap">' +
                    '<button class="btn btn-success" onclick="VPS.vmControl(' + v.id + ',\'start\')">启动</button>' +
                    '<button class="btn btn-warning" onclick="VPS.vmControl(' + v.id + ',\'shutdown\')">关机</button>' +
                    '<button class="btn btn-default" onclick="VPS.vmControl(' + v.id + ',\'reboot\')">重启</button>' +
                    '<button class="btn btn-default" onclick="VPS.vmControl(' + v.id + ',\'stop\')">强制停止</button>' +
                    '<button class="btn btn-primary" onclick="VPS.vmConsole(' + v.id + ')">VNC控制台</button>' +
                    '<button class="btn btn-danger" onclick="VPS.vmDestroy(' + v.id + ')">删除</button>' +
                '</div>'
            );
        }).catch(function(e) { toast(e.message, 'error'); });
    }

    function vmControl(id, action) {
        var labels = { start: '启动', stop: '强制停止', shutdown: '关机', reboot: '重启' };
        if (action === 'stop' && !confirm('确认强制停止？')) return;
        toast('正在' + (labels[action] || action) + '...', 'info');
        api('POST', '/vps/vm/control', { id: id, action: action }).then(function() {
            toast(labels[action] + '成功', 'success');
            loadVms();
        }).catch(function(e) { toast(e.message, 'error'); });
    }

    function vmDestroy(id) {
        if (!confirm('确认删除此虚拟机？此操作不可逆！')) return;
        if (!confirm('再次确认：删除将销毁虚拟机及所有数据。')) return;
        api('POST', '/vps/vm/destroy', { id: id }).then(function() {
            toast('删除成功', 'success');
            closeModal();
            loadVms();
        }).catch(function(e) { toast(e.message, 'error'); });
    }

    function vmConsole(id) {
        api('POST', '/vps/vm/console', { id: id }).then(function(res) {
            var d = res.data;
            showModal('VNC控制台',
                '<div style="text-align:center">' +
                '<p>请使用以下信息连接VNC：</p>' +
                '<div class="detail-grid" style="margin-top:12px">' +
                    '<div class="detail-item"><div class="label">Host</div><div class="value" style="font-size:14px;word-break:break-all">' + esc(d.host) + '</div></div>' +
                    '<div class="detail-item"><div class="label">Port</div><div class="value">' + d.port + '</div></div>' +
                '</div>' +
                '<p style="margin-top:12px;font-size:12px;color:#999">WebSocket URL:</p>' +
                '<code style="font-size:11px;word-break:break-all;display:block;padding:8px;background:#f5f5f5;border-radius:4px">' + esc(d.websocket_url) + '</code>' +
                '</div>'
            );
        }).catch(function(e) { toast(e.message, 'error'); });
    }

    function showVmCreateForm() {
        showModal('创建虚拟机',
            '<form id="vmCreateForm">' +
            '<div class="form-group"><label><span class="required">*</span> 用户ID</label><input class="form-control" name="user_id" type="number" required></div>' +
            '<div class="form-group"><label><span class="required">*</span> 套餐ID (VPS类型)</label><input class="form-control" name="plan_id" type="number" required></div>' +
            '<div class="form-group"><label><span class="required">*</span> 模板ID</label><input class="form-control" name="template_id" type="number" required></div>' +
            '<div class="form-group"><label>指定节点ID (留空自动选择)</label><input class="form-control" name="node_id" type="number"></div>' +
            '<div class="form-actions">' +
                '<button type="button" class="btn btn-default" onclick="VPS.closeModal()">取消</button>' +
                '<button type="submit" class="btn btn-primary">创建</button>' +
            '</div></form>'
        );
        document.getElementById('vmCreateForm').onsubmit = function(e) {
            e.preventDefault();
            var fd = new FormData(this);
            var data = {};
            fd.forEach(function(v, k) { if (v !== '') data[k] = parseInt(v); });
            toast('正在创建虚拟机...', 'info');
            api('POST', '/vps/vm/create', data).then(function(res) {
                var d = res.data;
                toast('创建成功! VMID: ' + d.vmid, 'success');
                closeModal();
                showModal('虚拟机创建完成',
                    '<div style="text-align:center">' +
                    '<p style="font-size:18px;color:#52c41a;margin-bottom:16px">✓ 虚拟机创建成功</p>' +
                    '<div class="detail-grid">' +
                        '<div class="detail-item"><div class="label">VM ID</div><div class="value">' + d.id + '</div></div>' +
                        '<div class="detail-item"><div class="label">VMID</div><div class="value">' + d.vmid + '</div></div>' +
                    '</div>' +
                    '<div style="margin-top:16px;padding:12px;background:#f6ffed;border:1px solid #b7eb8f;border-radius:4px">' +
                        '<p style="font-weight:600;margin-bottom:4px">初始密码（仅显示一次）</p>' +
                        '<code style="font-size:16px;color:#333">' + esc(d.password) + '</code>' +
                    '</div></div>'
                );
                loadVms();
            }).catch(function(e) { toast(e.message, 'error'); });
        };
    }

    // ========== TAB NAVIGATION ==========
    var titles = { nodes: '节点管理', templates: '系统模板', vms: '虚拟机管理' };
    var loaders = { nodes: loadNodes, templates: loadTemplates, vms: loadVms };

    function switchTab(tab) {
        document.querySelectorAll('.tab-panel').forEach(function(el) { el.classList.remove('active'); });
        document.querySelectorAll('.nav-item[data-tab]').forEach(function(el) { el.classList.remove('active'); });
        document.getElementById('tab-' + tab).classList.add('active');
        var navEl = document.querySelector('.nav-item[data-tab="' + tab + '"]');
        if (navEl) navEl.classList.add('active');
        document.getElementById('pageTitle').textContent = titles[tab] || tab;
        if (loaders[tab]) loaders[tab]();
    }

    // Init
    document.querySelectorAll('.nav-item[data-tab]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            switchTab(this.dataset.tab);
            history.replaceState(null, '', '#' + this.dataset.tab);
        });
    });

    var initTab = location.hash.replace('#', '') || 'nodes';
    if (!titles[initTab]) initTab = 'nodes';
    switchTab(initTab);

    // Export
    window.VPS = {
        loadNodes: loadNodes,
        showNodeForm: showNodeForm,
        testNode: testNode,
        syncNode: syncNode,
        showNatInit: showNatInit,
        dropNode: dropNode,
        loadTemplates: loadTemplates,
        showTemplateForm: showTemplateForm,
        dropTemplate: dropTemplate,
        loadVms: loadVms,
        vmGoPage: vmGoPage,
        vmDetail: vmDetail,
        vmControl: vmControl,
        vmDestroy: vmDestroy,
        vmConsole: vmConsole,
        showVmCreateForm: showVmCreateForm,
        closeModal: closeModal
    };
})();
