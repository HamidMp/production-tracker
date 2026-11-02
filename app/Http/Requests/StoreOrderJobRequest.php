<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\OrderJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', OrderJob::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'installer_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Installer->value),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'installer_id.exists' => 'The selected user is not an installer.',
        ];
    }
}
