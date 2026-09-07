@extends('layouts.app')

@section('content')
<div class="card">
  <div class="card-header"><h5 class="mb-0">Portal Settings</h5></div>
  <div class="card-body">
    <form method="POST" action="{{ route('settings.portal.update') }}" class="row g-3">
      @csrf
      <div class="col-md-6">
        <label class="form-label">Portal Name</label>
        <input class="form-control @error('portal_name') is-invalid @enderror" name="portal_name" value="{{ old('portal_name', $portalName) }}" required>
        @error('portal_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
      </div>

      <div class="col-12"><h6 class="mt-3 mb-0">Rent reminders</h6><hr class="mt-1"></div>

      <div class="col-12">
        <input type="hidden" name="rent_reminders_enabled" value="0">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="rent_reminders_enabled"
                 name="rent_reminders_enabled" value="1" @checked(old('rent_reminders_enabled', $rentRemindersEnabled ? '1' : '0') === '1')>
          <label class="form-check-label" for="rent_reminders_enabled">
            Email me on each rent due date asking whether the money arrived, and keep asking until I answer
          </label>
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label">Send reminders to</label>
        <input class="form-control @error('rent_reminder_email') is-invalid @enderror" name="rent_reminder_email"
               value="{{ old('rent_reminder_email', $rentReminderEmail) }}" placeholder="you@example.com, partner@example.com">
        @error('rent_reminder_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">
          Comma-separated. Leave blank to use every Admin user's email
          @if($fallbackRecipients) (currently: {{ implode(', ', $fallbackRecipients) }}) @endif.
        </div>
      </div>

      <div class="col-md-3">
        <label class="form-label">Rent due on day</label>
        <input type="number" min="1" max="28" class="form-control @error('rent_due_day') is-invalid @enderror"
               name="rent_due_day" value="{{ old('rent_due_day', $rentDueDay) }}" required>
        @error('rent_due_day') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">Day of the month expected payments are due (1–28).</div>
      </div>

      <div class="col-md-3">
        <label class="form-label">Repeat every (days)</label>
        <input type="number" min="1" max="30" class="form-control @error('rent_reminder_repeat_days') is-invalid @enderror"
               name="rent_reminder_repeat_days" value="{{ old('rent_reminder_repeat_days', $rentRepeatDays) }}" required>
        @error('rent_reminder_repeat_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">Until the payment is confirmed either way.</div>
      </div>

      <div class="col-12 form-text">
        Expected payments are created automatically each month from active rental agreements. Emails use the SMTP
        server under Settings → SMTP; make sure it is enabled and tested.
      </div>

      <div class="col-12">
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>
@endsection
