@extends('layouts.app')
@section('title', __('platform.ticket_new'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item">
        <a href="{{ route('app.support.tickets.index') }}">{{ __('platform.support_ticket') }}</a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('platform.ticket_new') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('platform.ticket_new')" />

    <div class="card" style="max-width: 40rem">
        <div class="card-body">
            <form method="POST" action="{{ route('app.support.tickets.store') }}">
                @csrf

                <div class="mb-3">
                    <label for="subject" class="form-label">{{ __('platform.ticket_subject') }}</label>
                    <input id="subject" name="subject" type="text" required maxlength="255"
                           value="{{ old('subject') }}"
                           class="form-control @error('subject') is-invalid @enderror">
                    @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="body" class="form-label">{{ __('platform.ticket_message') }}</label>
                    <textarea id="body" name="body" rows="6" required maxlength="5000"
                              class="form-control @error('body') is-invalid @enderror">{{ old('body') }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <button class="btn btn-primary">{{ __('platform.ticket_submit') }}</button>
            </form>
        </div>
    </div>
@endsection
