{{--
    The seven timestamps (SRS 17).

    Shown as a chain rather than as two fields, because response time and repair
    time answer different questions and a record with only "down at" and "up at"
    cannot separate a slow maintenance team from a slow reporting culture.

    Rendered with @dt, which converts the stored UTC instant to the factory's
    clock. Printing UTC here would be a six-hour lie that looks entirely
    plausible (SRS 47.2).
--}}
<div class="card mb-3">
    <div class="card-header">
        <i class="cil-clock" aria-hidden="true"></i>
        <span>{{ __('breakdown.chain') }}</span>

        @if ($breakdown->isOpen())
            <span class="ms-auto">
                <x-status-pill status="OPEN" tone="danger">{{ __('breakdown.still_running') }}</x-status-pill>
            </span>
        @endif
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <tbody>
                @foreach (App\Modules\Breakdown\Models\Breakdown::TIMESTAMP_CHAIN as $field)
                    @php $value = $breakdown->{$field}; @endphp

                    <tr>
                        <td style="width: 14rem">{{ __("breakdown.{$field}") }}</td>
                        <td class="{{ $value === null ? 'text-body-secondary' : 'fw-semibold' }}">
                            @if ($value === null)
                                {{ __('breakdown.not_recorded') }}
                            @else
                                @dt($value)
                            @endif
                        </td>
                        <td class="text-end">
                            @can('breakdown.breakdown.repair')
                                @unless ($breakdown->isTerminal())
                                    {{-- Editable, because a machine that stopped at
                                         21:50 and was reported at 06:10 is real, and
                                         forcing "now" onto every stamp would make the
                                         whole chain fiction. Corrections are recorded
                                         in the history. --}}
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-icon"
                                            data-coreui-toggle="modal"
                                            data-coreui-target="#ts-{{ $field }}"
                                            title="{{ __('breakdown.correct_timestamp') }}"
                                            aria-label="{{ __('breakdown.correct_timestamp') }}">
                                        <i class="cil-pencil" aria-hidden="true"></i>
                                    </button>
                                @endunless
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@can('breakdown.breakdown.repair')
    @unless ($breakdown->isTerminal())
        @foreach (App\Modules\Breakdown\Models\Breakdown::TIMESTAMP_CHAIN as $field)
            <div class="modal fade" id="ts-{{ $field }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST"
                          action="{{ route('app.breakdowns.timestamp', $breakdown) }}">
                        @csrf
                        <input type="hidden" name="field" value="{{ $field }}">

                        <div class="modal-header">
                            <h5 class="modal-title">{{ __("breakdown.{$field}") }}</h5>
                            <button type="button" class="btn-close" data-coreui-dismiss="modal"
                                    aria-label="{{ __('common.cancel') }}"></button>
                        </div>
                        <div class="modal-body">
                            <label for="value-{{ $field }}" class="form-label">
                                {{ __('breakdown.correct_timestamp') }}
                            </label>
                            {{-- Pre-filled on the factory's clock, and read back on
                                 it: a datetime-local input carries no timezone of its
                                 own, so both ends have to agree explicitly. --}}
                            <input id="value-{{ $field }}" name="value" type="datetime-local"
                                   class="form-control" required
                                   value="@dtinput($breakdown->{$field})">
                            <div class="form-text">
                                {{ __('breakdown.chain') }} — {{ app(App\Shared\Support\TenantTimezone::class)->current() }}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-coreui-dismiss="modal">
                                {{ __('common.cancel') }}
                            </button>
                            <button type="submit" class="btn btn-info text-white">{{ __('common.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endunless
@endcan
