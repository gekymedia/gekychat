@extends('layouts.admin')

@section('title', 'Backups & Google Drive')

@section('content')
@php
    $s = $status ?? [];
@endphp
<main id="main" class="main">
    <div class="pagetitle d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1>Backups & Google Drive</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route(config('services.google_backup.backups_dashboard_route', 'admin.dashboard')) }}">Home</a></li>
                    <li class="breadcrumb-item active">Backups</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="text-muted small" id="lastChecked">Checked: {{ $s['checked_at'] ?? '—' }}</span>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="refreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <section class="section">
        @if(empty($s['drive_configured']))
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Google Drive is not connected. Backups will stay on the server only until you
                <a href="{{ route(config('services.google_backup.google_auth_route', 'admin.google-auth.start')) }}" class="alert-link">re-authenticate with Google</a>.
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Google Drive</h6>
                        @if($s['drive_configured'] ?? false)
                            <span class="badge bg-success">Connected</span>
                        @else
                            <span class="badge bg-danger">Not connected</span>
                        @endif
                        <div class="small mt-2 text-muted">{{ $s['drive_folder'] ?? 'CUG Portal Backups' }}</div>
                        @if(!empty($s['drive_search_url']))
                            <a href="{{ $s['drive_search_url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mt-2">
                                <i class="bi bi-google me-1"></i>Open in Drive
                            </a>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Database backup</h6>
                        <div id="dbStatusBadge">
                            @if($s['database_running'] ?? false)
                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Running</span>
                            @else
                                <span class="badge bg-secondary">Idle</span>
                            @endif
                        </div>
                        <div class="small mt-2" id="lastDbBackup">
                            @if(!empty($s['last_database_backup']))
                                Last local: <strong>{{ $s['last_database_backup']['modified_at'] }}</strong><br>
                                <span class="text-muted">{{ $s['last_database_backup']['filename'] }} ({{ $s['last_database_backup']['size_human'] }})</span>
                            @else
                                <span class="text-muted">No local DB backup found</span>
                            @endif
                        </div>
                        <div class="small text-muted mt-1">{{ $s['schedule']['database'] ?? '' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Files backup</h6>
                        <div id="filesStatusBadge">
                            @if($s['files_running'] ?? false)
                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Running</span>
                            @else
                                <span class="badge bg-secondary">Idle</span>
                            @endif
                        </div>
                        <div class="small mt-2" id="filesProgressWrap">
                            @if(!empty($s['upload_progress']))
                                @php $p = $s['upload_progress']; @endphp
                                @if(($p['total'] ?? 0) > 0)
                                    <div class="mb-1">{{ number_format($p['uploaded']) }} / {{ number_format($p['total']) }} files ({{ $p['percent'] }}%)</div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: {{ $p['percent'] }}%"></div>
                                    </div>
                                @else
                                    <div class="mb-1">{{ number_format($p['uploaded']) }} files uploaded so far</div>
                                @endif
                                @if(!empty($p['current_path']))
                                    <div class="text-muted mt-1">Current: {{ $p['current_path'] }}</div>
                                @endif
                            @elseif(!empty($s['last_files_backup_line']))
                                <span class="text-muted">{{ $s['last_files_backup_line'] }}</span>
                            @else
                                <span class="text-muted">No recent files backup activity</span>
                            @endif
                        </div>
                        <div class="small text-muted mt-1">{{ $s['schedule']['files'] ?? '' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">Server disk</h6>
                        @php
                            $usedPct = $s['disk']['used_percent'] ?? null;
                            $diskClass = $usedPct >= 95 ? 'danger' : ($usedPct >= 85 ? 'warning' : 'success');
                        @endphp
                        <span class="badge bg-{{ $diskClass }}" id="diskBadge">
                            {{ $usedPct !== null ? $usedPct.'% used' : '—' }}
                        </span>
                        <div class="small mt-2" id="diskDetail">
                            {{ $s['disk']['free_human'] ?? '—' }} free of {{ $s['disk']['total_human'] ?? '—' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($s['local_backups']))
            <div class="card mb-4">
                <div class="card-header"><strong>Local database backups</strong> <span class="text-muted small">(also uploaded to Drive when connected)</span></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>File</th>
                                    <th>Size</th>
                                    <th>Modified</th>
                                </tr>
                            </thead>
                            <tbody id="localBackupsBody">
                                @foreach($s['local_backups'] as $b)
                                    <tr>
                                        <td><code>{{ $b['filename'] }}</code></td>
                                        <td>{{ $b['size_human'] }}</td>
                                        <td>{{ $b['modified_at'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Files backup log</strong>
                        <span class="text-muted small">storage/logs/files-backup.log</span>
                    </div>
                    <div class="card-body p-0">
                        <pre class="mb-0 p-3 bg-dark text-light small log-panel" id="filesLogPanel" style="max-height: 420px; overflow: auto;">@foreach($s['files_log'] ?? [] as $line){{ $line }}
@endforeach</pre>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Database backup log</strong>
                        <span class="text-muted small">database-backup.log</span>
                    </div>
                    <div class="card-body p-0">
                        <pre class="mb-0 p-3 bg-dark text-light small log-panel" id="databaseLogPanel" style="max-height: 420px; overflow: auto;">@foreach($s['database_log'] ?? [] as $line){{ $line }}
@endforeach</pre>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
(function () {
    const statusUrl = @json(route(config('services.google_backup.backups_status_route', 'admin.backups.status')));
    const refreshBtn = document.getElementById('refreshBtn');
    let timer = null;

    function badgeHtml(running, idleLabel) {
        return running
            ? '<span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Running</span>'
            : '<span class="badge bg-secondary">' + idleLabel + '</span>';
    }

    function renderProgress(p) {
        if (!p) {
            return '<span class="text-muted">No recent files backup activity</span>';
        }
        let html = '';
        if (p.total > 0) {
            html = '<div class="mb-1">' + p.uploaded.toLocaleString() + ' / ' + p.total.toLocaleString()
                + ' files (' + p.percent + '%)</div>';
            html += '<div class="progress" style="height: 8px;"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width: ' + p.percent + '%"></div></div>';
        } else {
            html = '<div class="mb-1">' + p.uploaded.toLocaleString() + ' files uploaded so far</div>';
        }
        if (p.current_path) {
            html += '<div class="text-muted mt-1">Current: ' + escapeHtml(p.current_path) + '</div>';
        }
        return html;
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function renderLog(lines) {
        return (lines || []).map(escapeHtml).join('\n');
    }

    function diskClass(pct) {
        if (pct >= 95) return 'danger';
        if (pct >= 85) return 'warning';
        return 'success';
    }

    function applyStatus(data) {
        document.getElementById('lastChecked').textContent = 'Checked: ' + (data.checked_at || '—');
        document.getElementById('dbStatusBadge').innerHTML = badgeHtml(data.database_running, 'Idle');
        document.getElementById('filesStatusBadge').innerHTML = badgeHtml(data.files_running, 'Idle');
        document.getElementById('filesProgressWrap').innerHTML = data.files_running
            ? renderProgress(data.upload_progress)
            : (data.last_files_backup_line
                ? '<span class="text-muted">' + escapeHtml(data.last_files_backup_line) + '</span>'
                : '<span class="text-muted">No recent files backup activity</span>');

        if (data.last_database_backup) {
            const b = data.last_database_backup;
            document.getElementById('lastDbBackup').innerHTML =
                'Last local: <strong>' + escapeHtml(b.modified_at) + '</strong><br>'
                + '<span class="text-muted">' + escapeHtml(b.filename) + ' (' + escapeHtml(b.size_human) + ')</span>';
        }

        if (data.disk) {
            const pct = data.disk.used_percent;
            const cls = diskClass(pct);
            document.getElementById('diskBadge').className = 'badge bg-' + cls;
            document.getElementById('diskBadge').textContent = (pct !== null ? pct + '% used' : '—');
            document.getElementById('diskDetail').textContent =
                (data.disk.free_human || '—') + ' free of ' + (data.disk.total_human || '—');
        }

        document.getElementById('filesLogPanel').textContent = renderLog(data.files_log);
        document.getElementById('databaseLogPanel').textContent = renderLog(data.database_log);

        const tbody = document.getElementById('localBackupsBody');
        if (tbody && Array.isArray(data.local_backups)) {
            tbody.innerHTML = data.local_backups.map(function (b) {
                return '<tr><td><code>' + escapeHtml(b.filename) + '</code></td><td>'
                    + escapeHtml(b.size_human) + '</td><td>' + escapeHtml(b.modified_at) + '</td></tr>';
            }).join('');
        }

        const panels = document.querySelectorAll('.log-panel');
        panels.forEach(function (el) { el.scrollTop = el.scrollHeight; });
    }

    function refresh() {
        fetch(statusUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(applyStatus)
            .catch(function () {});
    }

    refreshBtn.addEventListener('click', refresh);
    timer = setInterval(refresh, 15000);
})();
</script>
@endpush
