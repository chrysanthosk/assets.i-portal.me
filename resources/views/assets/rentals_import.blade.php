@extends('layouts.app')
@section('title', 'Import contract')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <h5 class="mb-0"><i class="bi bi-file-earmark-arrow-up me-1"></i> Import agreement from a contract</h5>
                <a href="{{ route('assets.rentals.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Upload the signed contract (PDF or photo). The parties, period, currency and the payment schedule are
                    read for you: a fixed monthly rent, or dated instalments that repeat every contract year. You review
                    everything before the agreement is created, and the contract is filed with the property's documents.
                </p>
                @unless($configured)
                    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i> No Anthropic API key is configured.
                        @can('manage_portal_settings')<a href="{{ route('settings.portal.edit') }}" class="alert-link">Add one under Settings → Portal</a>.@endcan</div>
                @endunless
                <form method="POST" action="{{ route('assets.rentals.import.store') }}" enctype="multipart/form-data" id="contractImportForm">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="contractFile">Contract (PDF, JPG, PNG · max 20 MB)</label>
                        <input type="file" id="contractFile" name="file" accept=".pdf,image/*" class="form-control @error('file') is-invalid @enderror" required @disabled(! $configured)>
                        @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button class="btn btn-primary" @disabled(! $configured)><i class="bi bi-magic me-1"></i> Read contract</button>
                    <span class="text-muted small ms-2">Takes 10–40 seconds.</span>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Recent imports</h6></div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm mb-0 align-middle"><tbody>
                @forelse($recent as $r)
                    <tr>
                        <td><div class="text-truncate" style="max-width: 220px;">{{ $r->original_name }}</div><div class="small text-muted">{{ $r->created_at->diffForHumans() }}</div></td>
                        <td>
                            @if($r->status === 'completed' && $r->asset)<a href="{{ route('assets.show', [$r->asset, 'tab' => 'agreements']) }}" class="badge text-bg-success text-decoration-none">Created</a>
                            @elseif($r->status === 'extracted')<a href="{{ route('assets.rentals.import.review', $r) }}" class="badge text-bg-warning text-decoration-none">Review</a>
                            @elseif($r->status === 'failed')<span class="badge text-bg-danger" title="{{ $r->error }}">Failed</span>
                            @else<span class="badge text-bg-secondary">{{ ucfirst($r->status) }}</span>@endif
                        </td>
                        <td class="text-end">
                            @if($r->status !== 'completed')
                                <form method="POST" action="{{ route('assets.rentals.import.destroy', $r) }}" class="d-inline" onsubmit="return confirm('Discard this import?');">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" aria-label="Discard"><i class="bi bi-trash"></i></button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="text-muted text-center py-3">No imports yet.</td></tr>
                @endforelse
                </tbody></table>
            </div>
        </div>
    </div>
</div>
@endsection
