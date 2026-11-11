<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Shipping;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ship_postal' => strtoupper(trim((string) $this->input('ship_postal'))),
            'email' => $this->user()?->email ?? (strtolower(trim((string) $this->input('email'))) ?: null),
        ]);
    }

    public function rules(): array
    {
        $type = $this->selectedMethod()?->type;
        $countries = array_keys(app(Shipping::class)->shippableCountries());

        // Card details are validated for completeness only; they are never stored.
        return [
            // Members always use their account email. Guests may leave it blank (no emails are sent then),
            // but need one to create an account.
            'email' => ['required_if_accepted:create_account', 'nullable', 'email:rfc', 'max:255'],
            'create_account' => ['nullable', 'boolean', Rule::prohibitedIf($this->user() !== null)],
            'password' => [$this->boolean('create_account') ? 'required' : 'nullable', 'string', Password::defaults()],
            'ship_name' => ['required', 'string', 'min:2', 'max:80'],
            'ship_phone' => ['required', 'string', 'regex:/^[+\d\s()-]{7,20}$/'],
            'ship_line1' => ['required', 'string', 'max:160'],
            'ship_line2' => ['nullable', 'string', 'max:160'],
            'ship_city' => ['required', 'string', 'max:80'],
            'ship_country' => ['required', Rule::in($countries)],
            'ship_postal' => ['required', 'string', 'max:12', function (string $attribute, mixed $value, Closure $fail) {
                $region = config('store.delivery_regions.'.$this->input('ship_country'));
                if ($region && ! preg_match($region['pattern'], (string) $value)) {
                    $fail("Enter a valid postal code for {$region['name']}.");
                }
            }],
            'customer_note' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::exists('payment_methods', 'code')->where('is_active', true)],
            'card_name' => [Rule::requiredIf($type === 'card'), 'nullable', 'string', 'min:2', 'max:80'],
            'card_number' => [Rule::requiredIf($type === 'card'), 'nullable', 'string', 'regex:/^[\d\s]{13,23}$/'],
            'card_expiry' => [Rule::requiredIf($type === 'card'), 'nullable', 'regex:/^\d{2}\s?\/\s?\d{2}$/'],
            'card_cvc' => [Rule::requiredIf($type === 'card'), 'nullable', 'regex:/^\d{3,4}$/'],
            // Mobile banking: the payer's wallet number and transaction ID are kept for verification.
            'payer_account' => [Rule::requiredIf($type === 'mobile'), 'nullable', 'string', 'regex:/^(\+?88)?01[3-9]\d{8}$/'],
            'payer_reference' => [Rule::requiredIf($type === 'mobile'), 'nullable', 'string', 'regex:/^[A-Za-z0-9]{6,30}$/'],
        ];
    }

    /** @return array<string, ?string> */
    public function shipping(): array
    {
        return $this->safe()->only(['email', 'ship_name', 'ship_phone', 'ship_line1', 'ship_line2', 'ship_city', 'ship_postal', 'ship_country', 'customer_note']);
    }

    public function selectedMethod(): ?PaymentMethod
    {
        return PaymentMethod::query()->where('is_active', true)->where('code', (string) $this->input('payment_method'))->first();
    }

    /** @return array{payer_account?: string, payer_reference?: string} */
    public function payerDetails(): array
    {
        if ($this->selectedMethod()?->type !== 'mobile') {
            return [];
        }

        return [
            'payer_account' => preg_replace('/^\+?88/', '', $this->validated('payer_account')),
            'payer_reference' => strtoupper($this->validated('payer_reference')),
        ];
    }

    public function wantsAccount(): bool
    {
        return $this->user() === null && $this->boolean('create_account');
    }

    /** Guests creating an account must not collide with an existing one. */
    public function after(): array
    {
        return [function ($validator) {
            if ($this->wantsAccount() && User::query()->where('email', $this->input('email'))->exists()) {
                $validator->errors()->add('email', 'An account already uses this email. Sign in to use it, or untick “Create an account” to check out as a guest.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'email' => 'email address',
            'ship_name' => 'full name',
            'ship_phone' => 'phone number',
            'ship_line1' => 'street address',
            'ship_city' => 'city',
            'ship_postal' => 'postal code',
            'ship_country' => 'country',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_if_accepted' => 'Enter your email to create an account.',
            'ship_country.in' => 'We do not currently deliver to that country.',
            'ship_phone.regex' => 'Enter a valid phone number so the courier can reach you.',
            'payment_method.exists' => 'That payment method is currently unavailable.',
            'card_number.regex' => 'Enter a valid card number.',
            'card_expiry.regex' => 'Enter the expiry as MM / YY.',
            'card_cvc.regex' => 'Enter a valid CVC.',
            'payer_account.required' => 'Enter the mobile wallet number you paid from.',
            'payer_account.regex' => 'Enter a valid 11-digit mobile number, e.g. 01XXXXXXXXX.',
            'payer_reference.required' => 'Enter the transaction ID from your payment confirmation.',
            'payer_reference.regex' => 'Enter a valid transaction ID (letters and numbers only).',
        ];
    }
}
