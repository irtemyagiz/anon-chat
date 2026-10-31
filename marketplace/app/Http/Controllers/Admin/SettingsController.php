<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Models\SeoSetting;
use App\Services\Files\ImageProcessor;
use App\Services\Settings\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Sistem ayarları (site adı, iletişim, logo, bakım modu, dosya limiti, SMTP) ve SEO ayarları.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function edit(): View
    {
        return view('admin.settings', [
            'values' => collect([
                'site.name', 'site.contact_email', 'site.support_email', 'site.logo_path', 'site.maintenance', 'site.maintenance_message',
                'storage.max_file_size_mb', 'mail.host', 'mail.port', 'mail.username', 'mail.encryption', 'mail.from_address', 'mail.from_name',
            ])->mapWithKeys(fn (string $key) => [$key => $this->settings->get($key)]),
            'hasMailPassword' => filled($this->settings->get('mail.password')),
            'serverUploadLimit' => ini_get('upload_max_filesize'),
        ]);
    }

    public function update(Request $request, ImageProcessor $images): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:60'],
            'contact_email' => ['nullable', 'email'],
            'support_email' => ['nullable', 'email'],
            'maintenance_message' => ['nullable', 'string', 'max:300'],
            'max_file_size_mb' => ['required', 'integer', 'min:1', 'max:2048'],
            'logo' => ['nullable', 'file', 'max:5120'],
        ]);

        $actor = $request->user();
        $this->settings->set('site.name', $data['site_name'], $actor, 'site');
        $this->settings->set('site.contact_email', $data['contact_email'] ?? null, $actor, 'site');
        $this->settings->set('site.support_email', $data['support_email'] ?? null, $actor, 'site');
        $this->settings->set('site.maintenance', $request->boolean('maintenance'), $actor, 'site');
        $this->settings->set('site.maintenance_message', $data['maintenance_message'] ?? null, $actor, 'site');
        $this->settings->set('storage.max_file_size_mb', (int) $data['max_file_size_mb'], $actor, 'storage');

        if ($request->hasFile('logo')) {
            try {
                $stored = $images->store($request->file('logo'), config('marketplace.storage.public_media_disk'), 'site', 600);
            } catch (MarketplaceException $exception) {
                return back()->withErrors(['logo' => $exception->getMessage()]);
            }

            $this->settings->set('site.logo_path', $stored['path'], $actor, 'site');
        } elseif ($request->boolean('remove_logo')) {
            $this->settings->set('site.logo_path', null, $actor, 'site');
        }

        return back()->with('status', __('Sistem ayarları kaydedildi.'));
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:190'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
            'from_address' => ['required', 'email'],
            'from_name' => ['required', 'string', 'max:80'],
        ]);

        $actor = $request->user();

        foreach (['host', 'port', 'username', 'encryption', 'from_address', 'from_name'] as $key) {
            $this->settings->set("mail.{$key}", $data[$key] ?? null, $actor, 'mail');
        }

        if (filled($data['password'] ?? null)) {
            $this->settings->set('mail.password', $data['password'], $actor, 'mail', encrypted: true);
        }

        return back()->with('status', __('SMTP ayarları kaydedildi. Test e-postası göndererek doğrulayın.'));
    }

    public function testMail(Request $request): RedirectResponse
    {
        try {
            Mail::raw(__('Bu bir test e-postasıdır. SMTP ayarlarınız çalışıyor.'), fn ($message) => $message->to($request->user()->email)->subject(config('app.name').' · '.__('SMTP testi')));
        } catch (Throwable $exception) {
            return back()->withErrors(['mail' => __('E-posta gönderilemedi: :error', ['error' => $exception->getMessage()])]);
        }

        return back()->with('status', __('Test e-postası :email adresine gönderildi.', ['email' => $request->user()->email]));
    }

    public function seo(): View
    {
        return view('admin.seo', [
            'entries' => SeoSetting::query()->get()->keyBy(fn (SeoSetting $s) => $s->route_name.'|'.$s->locale),
            'defaultImage' => $this->settings->get('site.default_og_image'),
        ]);
    }

    public function updateSeo(Request $request, ImageProcessor $images): RedirectResponse
    {
        $request->validate([
            'seo' => ['array'],
            'seo.*.*.meta_title' => ['nullable', 'string', 'max:160'],
            'seo.*.*.meta_description' => ['nullable', 'string', 'max:300'],
            'og_image' => ['nullable', 'file', 'max:5120'],
        ]);

        foreach ((array) $request->input('seo', []) as $route => $locales) {
            if (! array_key_exists($route, SeoSetting::ROUTES)) {
                continue;
            }

            foreach ((array) $locales as $locale => $values) {
                if (! in_array($locale, ['tr', 'en'], true)) {
                    continue;
                }

                SeoSetting::query()->updateOrCreate(['route_name' => $route, 'locale' => $locale], [
                    'meta_title' => $values['meta_title'] ?? null,
                    'meta_description' => $values['meta_description'] ?? null,
                    'noindex' => (bool) ($values['noindex'] ?? false),
                ]);
            }
        }

        if ($request->hasFile('og_image')) {
            try {
                $stored = $images->store($request->file('og_image'), config('marketplace.storage.public_media_disk'), 'site', 1200);
            } catch (MarketplaceException $exception) {
                return back()->withErrors(['og_image' => $exception->getMessage()]);
            }

            $this->settings->set('site.default_og_image', $stored['path'], $request->user(), 'seo');
        }

        return back()->with('status', __('SEO ayarları kaydedildi.'));
    }
}
