@if ($attachments)
    @if ($suggested)
        <p class="mt-3 text-xs text-muted">Immagini suggerite · da confermare</p>
    @endif
    <div class="{{ $suggested ? 'mt-1' : 'mt-3' }} flex gap-2">
        @foreach ($attachments as $attachment)
            @if ($source->containsPublicUrl($attachment['thumbnail']))
                <img src="{{ $attachment['thumbnail'] }}" alt="{{ $suggested ? 'Pagina suggerita '.$attachment['number'] : 'Pagina selezionata' }}" loading="lazy" referrerpolicy="no-referrer" class="h-20 w-14 rounded-lg border border-line bg-surface object-contain">
            @endif
        @endforeach
    </div>
@endif
