<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\SubDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubDepartment>
 */
class SubDepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => ucfirst(fake()->word().' '.fake()->word()),
            'code' => strtoupper(fake()->lexify('???')),
            'description' => fake()->sentence(),
        ];
    }
}
