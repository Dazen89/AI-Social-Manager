@extends('layouts.app')

@php
    $status = isset($exception) ? $exception->getStatusCode() : (int) trim($__env->yieldContent('code'));
    [$heading, $description] = match ($status) {
        401 => ['Accesso richiesto', 'Accedi per continuare.'],
        402 => ['Pagamento richiesto', 'L’accesso a questa risorsa richiede un pagamento.'],
        403 => ['Accesso non consentito', 'Il tuo account non ha i permessi per questa pagina.'],
        404 => ['Pagina non trovata', 'Verifica l’indirizzo o torna alla pagina iniziale.'],
        419 => ['Sessione scaduta', 'Torna alla pagina iniziale e ripeti l’operazione.'],
        429 => ['Troppe richieste', 'Attendi qualche istante prima di riprovare.'],
        503 => ['Servizio temporaneamente non disponibile', 'Riprova tra qualche minuto.'],
        default => $status >= 500
            ? ['Si è verificato un errore', 'La richiesta non è stata completata. Riprova tra qualche minuto.']
            : ['Richiesta non completata', 'Torna alla pagina iniziale e riprova.'],
    };
@endphp

@section('content')
    <main class="ui-auth">
        <div class="ui-auth-stack">
            <x-brand />
            <section class="ui-panel ui-auth-panel" aria-labelledby="error-title">
                <p class="ui-kicker">Errore {{ $status }}</p>
                <h1 id="error-title" class="ui-page-title">{{ $heading }}</h1>
                <p class="ui-page-description">{{ $description }}</p>
                <div class="mt-5"><a href="{{ url('/') }}" class="ui-button ui-button-primary">Torna alla pagina iniziale</a></div>
            </section>
        </div>
    </main>
@endsection
