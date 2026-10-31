<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserStatus;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Kimlik doğrular. Aynı e-posta + IP için dakikada 5 hatalı denemeden sonra kilitlenir.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            event(new Lockout($this));

            throw ValidationException::withMessages([
                'email' => __('Çok fazla deneme yapıldı. Lütfen :seconds saniye sonra tekrar deneyin.', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $credentials = ['email' => Str::lower($this->string('email')), 'password' => $this->string('password')->toString()];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => __('E-posta adresi veya şifre hatalı.')]);
        }

        if (Auth::user()->status !== UserStatus::Active) {
            Auth::logout();

            throw ValidationException::withMessages(['email' => __('Hesabınız askıya alınmış. Destek ekibiyle iletişime geçin.')]);
        }

        RateLimiter::clear($key);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
