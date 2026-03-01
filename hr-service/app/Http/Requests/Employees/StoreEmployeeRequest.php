<?php

namespace App\Http\Requests\Employees;

use App\Domain\Employees\DTOs\EmployeeData;
use App\Http\Requests\Employees\Concerns\ResolvesCountryRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class StoreEmployeeRequest extends FormRequest
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
            $this->baseRules(),
            $this->rulesForCountry()
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

    public function dto(): EmployeeData
    {
        $validated = $this->validated();

        return EmployeeData::fromArray([
            'id' => null,
            'name' => $validated['name'],
            'last_name' => $validated['last_name'],
            'salary' => $validated['salary'],
            'country' => $validated['country'],
            'attributes' => $validated['attributes'] ?? [],
        ]);
    }

    public function changedFields(): array
    {
        $validated = $this->validated();

        $baseFields = ['name', 'last_name', 'salary', 'country'];
        $attributeFields = array_map(
            fn (string $key) => "attributes.$key",
            array_keys(Arr::get($validated, 'attributes', []))
        );

        return array_values(array_unique(array_merge($baseFields, $attributeFields)));
    }
}
