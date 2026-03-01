<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        $country = $this->faker->randomElement(['USA', 'GERMANY']);

        return [
            'name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'salary' => $this->faker->numberBetween(50000, 120000),
            'country' => $country,
            'attributes' => $this->attributesForCountry($country),
        ];
    }

    protected function attributesForCountry(string $country): array
    {
        return match (strtoupper($country)) {
            'GERMANY' => [
                'tax_id' => 'DE'.$this->faker->numerify('#########'),
                'goal' => Str::title($this->faker->words(5, true)),
            ],
            default => [
                'ssn' => $this->faker->ssn(),
                'address' => $this->faker->streetAddress(),
            ],
        };
    }
}
