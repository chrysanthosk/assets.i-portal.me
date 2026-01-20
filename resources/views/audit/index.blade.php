@extends('layouts.app')

@section('content')
<style>
    /* Make audit expanded row + code blocks follow Bootstrap theme (dark/light) */
    .audit-expanded-cell {
        background-color: var(--bs-tertiary-bg) !important;
        color: var(--bs-body-color) !important;
    }

    .audit-pre {
        background-color: var(--bs-body-bg) !important;
        color: var(--bs-body-color) !important;
        border: 1px solid var(--bs-border-color) !important;
        white-space: pre-wrap;      /* wrap long JSON */
        word-break: break-word;     /* wrap very long strings */
    }
</style>

<div class="row">
    <div class="col-12">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="mb-0">Audit Logs</h4>
                <div class="text-muted">Filter by user, entity, action and date range. Click a row to expand details.</div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('audit.index') }}" class="row g-2 align-items-end">

                    <div class="col-lg-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="q" class="form-control" value="{{ $q }}" placeholder="action / entity / ip / json...">
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label">User</label>
                        <select name="user_id" class="form-select">
                            <option value="">All users</option>
                            @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected((int)$userId === (int)$u->id)>
                                {{ $u->name }} ({{ $u->email }})
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label">Entity</label>
                        <select name="entity" class="form-select">
                            <option value="">All entities</option>
                            @foreach($entities as $e)
                            <option value="{{ $e }}" @selected($entity === $e)>{{ $e }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <label class="form-label">Action</label>
                        <select name="action" class="form-select">
                            <option value="">All actions</option>
                            @foreach($actions as $a)
                            <option value="{{ $a }}" @selected($action === $a)>{{ $a }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-1">
                        <label class="form-label">From</label>
                        <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                    </div>

                    <div class="col-lg-1">
                        <label class="form-label">To</label>
                        <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                    </div>

                    <div class="col-lg-1 d-grid">
                        <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                    </div>

                    <div class="col-lg-1 d-grid">
                        <a href="{{ route('audit.index') }}" class="btn btn-outline-secondary">Reset</a>
                    </div>

                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <strong>Logs</strong>
                    <span class="text-muted ms-2">({{ $logs->total() }} total)</span>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                        <tr>
                            <th style="width: 90px;">ID</th>
                            <th style="width: 170px;">Time</th>
                            <th style="width: 220px;">User</th>
                            <th style="width: 220px;">Action</th>
                            <th style="width: 160px;">Entity</th>
                            <th style="width: 90px;" class="text-end">Entity ID</th>
                            <th>IP</th>
                            <th class="text-end" style="width: 80px;">View</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($logs as $log)
                        @php
                        $rowId = "auditRow".$log->id;
                        $meta = $log->meta ?? [];
                        $old = $log->old_values ?? ($meta['old'] ?? null);
                        $new = $log->new_values ?? ($meta['new'] ?? null);
                        @endphp

                        <tr>
                            <td class="fw-semibold">#{{ $log->id }}</td>
                            <td class="text-muted">{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
                            <td>
                                @if($log->user)
                                <div class="fw-semibold">{{ $log->user->name }}</div>
                                <div class="text-muted small">{{ $log->user->email }}</div>
                                @else
                                <span class="text-muted">system</span>
                                @endif
                            </td>
                            <td class="fw-semibold">{{ $log->action }}</td>
                            <td>{{ $log->entity }}</td>
                            <td class="text-end">{{ $log->entity_id ?? '—' }}</td>
                            <td class="text-muted">{{ $log->ip ?: '—' }}</td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#{{ $rowId }}"
                                        aria-expanded="false" aria-controls="{{ $rowId }}">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </td>
                        </tr>

                        <tr class="collapse" id="{{ $rowId }}">
                            <td colspan="8" class="audit-expanded-cell">
                                <div class="p-3">
                                    <div class="row g-3">

                                        <div class="col-lg-4">
                                            <div class="text-muted small">Auditable</div>
                                            <div class="fw-semibold">{{ $log->auditable_type ?: '—' }}</div>
                                            <div class="text-muted">auditable_id: {{ $log->auditable_id ?? '—' }}</div>
                                        </div>

                                        <div class="col-lg-4">
                                            <div class="text-muted small">User Agent</div>
                                            <div class="text-muted small" style="word-break: break-word;">
                                                {{ $log->user_agent ?: '—' }}
                                            </div>
                                        </div>

                                        <div class="col-lg-4">
                                            <div class="text-muted small">Meta</div>
                                            <pre class="mb-0 small p-2 rounded audit-pre" style="max-height: 220px; overflow:auto;">{{ json_encode($log->meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>

                                        <div class="col-lg-6">
                                            <div class="text-muted small">Old values</div>
                                            <pre class="mb-0 small p-2 rounded audit-pre" style="max-height: 260px; overflow:auto;">{{ json_encode($old, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>

                                        <div class="col-lg-6">
                                            <div class="text-muted small">New values</div>
                                            <pre class="mb-0 small p-2 rounded audit-pre" style="max-height: 260px; overflow:auto;">{{ json_encode($new, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>

                                    </div>
                                </div>
                            </td>
                        </tr>

                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No logs found.</td>
                        </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer">
                {{ $logs->links() }}
            </div>
        </div>

    </div>
</div>
@endsection
