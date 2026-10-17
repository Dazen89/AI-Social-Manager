<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAiIntegrationRequest extends FormRequest
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
        $providers = config('ai.providers', []);
        $provider = (string) $this->input('ai_provider');
        $models = array_keys(data_get($providers, $provider.'.models', []));

        return [
            'ai_provider' => ['required', 'string', Rule::in(array_keys($providers))],
            'model' => ['required', 'string', Rule::in($models)],
            'api_key' => ['nullable', 'string', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ai_provider.required' => 'Seleziona il fornitore AI.',
            'ai_provider.in' => 'Il fornitore AI selezionato non è supportato.',
            'model.required' => 'Seleziona il modello da utilizzare.',
            'model.in' => 'Il modello selezionato non appartiene al fornitore indicato.',
            'api_key.max' => 'La API key è troppo lunga.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ai_provider' => trim((string) $this->input('ai_provider')),
            'model' => trim((string) $this->input('model')),
            'api_key' => $this->filled('api_key')
                ? trim((string) $this->input('api_key'))
                : null,
        ]);
    }
}
