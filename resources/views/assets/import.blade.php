@extends('layouts.app')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <h5 class="mb-0"><i class="bi bi-file-earmark-arrow-up me-1"></i> Import property from title deed</h5>
                <a href="{{ route('assets.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Upload the scanned deed (Κτηματική Σελίδα) as a PDF or photo. The registration number, location,
                    plot reference, owner share, areas and valuations are read automatically. You review everything
                    before the property is created, and the scan is attached as its title-deed document.
                </p>

                @unless($configured)
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        No Anthropic API key is configured, so extraction cannot run.
                        @can('manage_portal_settings')
                            <a href="{{ route('settings.portal.edit') }}" class="alert-link">Add one under Settings → Portal</a>.
                        @else
                            Ask an administrator to add one under Settings → Portal.
                        @endcan
                    </div>
                @endunless

                <form method="POST" action="{{ route('assets.import.store') }}" enctype="multipart/form-data" id="deedImportForm">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="deedFile">Title deed scan (PDF, JPG, PNG, WEBP · max 20 MB)</label>
                        <input type="file" id="deedFile" name="file" accept=".pdf,image/*"
                               class="form-control @error('file') is-invalid @enderror" required @disabled(! $configured)>
                        @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button class="btn btn-primary" id="deedImportBtn" @disabled(! $configured)>
                        <i class="bi bi-magic me-1"></i> Read deed
                    </button>
                    <span class="text-muted small ms-2">Takes 10–40 seconds.</span>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Recent imports</h6></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <tbody>
                    @forelse($recent as $r)
                        <tr>
                            <td>
                                <div class="text-truncate" style="max-width: 220px;">{{ $r->original_name }}</div>
                                <div class="small text-muted">{{ $r->created_at->diffForHumans() }}</div>
                            </td>
                            <td>
                                @if($r->status === 'completed' && $r->asset)
                                    <a href="{{ route('assets.show', $r->asset) }}" class="badge text-bg-success text-decoration-none">Created</a>
                                @elseif($r->status === 'extracted')
                                    <a href="{{ route('assets.import.review', $r) }}" class="badge text-bg-warning text-decoration-none">Review</a>
                                @elseif($r->status === 'failed')
                                    <span class="badge text-bg-danger" title="{{ $r->error }}">Failed</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ ucfirst($r->status) }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($r->status !== 'completed')
                                <form method="POST" action="{{ route('assets.import.destroy', $r) }}" class="d-inline"
                                      onsubmit="return confirm('Discard this import?');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" aria-label="Discard"><i class="bi bi-trash"></i></button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted text-center py-3">No imports yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('deedImportForm')?.addEventListener('submit', function () {
        const btn = document.getElementById('deedImportBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Reading deed…';
    });
</script>
@endsection
