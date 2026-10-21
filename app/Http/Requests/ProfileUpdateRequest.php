<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\SmsPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if (! $user) {
            return;
        }

        $this->merge([
            'phone' => $this->input('phone', $user->phone),
            'sms_notifications_enabled' => $this->input('sms_notifications_enabled', (bool) $user->sms_notifications_enabled),
            'birth_date' => $this->input('birth_date', optional($user->birth_date)->format('Y-m-d')),
            'gender' => $this->input('gender', $user->gender),
            'address_line' => $this->input('address_line', $user->address_line),
            'city' => $this->input('city', $user->city),
            'province' => $this->input('province', $user->province),
            'postal_code' => $this->input('postal_code', $user->postal_code),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'phone' => ['required', 'string', 'max:30'],
            'sms_notifications_enabled' => [
                'boolean',
                function ($attribute, $value, $fail) {
                    $phone = $this->input('phone');

                    if ($this->boolean('sms_notifications_enabled') && (! is_string($phone) || ! SmsPhoneNumber::normalize($phone))) {
                        $fail('Enter a valid Philippine mobile number before enabling SMS alerts.');
                    }
                },
            ],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'string', Rule::in(['male', 'female', 'non_binary', 'prefer_not_to_say'])],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'profile_photo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
