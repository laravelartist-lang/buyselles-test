@extends('layouts.admin.app')

@section('title', translate('reseller_api_documentation'))

@push('css_or_js')
    @include('partner-api.partials._docs-styles')
@endpush

@section('content')
<div class="content container-fluid">
    <div class="card">
        <div class="card-body">
            @include('partner-api.partials._docs-header', ['showAdminBack' => true])
            @include('partner-api.partials._docs-content')
        </div>
    </div>
</div>
@endsection
