<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $site = $this->route('site');

        return [
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:2048'],
            'host' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sites', 'host')->ignore($site instanceof Site ? $site->id : null),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $parts = parse_url((string) $this->input('_submitted_base_url'));

            if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
                return;
            }

            if (! in_array(Str::lower($parts['scheme']), ['http', 'https'], true)) {
                $validator->errors()->add('base_url', 'Il sito deve utilizzare http o https.');
            }

            if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                $validator->errors()->add('base_url', 'Inserisci solo l’indirizzo base del sito, senza credenziali, query o frammenti.');
            }

            if (isset($parts['path']) && ! in_array($parts['path'], ['', '/'], true)) {
                $validator->errors()->add('base_url', 'Inserisci l’indirizzo base del sito senza il percorso di una pagina.');
            }

        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome del sito',
            'base_url' => 'URL base',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Inserisci un nome con cui riconoscere il sito nella dashboard.',
            'name.max' => 'Il nome del sito non può superare 255 caratteri.',
            'base_url.required' => 'Inserisci l’indirizzo principale del sito.',
            'base_url.url' => 'Inserisci un indirizzo web valido, ad esempio https://www.example.com.',
            'base_url.max' => 'L’URL base è troppo lungo.',
            'host.required' => 'Non è stato possibile ricavare il dominio dall’URL base.',
            'host.unique' => 'Questo dominio è già presente.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $baseUrl = trim((string) $this->input('base_url'));

        if ($baseUrl !== '' && ! preg_match('~^https?://~i', $baseUrl)) {
            $baseUrl = 'https://'.$baseUrl;
        }

        $submittedBaseUrl = $baseUrl;
        $parts = parse_url($baseUrl);
        $host = is_array($parts) && isset($parts['host'])
            ? Str::lower(rtrim($parts['host'], '.'))
            : null;

        if ($host !== null && isset($parts['scheme'])) {
            $baseUrl = Str::lower($parts['scheme']).'://'.$host;

            if (isset($parts['port'])) {
                $baseUrl .= ':'.$parts['port'];
            }
        }

        $this->merge([
            '_submitted_base_url' => $submittedBaseUrl,
            'name' => trim((string) $this->input('name')),
            'base_url' => $baseUrl,
            'host' => $host,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
