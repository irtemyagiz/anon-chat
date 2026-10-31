<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Yalnızca oturum sahibinin kendisine döner (e-posta içerir).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'username' => $this->username,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'email_verified' => $this->hasVerifiedEmail(),
            'country_code' => $this->country_code,
            'locale' => $this->locale,
            'is_creator' => $this->isApprovedCreator(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
