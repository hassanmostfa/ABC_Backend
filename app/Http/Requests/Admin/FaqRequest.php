<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FaqRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $faqId = $this->route('id');
        $isUpdate = !is_null($faqId);

        return [
            'question_en' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'question_ar' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
            'answer_en' => $isUpdate ? 'sometimes|required|string' : 'required|string',
            'answer_ar' => $isUpdate ? 'sometimes|required|string' : 'required|string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ];
    }
}
