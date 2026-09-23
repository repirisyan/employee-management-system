<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SubDepartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $department = Department::factory()->create();
        $subDepartment = SubDepartment::factory()->create(['department_id' => $department->id]);

        return [
            'user_id' => User::factory(),
            'nip' => fake()->unique()->numerify('1990##########'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'role_id' => Role::factory(),
            'department_id' => $department->id,
            'sub_department_id' => $subDepartment->id,
            'gender' => fake()->randomElement(['L', 'P']),
            'address' => fake()->address(),
            'status' => 'active',
        ];
    }
}
