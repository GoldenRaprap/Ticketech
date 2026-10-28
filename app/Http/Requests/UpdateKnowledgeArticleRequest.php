<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeArticleRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:180'],
            'category' => ['required', 'string', 'max:80'],
            'summary' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:30000'],
            'is_published' => ['sometimes', 'boolean'],
            'images' => ['sometimes', 'array', 'max:5'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_images' => ['sometimes', 'array'],
            'remove_images.*' => [
                'integer',
                'distinct',
                Rule::exists('knowledge_article_images', 'id')
                    ->where('knowledge_article_id', $this->route('article')->id),
            ],
        ];
    }
}
