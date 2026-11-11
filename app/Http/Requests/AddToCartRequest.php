<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rx = ['nullable', 'string', 'max:12', 'regex:/^[+\-]?\d{0,3}(\.\d{1,2})?$/'];

        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'qty' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'finish' => ['nullable', 'string', 'max:80'],
            'size' => ['nullable', 'string', 'exists:frame_sizes,code'],
            'lens' => ['nullable', 'array'],
            'lens.power_type' => ['required_with:lens', 'string', 'max:30'],
            'lens.lens' => ['nullable', 'string', 'max:30'],
            'lens.rx_mode' => ['nullable', 'in:later,manual,upload'],
            'lens.rx' => ['nullable', 'array'],
            'lens.rx.r_sph' => $rx,
            'lens.rx.l_sph' => $rx,
            'lens.rx.r_cyl' => $rx,
            'lens.rx.l_cyl' => $rx,
            'lens.rx.r_axis' => ['nullable', 'integer', 'between:1,180'],
            'lens.rx.l_axis' => ['nullable', 'integer', 'between:1,180'],
            // Progressive lenses cannot be made without the reading addition.
            'lens.rx.add_power' => [Rule::requiredIf(fn () => $this->input('lens.power_type') === 'progressive' && $this->input('lens.rx_mode') === 'manual'), 'nullable', 'string', 'max:12', 'regex:/^\+?[0-3](\.\d{1,2})?$/'],
            'lens.rx.pd' => ['nullable', 'numeric', 'between:40,80'],
            'lens.rx.name' => ['required_if:lens.rx_mode,manual', 'nullable', 'string', 'min:2', 'max:80'],
            'lens.rx.phone' => ['required_if:lens.rx_mode,manual', 'nullable', 'string', 'regex:/^[+\d\s-]{7,18}$/'],
            'prescription_file' => ['required_if:lens.rx_mode,upload', 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'lens.rx.add_power.required' => 'Select the reading addition (ADD) from your prescription.',
            'lens.rx.add_power.regex' => 'Enter a valid reading addition (ADD).',
            'lens.rx.pd.between' => 'Pupil distance should be between 40 and 80 mm.',
            'lens.rx.name.required_if' => 'Enter the patient name.',
            'lens.rx.phone.required_if' => 'Enter a contact phone number.',
            'lens.rx.phone.regex' => 'Enter a valid phone number.',
            'prescription_file.required_if' => 'Attach your prescription file.',
        ];
    }
}
