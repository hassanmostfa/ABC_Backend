<?php

namespace App\Http\Requests\Admin;

use App\Enums\ComplaintStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplaintStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ComplaintStatus::class)],
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
