<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * E-posta ve kullanıcı adı küçük harfe çevrilerek doğrulanır; böylece
     * "Ali@Site.com" ile "ali@site.com" iki ayrı hesap olarak açılamaz.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(array_map(
            fn (mixed $value): mixed => is_string($value) ? mb_strtolower(trim($value)) : $value,
            $this->only('email', 'username'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_.]+$/', 'unique:users,username'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'country_code' => ['nullable', Rule::in(array_keys(config('countries')))],
            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'username' => __('kullanıcı adı'),
            'first_name' => __('ad'),
            'last_name' => __('soyad'),
            'email' => __('e-posta'),
            'password' => __('şifre'),
            'country_code' => __('ülke'),
            'terms' => __('kullanım şartları'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => __('Kullanıcı adı yalnızca harf, rakam, nokta ve alt çizgi içerebilir.'),
        ];
    }
}
