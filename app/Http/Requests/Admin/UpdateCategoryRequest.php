<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Authorize the request (admin middleware enforces the role).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'agency_id' => ['sometimes', 'required', 'integer', 'exists:agencies,id'],
            'responsible_employee_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', Role::Employee->value)->where('is_active', true),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Ensure the responsible employee belongs to the category's agency.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $employeeId = $this->input('responsible_employee_id');

            if (! $employeeId || $validator->errors()->has('responsible_employee_id')) {
                return;
            }

            $agencyId = $this->resolveAgencyId();
            $employee = User::find($employeeId);

            if ($employee && $agencyId && $employee->agency_id !== (int) $agencyId) {
                $validator->errors()->add(
                    'responsible_employee_id',
                    "The selected employee does not belong to this category's agency."
                );
            }
        });
    }

    /**
     * The agency the category will belong to after this update.
     */
    protected function resolveAgencyId(): ?int
    {
        if ($this->filled('agency_id')) {
            return $this->integer('agency_id');
        }

        return $this->route('category')?->agency_id;
    }
}
