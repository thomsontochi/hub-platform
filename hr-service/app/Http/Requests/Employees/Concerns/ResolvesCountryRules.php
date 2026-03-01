<?php

namespace App\Http\Requests\Employees\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

trait ResolvesCountryRules
{
    protected function allowedCountries(): array
    {
        return array_keys(config('countries', []));
    }

    protected function countryAttributeRules(string $country, bool $isUpdate = false): array
    {
        $rules = data_get(config('countries'), "$country.validation_rules", []);

        return collect($rules)
            ->mapWithKeys(function ($ruleSet, $attribute) use ($isUpdate) {
                $rules = Arr::wrap($ruleSet);

                if ($isUpdate) {
                    $rules = array_map(function ($rule) {
                        return $rule === 'required' ? 'sometimes' : $rule;
                    }, $rules);
                }

                return ["attributes.$attribute" => $rules];
            })
            ->toArray();
    }

    protected function baseRules(bool $isUpdate = false): array
    {
        $required = $isUpdate ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => array_merge($required, ['string', 'max:255']),
            'last_name' => array_merge($required, ['string', 'max:255']),
            'salary' => array_merge($required, ['numeric', 'min:0.01']),
            'country' => array_merge($isUpdate ? ['sometimes', 'string'] : ['required', 'string'], [
                'size:3',
                Rule::in($this->allowedCountries()),
            ]),
            'attributes' => ['sometimes', 'array'],
        ];
    }

    protected function rulesForCountry(bool $isUpdate = false): array
    {
        $country = strtoupper(
            $this->input('country', 
                optional($this->route('employee'))->country ?? ''
            )
        );

        if (! $country) {
            return [];
        }

        return $this->countryAttributeRules($country, $isUpdate);
    }

    protected function uniqueIdentifierKey(): ?string
    {
        $country = strtoupper(
            $this->input('country',
                optional($this->route('employee'))->country ?? ''
            )
        );

        if (! $country) {
            return null;
        }

        return data_get(config('countries'), "$country.unique_identifier");
    }
}
