<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            // Only this product's images may be reordered.
            'ids.*' => ['integer', 'distinct', Rule::exists('product_images', 'id')->where('product_id', $this->route('product')->id)],
        ];
    }
}
