<?php

namespace App\Http\Requests\Employees;

use App\Domain\Employees\DTOs\EmployeeData;
use App\Http\Requests\Employees\Concerns\ResolvesCountryRules;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class UpdateEmployeeRequest extends FormRequest
{
    use ResolvesCountryRules;

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
        return array_merge(
            $this->baseRules(isUpdate: true),
            $this->rulesForCountry(isUpdate: true)
        );
    }

    protected function prepareForValidation(): void
    {
        if ($country = $this->input('country')) {
            $this->merge([
                'country' => strtoupper($country),
            ]);
        }
    }

    public function dto(Employee $employee): EmployeeData
    {
        $validated = $this->validated();

        return EmployeeData::fromArray([
            'id' => $employee->id,
            'name' => $validated['name'] ?? $employee->name,
            'last_name' => $validated['last_name'] ?? $employee->last_name,
            'salary' => $validated['salary'] ?? $employee->salary,
            'country' => $validated['country'] ?? $employee->country,
            'attributes' => $validated['attributes'] ?? $employee->attributes,
        ]);
    }

    public function changedFields(): array
    {
        $validated = $this->validated();

        $baseFields = collect($validated)
            ->keys()
            ->reject(fn ($key) => $key === 'attributes')
            ->values()
            ->all();

        $attributeFields = array_map(
            fn (string $key) => "attributes.$key",
            array_keys(Arr::get($validated, 'attributes', []))
        );

        return array_values(array_unique(array_merge($baseFields, $attributeFields)));
    }
}
