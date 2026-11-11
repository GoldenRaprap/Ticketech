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
            'student_id' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^09[0-9]{9}$/'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:500'],
            'attachment' => ['nullable', 'array', 'max:5'],
            'attachment.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ];
    }

    /**
     * Get the validation messages for the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.regex' => 'Student or employee ID must contain exactly 10 digits.',
            'phone.regex' => 'Phone number must be 11 digits and start with 09.',
            'attachment.array' => 'Attachments must be submitted as separate files.',
            'attachment.max' => 'You can attach up to 5 files to a ticket.',
            'attachment.*.file' => 'File :position could not be uploaded. Please choose the file again.',
            'attachment.*.mimes' => 'File :position must be a PDF, JPG, PNG, DOC, or DOCX file.',
            'attachment.*.max' => 'File :position must not exceed 5 MB.',
        ];
    }
}
