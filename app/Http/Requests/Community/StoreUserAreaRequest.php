<?php

namespace App\Http\Requests\Community;

use App\Enums\AreaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserAreaRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('areas')],
            'parent_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('status', AreaStatus::APPROVED->value)],
        ];
    }
}
