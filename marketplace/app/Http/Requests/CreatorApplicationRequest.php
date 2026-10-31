<?php

namespace App\Http\Requests;

use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creator başvurusu. Ziyaretçi için hesap alanlarını da içerir; giriş yapmış
 * kullanıcı yalnızca mağaza alanlarını doldurur (hesabını creator'a yükseltir).
 */
class CreatorApplicationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(array_map(
            fn (mixed $value): mixed => is_string($value) ? mb_strtolower(trim($value)) : $value,
            $this->only('email', 'username'),
        ));
    }

    public function authorize(): bool
    {
        return $this->user()?->creatorProfile === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $storeRules = [
            'designer_name' => ['required', 'string', 'min:2', 'max:80'],
            'store_name' => ['required', 'string', 'min:2', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'terms' => ['accepted'],
        ];

        if ($this->user() !== null) {
            return $storeRules;
        }

        $accountRules = (new RegisterRequest)->rules();
        $accountRules['country_code'] = ['required', Rule::in(array_keys(config('countries')))];

        return [...$accountRules, ...$storeRules];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...(new RegisterRequest)->attributes(),
            'designer_name' => __('tasarımcı adı'),
            'store_name' => __('mağaza adı'),
            'description' => __('profil açıklaması'),
        ];
    }
}
