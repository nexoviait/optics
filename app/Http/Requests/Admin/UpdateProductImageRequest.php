<?php

namespace App\Http\Requests\Admin;

use App\Enums\ImageViewType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Partial update: the Blade form sends alt/sort/lifestyle, the React manager sends any subset. */
class UpdateProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alt' => ['sometimes', 'nullable', 'string', 'max:160'],
            'sort_order' => ['sometimes', 'integer'],
            'is_lifestyle' => ['sometimes', 'boolean'],
            'view_type' => ['sometimes', Rule::enum(ImageViewType::class)],
            'is_primary' => ['sometimes', 'accepted'],
        ];
    }
}
