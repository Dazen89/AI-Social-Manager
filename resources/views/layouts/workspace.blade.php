@extends('layouts.app')

@section('content')
    <div class="ui-workspace" data-workspace>
        <x-sidebar />
        <div class="ui-overlay" data-menu-overlay></div>
        <div class="ui-workspace-body" data-workspace-body>
            <a href="#main-content" class="ui-skip-link">Vai al contenuto</a>
            <header class="ui-mobile-header">
                <button type="button" class="ui-icon-button" aria-label="Apri navigazione" aria-controls="workspace-sidebar" aria-expanded="false" data-menu-open>
                    <x-icon name="menu" />
                </button>
                <x-brand />
            </header>
            @yield('page')
        </div>
    </div>

    <x-confirm-dialog />
@endsection
