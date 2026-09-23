<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;

test('authenticated user can view departments list', function () {
    $user = User::factory()->create();
    $department = Department::factory()->create(['name' => 'Divisi Keuangan']);

    $response = $this->actingAs($user)->get('/departments');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('departments/Index')
            ->has('departments.data', 1)
            ->where('departments.data.0.name', 'Divisi Keuangan')
        );
});

test('user can create a department', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/departments', [
        'name' => 'Divisi Teknologi',
        'code' => 'TI',
        'description' => 'Departemen teknologi informasi',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('departments', [
        'name' => 'Divisi Teknologi',
        'code' => 'TI',
    ]);
});

test('user can update a department', function () {
    $user = User::factory()->create();
    $department = Department::factory()->create(['name' => 'Lama']);

    $response = $this->actingAs($user)->put("/departments/{$department->id}", [
        'name' => 'Baru',
        'code' => 'BARU',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('departments', [
        'id' => $department->id,
        'name' => 'Baru',
    ]);
});

test('user can delete a department without employees', function () {
    $user = User::factory()->create();
    $department = Department::factory()->create();

    $response = $this->actingAs($user)->delete("/departments/{$department->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('departments', ['id' => $department->id]);
});

test('non super admin cannot access master departments', function () {
    $role = Role::factory()->create(['slug' => 'pegawai', 'name' => 'Pegawai']);
    $user = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $user->id,
        'role_id' => $role->id,
    ]);

    $response = $this->actingAs($user)->get('/departments');
    $response->assertForbidden();

    $responsePost = $this->actingAs($user)->post('/departments', [
        'name' => 'Departemen Ilegal',
        'code' => 'ILEGAL',
    ]);
    $responsePost->assertForbidden();
});
