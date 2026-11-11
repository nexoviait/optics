<?php

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreProductModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Binary glTF only: one self-contained file with its textures, which browsers load in one request.
            'model' => ['required', 'file', 'extensions:glb', 'max:30720', function (string $attribute, mixed $value, Closure $fail) {
                // Every .glb starts with the ASCII magic "glTF"; reject renamed files early.
                if ($value instanceof UploadedFile && file_get_contents($value->getRealPath(), false, null, 0, 4) !== 'glTF') {
                    $fail('The 3D model must be a binary glTF (.glb) file.');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'model.extensions' => 'The 3D model must be a binary glTF (.glb) file.',
            'model.max' => 'The 3D model may not be larger than 30 MB.',
        ];
    }
}
