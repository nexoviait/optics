<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'finishes' => array_values(array_filter((array) $this->input('finishes', []))),
            'try_on_flip' => $this->boolean('try_on_flip'),
            'try_on_scale' => $this->input('try_on_scale') ?? 1,
            'try_on_offset_y' => $this->input('try_on_offset_y') ?? 0,
        ]);
    }

    public function messages(): array
    {
        return ['compare_at_price.gt' => 'The original (MRP) price must be higher than the selling price.'];
    }

    public function rules(): array
    {
        $id = $this->route('product')?->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('products', 'slug')->ignore($id)],
            'sku' => ['required', 'string', 'max:40', Rule::unique('products', 'sku')->ignore($id)],
            'category' => ['required', Rule::in(array_keys(config('store.categories')))],
            'status' => ['required', Rule::in(['active', 'draft'])],
            'series' => ['nullable', 'string', 'max:120'],
            'badge' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'brand' => ['nullable', 'string', 'max:80'],
            'model_no' => ['nullable', 'string', 'max:40'],
            'variant_group' => ['nullable', 'alpha_dash', 'max:60'],
            'gender' => ['sometimes', 'required', Rule::in(array_keys(config('store.genders')))],
            'age_group' => ['nullable', 'string', 'max:30'],
            'frame_type' => ['sometimes', 'required', Rule::in(array_keys(config('store.frame_types')))],
            'color_name' => ['nullable', 'string', 'max:40'],
            'temple_material' => ['nullable', 'string', 'max:80'],
            'offer_text' => ['nullable', 'string', 'max:120'],
            'compare_at_price' => ['nullable', 'numeric', 'gt:price', 'max:99999'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'cost' => ['required', 'numeric', 'min:0', 'max:99999'],
            'stock' => ['required', 'integer', 'min:0'],
            'low_stock_threshold' => ['required', 'integer', 'min:0'],
            'shape' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:80'],
            'material' => ['nullable', 'string', 'max:80'],
            'lens_tag' => ['nullable', 'string', 'max:60'],
            'weight' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer'],
            'finishes' => ['array', 'max:6'],
            'finishes.*' => ['string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'image' => ['nullable', 'image', 'max:4096'],
            // Transparent PNG/WebP of the frame front for the AR try-on.
            'try_on_image' => ['nullable', 'file', 'mimes:png,webp', 'max:2048'],
            'remove_try_on_image' => ['nullable', 'boolean'],
            // Live camera try-on fine-tuning.
            'try_on_flip' => ['nullable', 'boolean'],
            'try_on_scale' => ['nullable', 'numeric', 'between:0.7,1.3'],
            'try_on_offset_y' => ['nullable', 'numeric', 'between:-15,15'],
        ];
    }
}
