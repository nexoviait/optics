<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSpinSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 24–36 transparent WebP/PNG frames recommended; extracted by the ExtractSpinSet job.
            'archive' => ['required', 'file', 'mimes:zip', 'max:51200'],
        ];
    }
}
