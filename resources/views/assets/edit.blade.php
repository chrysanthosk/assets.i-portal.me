@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-xxl-10">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="mb-0">Edit property</h4>
                <div class="text-muted small">{{ $asset->name }}</div>
            </div>
            <a href="{{ route('assets.show', $asset) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> View</a>
        </div>

        <form method="POST" action="{{ route('assets.update', $asset) }}">
            @csrf
            @method('PUT')
            @include('assets._form')

            <div class="d-flex justify-content-between align-items-center gap-2 mb-4">
                <div class="text-muted small">Documents are managed on the property page.</div>
                <div class="d-flex gap-2">
                    <a href="{{ route('assets.show', $asset) }}" class="btn btn-outline-secondary">Cancel</a>
                    <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Save changes</button>
                </div>
            </div>
        </form>

    </div>
</div>
@endsection
