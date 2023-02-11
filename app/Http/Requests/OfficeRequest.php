<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfficeRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'title' => [Rule::requiredIf($this->route('post')), 'string'],
            'description' => [Rule::requiredIf($this->route('post')), 'string'],
            'lat' => [Rule::requiredIf($this->route('post')), 'numeric'],
            'lng' => [Rule::requiredIf($this->route('post')), 'numeric'],
            'address_line1' => [Rule::requiredIf($this->route('post')), 'string'],
            'address_line2' => ['nullable', 'string'],
            'hidden' => ['bool'],
            'price_per_day' => [Rule::requiredIf($this->route('post')), 'integer', 'min:100'],
            'monthly_discount' => ['integer', 'min:0'],

            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
        ];
    }
}
