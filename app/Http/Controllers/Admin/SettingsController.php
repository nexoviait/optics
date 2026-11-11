<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\FrameSize;
use App\Models\ShippingZone;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /** Editable settings grouped by tab: key => validation rules. */
    private const FIELDS = [
        'general' => [
            'store_name' => ['required', 'string', 'max:120'],
            'store_tagline' => ['nullable', 'string', 'max:120'],
            'store_identifier' => ['required', 'alpha_dash', 'max:60'],
            'primary_domain' => ['nullable', 'string', 'max:120'],
            'concierge_email' => ['required', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'registered_address' => ['nullable', 'string', 'max:255'],
            'footer_blurb' => ['nullable', 'string', 'max:255'],
            'ateliers' => ['nullable', 'string', 'max:120'],
            'meta_title' => ['nullable', 'string', 'max:120'],
            'meta_description' => ['nullable', 'string', 'max:255'],
            'social_instagram' => ['nullable', 'url:http,https', 'max:255'],
            'social_facebook' => ['nullable', 'url:http,https', 'max:255'],
            'social_youtube' => ['nullable', 'url:http,https', 'max:255'],
            'social_x' => ['nullable', 'url:http,https', 'max:255'],
            'app_store_url' => ['nullable', 'url:http,https', 'max:255'],
            'play_store_url' => ['nullable', 'url:http,https', 'max:255'],
        ],
        'region' => [
            'currency' => ['required', 'in:USD,CHF,EUR'],
            'timezone' => ['required', 'timezone'],
            'date_format' => ['required', 'in:d M Y,d.m.Y,m/d/Y,Y-m-d'],
        ],
        'pricing' => [
            'prices_tax_inclusive' => ['boolean'],
            'rx_vat_exemption' => ['boolean'],
            'member_bogo_enabled' => ['boolean'],
            'vat_ch' => ['required', 'numeric', 'between:0,50'],
            'vat_eu' => ['required', 'numeric', 'between:0,50'],
            'vat_uk' => ['required', 'numeric', 'between:0,50'],
            'free_shipping_threshold' => ['required', 'numeric', 'min:0'],
        ],
        'checkout' => [
            'order_prefix' => ['required', 'string', 'max:8', 'regex:/^[A-Z0-9-]+$/'],
            'rx_verification_gate' => ['boolean'],
            'address_validation' => ['boolean'],
            'exchange_days' => ['required', 'integer', 'between:0,365'],
            'warranty_days' => ['required', 'integer', 'between:0,3650'],
            'rx_submission_days' => ['required', 'integer', 'between:1,90'],
        ],
        'legal' => [
            'policy_terms' => ['nullable', 'string', 'max:5000'],
            'policy_privacy' => ['nullable', 'string', 'max:5000'],
            'policy_remake' => ['nullable', 'string', 'max:5000'],
            'compliance_swissmedic' => ['boolean'],
            'compliance_eu_mdr' => ['boolean'],
        ],
    ];

    public function index(Request $request): View
    {
        return view('admin.settings.index', [
            'tab' => $request->query('tab', 'general'),
            'settings' => Settings::all(),
            'zones' => ShippingZone::query()->orderBy('sort_order')->get(),
            'coupons' => Coupon::query()->latest()->get(),
            'sizes' => FrameSize::ordered(),
            'regions' => config('store.delivery_regions'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tab = $request->input('tab');
        abort_unless(isset(self::FIELDS[$tab]), 404);

        $rules = self::FIELDS[$tab];
        $data = $request->validate($rules);

        // Unchecked checkboxes are absent from the request.
        foreach ($rules as $key => $rule) {
            if (in_array('boolean', $rule, true)) {
                $data[$key] = $request->boolean($key);
            }
        }

        Settings::put($data);

        return redirect()->route('admin.settings.index', ['tab' => $tab])->with('success', 'Settings saved.');
    }

    /** Uploadable brand assets: setting key => form field. */
    private const BRAND_ASSETS = ['logo' => 'logo_path', 'favicon' => 'favicon_path'];

    public function updateBranding(Request $request): RedirectResponse
    {
        // SVG is excluded on purpose: it can carry script when opened directly.
        $request->validate([
            'logo' => ['nullable', File::types(['png', 'jpg', 'jpeg', 'webp'])->max(2048), 'dimensions:min_width=32,min_height=32,max_width=2000,max_height=2000'],
            'favicon' => ['nullable', File::types(['png', 'ico'])->max(512)],
        ]);

        $saved = [];
        foreach (self::BRAND_ASSETS as $field => $key) {
            if ($request->hasFile($field)) {
                $this->deleteBrandFile($key);
                $saved[$key] = $request->file($field)->store('branding', 'public');
            }
        }

        if ($saved === []) {
            return back()->withErrors(['logo' => 'Choose a logo or favicon to upload.']);
        }

        Settings::put($saved);

        return redirect()->route('admin.settings.index', ['tab' => 'branding'])->with('success', 'Branding updated.');
    }

    public function destroyBranding(string $asset): RedirectResponse
    {
        $key = self::BRAND_ASSETS[$asset] ?? abort(404);

        $this->deleteBrandFile($key);
        Settings::put([$key => null]);

        return redirect()->route('admin.settings.index', ['tab' => 'branding'])->with('success', ucfirst($asset).' reset to default.');
    }

    private function deleteBrandFile(string $key): void
    {
        $path = Settings::get($key);
        if ($path && str_starts_with($path, 'branding/')) {
            Storage::disk('public')->delete($path);
        }
    }

    public function storeZone(Request $request): RedirectResponse
    {
        ShippingZone::query()->create($this->zonePayload($request));

        return redirect()->route('admin.settings.index', ['tab' => 'shipping'])->with('success', 'Shipping zone added.');
    }

    public function updateZone(Request $request, ShippingZone $zone): RedirectResponse
    {
        $zone->update($this->zonePayload($request));

        return redirect()->route('admin.settings.index', ['tab' => 'shipping'])->with('success', "{$zone->name} updated.");
    }

    public function destroyZone(ShippingZone $zone): RedirectResponse
    {
        $zone->delete();

        return redirect()->route('admin.settings.index', ['tab' => 'shipping'])->with('success', 'Shipping zone deleted.');
    }

    public function storeCoupon(Request $request): RedirectResponse
    {
        Coupon::query()->create($this->couponPayload($request));

        return redirect()->route('admin.settings.index', ['tab' => 'coupons'])->with('success', 'Coupon created.');
    }

    public function updateCoupon(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->couponPayload($request, $coupon));

        return redirect()->route('admin.settings.index', ['tab' => 'coupons'])->with('success', "{$coupon->code} updated.");
    }

    public function destroyCoupon(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('admin.settings.index', ['tab' => 'coupons'])->with('success', 'Coupon deleted.');
    }

    public function updateSizes(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sizes' => ['required', 'array'],
            'sizes.*.width' => ['required', 'integer', 'between:100,180'],
            'sizes.*.lens_width' => ['required', 'integer', 'between:30,70'],
            'sizes.*.bridge' => ['required', 'integer', 'between:10,30'],
            'sizes.*.temple' => ['required', 'integer', 'between:110,170'],
            'sizes.*.face' => ['required', 'string', 'max:60'],
        ]);

        foreach ($data['sizes'] as $id => $values) {
            FrameSize::query()->whereKey($id)->update($values);
        }

        return redirect()->route('admin.settings.index', ['tab' => 'sizes'])->with('success', 'Size guide saved.');
    }

    private function zonePayload(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'courier' => ['required', 'string', 'max:120'],
            'countries' => ['required', 'array', 'min:1'],
            'countries.*' => [Rule::in(array_keys(config('store.delivery_regions')))],
            'transit_min' => ['required', 'integer', 'between:1,30'],
            'transit_max' => ['required', 'integer', 'between:1,30', 'gte:transit_min'],
            'rate_label' => ['nullable', 'string', 'max:30'],
            'holidays' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['holidays'] = collect(preg_split('/[\s,]+/', (string) ($data['holidays'] ?? '')))
            ->filter(fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))->values()->all();
        $data['enabled'] = $request->boolean('enabled');

        return $data;
    }

    private function couponPayload(Request $request, ?Coupon $coupon = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'numeric', 'min:0.01', $request->input('type') === 'percent' ? 'max:100' : 'max:99999'],
            'expires_at' => ['nullable', 'date'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
