// VPS Management link - injected into admin panel
(function() {
    function addVpsLink() {
        if (document.getElementById('vps-admin-link')) return;
        var btn = document.createElement('a');
        btn.id = 'vps-admin-link';
        btn.href = '/' + (window.settings && window.settings.secure_path || 'admin') + '/vps';
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:6px"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>VPS管理';
        btn.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;background:#1890ff;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;box-shadow:0 4px 12px rgba(24,144,255,0.4);transition:all 0.2s;display:flex;align-items:center';
        btn.onmouseenter = function() { this.style.background = '#40a9ff'; this.style.transform = 'translateY(-2px)'; };
        btn.onmouseleave = function() { this.style.background = '#1890ff'; this.style.transform = 'none'; };
        document.body.appendChild(btn);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addVpsLink);
    } else {
        addVpsLink();
    }
})();
