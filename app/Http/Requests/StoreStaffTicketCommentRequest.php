<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreStaffTicketCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
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
            'attachment.array' => 'Attachments must be submitted as separate files.',
            'attachment.max' => 'You can attach up to 5 files per message.',
            'attachment.*.file' => 'File :position could not be uploaded. Please choose the file again.',
            'attachment.*.mimes' => 'File :position must be a PDF, JPG, PNG, DOC, or DOCX file.',
            'attachment.*.max' => 'File :position must not exceed 5 MB.',
        ];
    }
}
