<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'password' => [
                'bail', $user ? 'nullable' : 'required', 'string', 'min:12', 'confirmed',
                function (string $attribute, string $value, Closure $fail): void {
                    if (strlen($value) > 72) {
                        $fail('La password deve occupare al massimo 72 byte.');
                    }
                    if (str_contains($value, "\0")) {
                        $fail('La password contiene un carattere non valido.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Inserisci il nome dell’utente.',
            'email.required' => 'Inserisci l’indirizzo email.',
            'email.email' => 'Inserisci un indirizzo email valido.',
            'email.unique' => 'Questo indirizzo email è già associato a un account.',
            'password.required' => 'Imposta una password per il nuovo account.',
            'password.min' => 'La password deve contenere almeno 12 caratteri.',
            'password.confirmed' => 'Le due password devono coincidere.',
        ];
    }
}
