@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-12 col-xxl-10">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <h4 class="mb-0">Add property</h4>
            <a href="{{ route('assets.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
        </div>

        <div class="alert alert-primary d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <i class="bi bi-magic me-1"></i>
                <strong>Have the title deed?</strong> Upload the scan and this form is filled in for you.
            </div>
            <a href="{{ route('assets.import.create') }}" class="btn btn-primary btn-sm">Import title deed</a>
        </div>

        <form method="POST" action="{{ route('assets.store') }}">
            @csrf
            @include('assets._form', ['asset' => null])

            <div class="d-flex justify-content-end gap-2 mb-4">
                <a href="{{ route('assets.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i> Create property</button>
            </div>
        </form>

    </div>
</div>
@endsection
