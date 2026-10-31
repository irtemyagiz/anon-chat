<?php

namespace App\Http\Controllers\Creator;

use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Services\Files\ImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StoreSettingsController extends Controller
{
    /** Desteklenen sosyal bağlantılar ve izin verilen alan adları. */
    public const SOCIAL = [
        'website' => null,
        'instagram' => 'instagram.com',
        'youtube' => 'youtube.com',
        'x' => 'x.com',
        'tiktok' => 'tiktok.com',
    ];

    public function edit(Request $request): View
    {
        return view('creator.store', ['profile' => $request->user()->creatorProfile]);
    }

    public function update(Request $request, ImageProcessor $images): RedirectResponse
    {
        $profile = $request->user()->creatorProfile;

        $data = $request->validate([
            'store_name' => ['required', 'string', 'min:2', 'max:80'],
            'designer_name' => ['required', 'string', 'min:2', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'social' => ['nullable', 'array'],
            ...collect(self::SOCIAL)->mapWithKeys(fn (?string $domain, string $key) => [
                "social.{$key}" => array_filter(['nullable', 'url:https', 'max:255', $domain ? 'regex:/^https:\/\/(www\.)?'.preg_quote($domain, '/').'\//' : null]),
            ])->all(),
            'logo' => ['nullable', 'file', 'max:10240'],
            'cover' => ['nullable', 'file', 'max:10240'],
        ]);

        $profile->fill([
            'store_name' => $data['store_name'],
            'designer_name' => $data['designer_name'],
            'description' => $data['description'] ?? null,
            'social_links' => array_filter($data['social'] ?? []) ?: null,
        ]);

        $disk = config('marketplace.storage.public_media_disk');

        try {
            foreach (['logo' => ['logo_path', 600], 'cover' => ['cover_path', 2400]] as $field => [$column, $edge]) {
                if ($request->hasFile($field)) {
                    $stored = $images->store($request->file($field), $disk, "stores/{$profile->store_slug}", $edge);

                    if ($profile->{$column}) {
                        Storage::disk($disk)->delete($profile->{$column});
                    }

                    $profile->{$column} = $stored['path'];
                }
            }
        } catch (MarketplaceException $exception) {
            return back()->withInput()->withErrors(['logo' => $exception->getMessage()]);
        }

        $profile->save();

        return back()->with('status', __('Mağaza ayarları kaydedildi.'));
    }
}
