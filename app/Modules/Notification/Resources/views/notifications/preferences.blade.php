@extends('layouts.app')
@section('title', __('notification.preferences'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('app.dashboard') }}">{{ __('nav.dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('app.notifications') }}">{{ __('notification.notifications') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('notification.preferences') }}</li>
@endsection

@section('content')
    <x-page-header :title="__('notification.preferences')" :subtitle="__('notification.channels_hint')" />

    <form method="POST" action="{{ route('app.notifications.preferences.save') }}">
        @csrf

        <div class="card">
            <div class="card-header">
                <i class="cil-bell" aria-hidden="true"></i>
                <span>{{ __('notification.preferences') }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('notification.event_label') }}</th>
                            <th class="text-center">{{ __('notification.channel_in_app') }}</th>
                            <th class="text-center">{{ __('notification.channel_email') }}</th>
                            <th class="text-center">{{ __('notification.channel_sms') }}</th>
                            <th class="text-center">{{ __('notification.channel_whatsapp') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            @php $preference = $preferences[$event]; @endphp

                            <tr>
                                <td>{{ __('notification.event_'.strtolower($event)) }}</td>

                                <td class="text-center">
                                    {{-- Always on, and shown as such rather than
                                         hidden: the record of what happened is
                                         part of the audit trail, not a
                                         preference. --}}
                                    <input type="checkbox" class="form-check-input" checked disabled
                                           aria-label="{{ __('notification.channel_in_app') }}"
                                           title="{{ __('notification.in_app_always_on') }}">
                                </td>

                                @foreach (['email', 'sms', 'whatsapp'] as $channel)
                                    <td class="text-center">
                                        <input type="hidden" name="preferences[{{ $event }}][{{ $channel }}]" value="0">
                                        <input type="checkbox" class="form-check-input"
                                               name="preferences[{{ $event }}][{{ $channel }}]" value="1"
                                               @checked($preference->{$channel})
                                               aria-label="{{ __('notification.channel_'.$channel) }}">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card-body border-top">
                <div class="form-text mb-2">{{ __('notification.in_app_always_on') }}</div>
                {{-- Stated plainly rather than letting somebody switch on email
                     and wonder why nothing arrives. --}}
                <div class="alert alert-secondary py-2 mb-3 small">
                    <strong>{{ __('notification.not_yet_delivered') }}</strong> —
                    {{ __('notification.not_yet_delivered_hint') }}
                </div>

                <button class="btn btn-info text-white">{{ __('common.save') }}</button>
            </div>
        </div>
    </form>
@endsection
