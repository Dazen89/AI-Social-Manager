<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveXSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'string', 'max:2048'],
            'client_secret' => ['nullable', 'string', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required' => 'Inserisci il Client ID dell’app X.',
            'client_id.max' => 'Il Client ID è troppo lungo.',
            'client_secret.max' => 'Il Client Secret è troppo lungo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'client_id' => trim((string) $this->input('client_id')),
            'client_secret' => $this->filled('client_secret')
                ? trim((string) $this->input('client_secret'))
                : null,
        ]);
    }
}
