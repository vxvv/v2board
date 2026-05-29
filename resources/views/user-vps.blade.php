<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>我的VPS - {{$title}}</title>
    <link rel="stylesheet" href="/assets/user/vps.css?v={{$version}}">
</head>
<body>
<div id="app">
    <header class="header">
        <div class="header-inner">
            <div class="header-left">
                @if($logo)<img src="{{$logo}}" alt="">@endif
                <h1 class="header-title">{{$title}}</h1>
            </div>
            <nav class="header-nav">
                <a href="/">控制面板</a>
                <a href="/vps" class="active">我的VPS</a>
            </nav>
        </div>
    </header>

    <main class="main">
        <!-- VM List -->
        <div id="vmListView">
            <div class="section-header">
                <h2>我的虚拟机</h2>
            </div>
            <div id="vmCards" class="vm-grid">
                <div class="loading">加载中</div>
            </div>
        </div>

        <!-- VM Detail -->
        <div id="vmDetailView" style="display:none">
            <div class="section-header">
                <button class="btn btn-text" onclick="UserVPS.backToList()">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    返回列表
                </button>
                <h2 id="vmDetailTitle">虚拟机详情</h2>
            </div>
            <div class="detail-cards" id="vmDetailContent"></div>
        </div>
    </main>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modalOverlay" onclick="UserVPS.closeModal()">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 id="modalTitle">操作</h3>
            <button class="modal-close" onclick="UserVPS.closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script src="/assets/user/vps.js?v={{$version}}"></script>
</body>
</html>
