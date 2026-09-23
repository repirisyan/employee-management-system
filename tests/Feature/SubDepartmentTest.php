<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SubDepartment;
use App\Models\User;

test('authenticated user can view sub-departments list', function () {
    $user = User::factory()->create();
    $sub = SubDepartment::factory()->create(['name' => 'Sub Bagian Mutasi']);

    $response = $this->actingAs($user)->get('/sub-departments');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sub-departments/Index')
            ->has('subDepartments.data', 1)
            ->where('subDepartments.data.0.name', 'Sub Bagian Mutasi')
        );
});

test('user can create a sub-department', function () {
    $user = User::factory()->create();
    $department = Department::factory()->create();

    $response = $this->actingAs($user)->post('/sub-departments', [
        'department_id' => $department->id,
        'name' => 'Software Engineering',
        'code' => 'TI-SE',
        'description' => 'Rekayasa perangkat lunak',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('sub_departments', [
        'department_id' => $department->id,
        'name' => 'Software Engineering',
        'code' => 'TI-SE',
    ]);
});

test('non super admin cannot access master sub-departments', function () {
    $role = Role::factory()->create(['slug' => 'pegawai', 'name' => 'Pegawai']);
    $user = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $user->id,
        'role_id' => $role->id,
    ]);

    $response = $this->actingAs($user)->get('/sub-departments');
    $response->assertForbidden();

    $responsePost = $this->actingAs($user)->post('/sub-departments', [
        'name' => 'Sub Ilegal',
        'code' => 'SI',
    ]);
    $responsePost->assertForbidden();
});

test('user can filter sub-departments by single or multiple parent departments', function () {
    $user = User::factory()->create();
    $deptA = Department::factory()->create(['name' => 'Bagian Keuangan']);
    $deptB = Department::factory()->create(['name' => 'Bagian Kepegawaian']);
    $deptC = Department::factory()->create(['name' => 'Bagian Umum']);

    $subA = SubDepartment::factory()->create(['name' => 'Sub Anggaran', 'department_id' => $deptA->id]);
    $subB = SubDepartment::factory()->create(['name' => 'Sub Mutasi', 'department_id' => $deptB->id]);
    $subC = SubDepartment::factory()->create(['name' => 'Sub Logistik', 'department_id' => $deptC->id]);

    // Single department_id (backward compatibility)
    $responseSingle = $this->actingAs($user)->get("/sub-departments?department_id={$deptA->id}");
    $responseSingle->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sub-departments/Index')
            ->has('subDepartments.data', 1)
            ->where('subDepartments.data.0.name', 'Sub Anggaran')
        );

    // Multi department_ids as array
    $responseMulti = $this->actingAs($user)->get("/sub-departments?department_ids[]={$deptA->id}&department_ids[]={$deptB->id}");
    $responseMulti->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sub-departments/Index')
            ->has('subDepartments.data', 2)
            ->where('filters.department_ids', [$deptA->id, $deptB->id])
        );

    // Multi department_ids as comma-separated string
    $responseCsv = $this->actingAs($user)->get("/sub-departments?department_ids={$deptA->id},{$deptC->id}");
    $responseCsv->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sub-departments/Index')
            ->has('subDepartments.data', 2)
        );
});
