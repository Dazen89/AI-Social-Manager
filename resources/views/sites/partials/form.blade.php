@unless ($site->exists)
    <aside class="rounded-2xl border border-sky-500/30 bg-sky-500/10 p-5" aria-labelledby="site-setup-guide">
        <h2 id="site-setup-guide" class="font-semibold text-sky-100">Prima di iniziare</h2>
        <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm leading-6 text-slate-300">
            <li>Assicurati di possedere il sito o di essere autorizzato a consultarne i dati.</li>
            <li>Inserisci il nome e l’indirizzo principale del sito.</li>
            <li>Salva per accedere alla pagina dei servizi disponibili, incluso Google Analytics.</li>
        </ol>
        <p class="mt-3 text-sm leading-6 text-slate-400">In questa fase non servono account esterni, identificativi Analytics o credenziali.</p>
    </aside>
@endunless

<fieldset class="space-y-5">
    <legend class="text-lg font-semibold">Sito</legend>
    <p class="text-sm text-slate-400">L’indirizzo deve rappresentare l’origine del sito, senza percorsi, query string o frammenti.</p>

    <div>
        <div class="mb-2 flex items-baseline justify-between gap-3">
            <label for="name" class="block text-sm font-medium text-slate-300">Nome</label>
            <span class="text-xs text-slate-500">Obbligatorio</span>
        </div>
        <input id="name" name="name" type="text" value="{{ old('name', $site->name) }}" required maxlength="255" placeholder="Sito istituzionale" aria-describedby="name-help">
        <p id="name-help" class="mt-2 text-sm leading-6 text-slate-400">Un nome riconoscibile solo all’interno della dashboard, ad esempio “Sito aziendale” o “Negozio online”. Non viene inviato a Google.</p>
        @error('name')<p class="mt-2 text-sm text-rose-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <div class="mb-2 flex items-baseline justify-between gap-3">
            <label for="base_url" class="block text-sm font-medium text-slate-300">URL base</label>
            <span class="text-xs text-slate-500">Obbligatorio</span>
        </div>
        <input id="base_url" name="base_url" type="text" inputmode="url" autocomplete="url" autocapitalize="none" spellcheck="false" value="{{ old('base_url', $site->base_url) }}" required maxlength="2048" placeholder="www.example.com" aria-describedby="base-url-help">
        <p id="base-url-help" class="mt-2 text-sm leading-6 text-slate-400">Inserisci il dominio principale, ad esempio <span class="font-mono text-xs text-slate-300">www.example.com</span>. Se non specifichi il protocollo aggiungeremo automaticamente <span class="font-mono text-xs text-slate-300">https://</span>. Non inserire una pagina come <span class="font-mono text-xs text-slate-300">/contatti</span>.</p>
        @error('base_url')<p class="mt-2 text-sm text-rose-400">{{ $message }}</p>@enderror
        @error('host')<p class="mt-2 text-sm text-rose-400">{{ $message }}</p>@enderror
    </div>

    <div>
        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-3 text-sm text-slate-300">
            <input name="is_active" type="checkbox" value="1" class="size-4 rounded border-slate-700 bg-slate-900 text-sky-500" @checked(old('is_active', $site->exists ? $site->is_active : true))>
            Monitoraggio attivo
        </label>
        <p class="mt-2 pl-7 text-sm leading-6 text-slate-400">Se lo disattivi, la configurazione resta salvata ma il sito viene escluso dalle future sincronizzazioni.</p>
    </div>
</fieldset>

<div class="flex flex-wrap items-center gap-4 border-t border-slate-800 pt-8">
    <button type="submit" class="rounded-xl bg-sky-500 px-5 py-3 font-semibold text-slate-950 transition hover:bg-sky-400">{{ $submitLabel }}</button>
    <a href="{{ route('sites.index') }}" class="text-sm text-slate-400">Annulla</a>
</div>
