@extends('layouts.admin.app')

@section('title', translate('api_logs') . ' — ' . $key->name)

@section('content')
<div class="content container-fluid">

    {{-- Breadcrumb --}}
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="{{ route('admin.reseller-keys.list') }}" class="text-muted text-decoration-none">
            <i class="fi fi-rr-arrow-left me-1"></i>{{ translate('reseller_api_keys') }}
        </a>
        <span class="text-muted">/</span>
        <a href="{{ route('admin.reseller-keys.edit', $key->id) }}" class="text-muted text-decoration-none">
            {{ $key->name }}
        </a>
        <span class="text-muted">/</span>
        <span class="fw-semibold">{{ translate('logs') }}</span>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h3 class="mb-1">{{ translate('api_request_logs') }}</h3>
            <div class="text-muted small">
                {{ $key->name }} (#{{ $key->id }})
                &nbsp;&mdash;&nbsp;
                {{ translate('total_requests') }}: <strong>{{ number_format($key->total_requests) }}</strong>
            </div>
        </div>
        <a href="{{ route('admin.reseller-keys.edit', $key->id) }}" class="btn btn-outline-secondary">
            <i class="fi fi-rr-settings me-1"></i>{{ translate('edit_settings') }}
        </a>
    </div>

    {{-- ─── Filter Bar ─── --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.reseller-keys.logs', $key->id) }}"
                  class="d-flex flex-wrap gap-3 align-items-end">

                <div>
                    <label class="form-label mb-1 small fw-semibold">{{ translate('method') }}</label>
                    <select name="method_filter" class="form-select form-select-sm">
                        <option value="">{{ translate('all_methods') }}</option>
                        @foreach(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $m)
                            <option value="{{ $m }}" {{ request('method_filter') === $m ? 'selected' : '' }}>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label mb-1 small fw-semibold">{{ translate('status') }}</label>
                    <select name="status_filter" class="form-select form-select-sm">
                        <option value="">{{ translate('all_statuses') }}</option>
                        <option value="2xx" {{ request('status_filter') === '2xx' ? 'selected' : '' }}>2xx — {{ translate('success') }}</option>
                        <option value="4xx" {{ request('status_filter') === '4xx' ? 'selected' : '' }}>4xx — {{ translate('client_error') }}</option>
                        <option value="5xx" {{ request('status_filter') === '5xx' ? 'selected' : '' }}>5xx — {{ translate('server_error') }}</option>
                        @foreach([200, 201, 400, 401, 403, 404, 422, 429, 500] as $code)
                            <option value="{{ $code }}" {{ request('status_filter') == $code ? 'selected' : '' }}>{{ $code }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label mb-1 small fw-semibold">{{ translate('date_from') }}</label>
                    <input type="date" name="date_from" class="form-control form-control-sm"
                           value="{{ request('date_from') }}">
                </div>

                <div>
                    <label class="form-label mb-1 small fw-semibold">{{ translate('date_to') }}</label>
                    <input type="date" name="date_to" class="form-control form-control-sm"
                           value="{{ request('date_to') }}">
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fi fi-rr-search me-1"></i>{{ translate('filter') }}
                    </button>
                    <a href="{{ route('admin.reseller-keys.logs', $key->id) }}" class="btn btn-outline-secondary btn-sm">
                        {{ translate('reset') }}
                    </a>
                </div>

            </form>
        </div>
    </div>

    {{-- ─── Results summary ─── --}}
    @if(request()->hasAny(['method_filter','status_filter','date_from','date_to']))
        <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-3">
            <i class="fi fi-sr-info"></i>
            <span>
                {{ translate('showing') }} <strong>{{ $logs->total() }}</strong> {{ translate('filtered_results') }}
            </span>
        </div>
    @endif

    {{-- ─── Logs Table ─── --}}
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-borderless align-middle">
                    <thead class="text-capitalize">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('method') }}</th>
                            <th>{{ translate('endpoint') }}</th>
                            <th class="text-center">{{ translate('status') }}</th>
                            <th>{{ translate('ip_address') }}</th>
                            <th class="text-end">{{ translate('response_ms') }}</th>
                            <th>{{ translate('error') }}</th>
                            <th>{{ translate('date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($logs as $i => $log)
                        @php
                            $status = $log->http_status ?? 0;
                            $statusClass = 'secondary';
                            if ($status >= 500) $statusClass = 'danger';
                            elseif ($status >= 400) $statusClass = 'warning';
                            elseif ($status >= 200 && $status < 300) $statusClass = 'success';

                            $methodColors = [
                                'GET'    => 'primary',
                                'POST'   => 'success',
                                'PUT'    => 'info',
                                'PATCH'  => 'info',
                                'DELETE' => 'danger',
                            ];
                            $methodColor = $methodColors[$log->method ?? ''] ?? 'secondary';
                        @endphp
                        <tr>
                            <td class="text-muted small">{{ $logs->firstItem() + $i }}</td>
                            <td>
                                <span class="badge bg-{{ $methodColor }}">{{ $log->method ?? '—' }}</span>
                            </td>
                            <td>
                                <code class="small text-break" style="max-width: 280px; display: inline-block;">
                                    {{ $log->endpoint ?? '—' }}
                                </code>
                                @if(!empty($log->request_summary))
                                    <a href="#log-{{ $log->id }}-detail" data-bs-toggle="collapse"
                                       class="ms-1 text-muted small text-decoration-none">
                                        <i class="fi fi-rr-info"></i>
                                    </a>
                                    <div id="log-{{ $log->id }}-detail" class="collapse mt-1">
                                        <pre class="small bg-light p-2 rounded mb-0" style="max-width:400px; overflow:auto; max-height:150px;">{{ json_encode($log->request_summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $statusClass }} {{ $statusClass === 'warning' ? 'text-dark' : '' }}">
                                    {{ $status ?: '—' }}
                                </span>
                            </td>
                            <td class="text-muted small font-monospace">{{ $log->ip_address ?? '—' }}</td>
                            <td class="text-end small">
                                @if($log->response_time_ms !== null)
                                    @php
                                        $ms = $log->response_time_ms;
                                        $msClass = $ms > 2000 ? 'danger' : ($ms > 800 ? 'warning' : 'success');
                                    @endphp
                                    <span class="text-{{ $msClass }}">{{ number_format($ms) }}ms</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($log->error_message)
                                    <span class="text-danger small" title="{{ $log->error_message }}">
                                        {{ Str::limit($log->error_message, 60) }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-muted small" title="{{ $log->created_at }}">
                                {{ $log->created_at ? $log->created_at->format('d M Y H:i:s') : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <i class="fi fi-sr-inbox-in fs-1 text-muted"></i>
                                    <span class="text-muted">{{ translate('no_logs_found') }}</span>
                                    @if(request()->hasAny(['method_filter','status_filter','date_from','date_to']))
                                        <a href="{{ route('admin.reseller-keys.logs', $key->id) }}" class="btn btn-sm btn-outline-secondary">
                                            {{ translate('clear_filters') }}
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($logs->hasPages())
                <div class="d-flex justify-content-center mt-3">
                    {{ $logs->appends(request()->query())->links() }}
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
