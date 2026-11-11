<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LensOption;
use App\Models\LensSku;
use App\Models\PowerType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LensController extends Controller
{
    public function index(): View
    {
        return view('admin.lenses.index', [
            'skus' => LensSku::query()->orderBy('refractive_index')->get(),
            'powerTypes' => PowerType::query()->orderBy('sort_order')->get(),
            'lensOptions' => LensOption::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function storeSku(Request $request): RedirectResponse
    {
        $data = $this->validateSku($request);
        $data['sku'] = ($data['sku'] ?? null) ?: sprintf('LN-%s-%s', preg_replace('/\W/', '', strtoupper($data['substrate'])), str_replace('.', '', $data['refractive_index']));
        $request->merge(['sku' => $data['sku']])->validate(['sku' => ['unique:lens_skus,sku']], ['sku.unique' => 'A lens with that SKU already exists.']);

        LensSku::query()->create($data);

        return back()->with('success', 'Lens SKU created.');
    }

    public function updateSku(Request $request, LensSku $sku): RedirectResponse
    {
        $data = $this->validateSku($request, $sku);
        $data['sku'] = ($data['sku'] ?? null) ?: $sku->sku;
        $sku->update($data);

        return back()->with('success', "{$sku->name} updated.");
    }

    public function destroySku(LensSku $sku): RedirectResponse
    {
        $sku->delete();

        return back()->with('success', 'Lens SKU deleted.');
    }

    public function updatePricing(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'power_types' => ['array'],
            'power_types.*.label' => ['required', 'string', 'max:80'],
            'power_types.*.description' => ['nullable', 'string', 'max:160'],
            'power_types.*.price' => ['required', 'numeric', 'min:0'],
            'power_types.*.is_active' => ['boolean'],
            'lens_options' => ['array'],
            'lens_options.*.label' => ['required', 'string', 'max:80'],
            'lens_options.*.description' => ['nullable', 'string', 'max:160'],
            'lens_options.*.price' => ['required', 'numeric', 'min:0'],
            'lens_options.*.is_active' => ['boolean'],
            'lens_options.*.badge' => ['nullable', 'string', 'max:40'],
            'lens_options.*.features' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($data['power_types'] ?? [] as $id => $values) {
            PowerType::query()->whereKey($id)->update($values + ['is_active' => false]);
        }
        foreach ($data['lens_options'] ?? [] as $id => $values) {
            // Features are edited one per line and stored as a list.
            $values['features'] = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($values['features'] ?? '')))));
            LensOption::query()->findOrFail($id)->update($values + ['is_active' => false]);
        }

        return back()->with('success', 'Lens pricing saved.');
    }

    private function validateSku(Request $request, ?LensSku $sku = null): array
    {
        return $request->validate([
            'sku' => ['nullable', 'string', 'max:40', Rule::unique('lens_skus', 'sku')->ignore($sku?->id)],
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'substrate' => ['required', Rule::in(config('store.lens_substrates'))],
            'refractive_index' => ['required', Rule::in(['1.50', '1.52', '1.59', '1.60', '1.67', '1.74'])],
            'abbe' => ['required', 'integer', 'between:20,65'],
            'geometry' => ['required', 'string', 'max:60'],
            'coatings' => ['array'],
            'coatings.*' => [Rule::in(config('store.lens_coatings'))],
            'labs' => ['array'],
            'labs.*' => [Rule::in(config('store.lens_labs'))],
            'stock' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
        ]) + ['coatings' => [], 'labs' => []];
    }
}
