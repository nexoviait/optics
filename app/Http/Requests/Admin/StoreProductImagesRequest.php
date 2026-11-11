<?php

namespace App\Http\Requests\Admin;

use App\Enums\ImageViewType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // admin middleware guards the route
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'max:12'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=400,min_height=400'],
            'is_lifestyle' => ['boolean'],
            'view_type' => ['nullable', Rule::enum(ImageViewType::class)],
            'alt' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function messages(): array
    {
        return ['images.*.dimensions' => 'Photos must be at least 400 × 400 px (1080 × 1080 recommended).'];
    }
}
