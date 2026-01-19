@extends('layouts.app')

@section('content')
  <div class="card">
    <div class="card-body">
      <h4 class="mb-0">{{ $greeting }}, {{ $fullName }} 👋</h4>
      <p class="text-muted mb-0">Welcome to your portal.</p>
    </div>
  </div>
@endsection
