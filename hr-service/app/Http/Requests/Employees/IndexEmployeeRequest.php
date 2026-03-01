<?php

namespace App\Http\Requests\Employees;

use App\Http\Requests\Employees\Concerns\ResolvesCountryRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexEmployeeRequest extends FormRequest
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
        return [
            'country' => ['required', 'string', 'size:3', Rule::in($this->allowedCountries())],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($country = $this->input('country')) {
            $this->merge([
                'country' => strtoupper($country),
            ]);
        }
    }

    public function perPage(): int
    {
        return (int) $this->input('per_page', 15);
    }
}
