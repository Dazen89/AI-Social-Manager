<?php

namespace App\Http\Requests;

use App\Models\EditorialSource;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveEditorialSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');
        $source = $this->route('source');

        return (bool) $this->user()?->is_admin
            && ($source === null || ($source instanceof EditorialSource && $site instanceof Site && $source->site_id === $site->id));
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(['foolslide'])],
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url:http,https', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('base_url')) {
                return;
            }

            $site = $this->route('site');
            $parts = parse_url((string) $this->input('base_url'));

            if (! $site instanceof Site || $parts === false || ! isset($parts['host'], $parts['scheme'])) {
                return;
            }

            if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                $validator->errors()->add('base_url', 'Inserisci l’indirizzo base di FoOlSlide, senza credenziali, query o frammenti.');
            }

            if (Str::lower(rtrim($parts['host'], '.')) !== $site->host) {
                $validator->errors()->add('base_url', 'La fonte deve appartenere allo stesso dominio del sito selezionato.');
            }

            $source = $this->route('source');

            if ($site->editorialSources()
                ->where('provider', 'foolslide')
                ->where('base_url', $this->input('base_url'))
                ->when($source, fn ($query) => $query->where('id', '!=', $source->id))
                ->exists()) {
                $validator->errors()->add('base_url', 'Questa fonte FoOlSlide è già stata aggiunta al sito.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $url = trim((string) $this->input('base_url'));

        if ($url !== '' && ! preg_match('~^https?://~i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);

        if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
            $origin = Str::lower($parts['scheme']).'://'.Str::lower(rtrim($parts['host'], '.'));
            $port = isset($parts['port']) ? ':'.$parts['port'] : '';
            $path = rtrim($parts['path'] ?? '', '/');
            $url = $origin.$port.$path;

            if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                $url = trim((string) $this->input('base_url'));
            }
        }

        $this->merge([
            'provider' => $this->input('provider'),
            'name' => trim((string) $this->input('name')),
            'base_url' => $url,
        ]);
    }
}
