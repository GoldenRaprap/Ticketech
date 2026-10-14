<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'student_id' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'priority' => ['required', Rule::in(['High', 'Medium', 'Low'])],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ];
    }
}
