<?php

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('super admin dashboard loads system-wide stats and departments', function () {
    $roleSuper = Role::factory()->create(['slug' => 'super-admin', 'name' => 'Super Admin']);
    $rolePegawai = Role::factory()->create(['slug' => 'pegawai', 'name' => 'Pegawai']);

    $userAdmin = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $userAdmin->id,
        'role_id' => $roleSuper->id,
    ]);

    $deptA = Department::factory()->create(['name' => 'Bagian A']);
    $deptB = Department::factory()->create(['name' => 'Bagian B']);

    Employee::factory()->create(['department_id' => $deptA->id, 'role_id' => $rolePegawai->id]);
    Employee::factory()->create(['department_id' => $deptB->id, 'role_id' => $rolePegawai->id]);

    $response = $this->actingAs($userAdmin)->get('/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('role', 'super-admin')
            ->where('department', null)
            ->where('monthStats', null)
            ->has('summary')
            ->where('summary.total_departments', Department::count())
            ->has('recentAttendances')
        );
});

test('admin bagian dashboard loads department-scoped stats and recent attendances', function () {
    $roleAdminBagian = Role::factory()->create(['slug' => 'admin-bagian', 'name' => 'Admin Bagian']);
    $rolePegawai = Role::factory()->create(['slug' => 'pegawai', 'name' => 'Pegawai']);

    $deptA = Department::factory()->create(['name' => 'Bagian SDM']);
    $deptB = Department::factory()->create(['name' => 'Bagian IT']);

    $userAdminDept = User::factory()->create();
    $empAdmin = Employee::factory()->create([
        'user_id' => $userAdminDept->id,
        'department_id' => $deptA->id,
        'role_id' => $roleAdminBagian->id,
    ]);

    $empA1 = Employee::factory()->create(['department_id' => $deptA->id, 'role_id' => $rolePegawai->id]);
    $empB1 = Employee::factory()->create(['department_id' => $deptB->id, 'role_id' => $rolePegawai->id]);

    $today = Carbon::today()->toDateString();
    Attendance::factory()->create(['employee_id' => $empA1->id, 'date' => $today, 'status' => 'hadir']);
    Attendance::factory()->create(['employee_id' => $empB1->id, 'date' => $today, 'status' => 'hadir']);

    $response = $this->actingAs($userAdminDept)->get('/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('role', 'admin-bagian')
            ->where('department.id', $deptA->id)
            ->where('summary.total_employees', 2) // empAdmin + empA1
            ->where('summary.total_departments', 1)
            ->has('recentAttendances', 1)
            ->where('recentAttendances.0.employee_id', $empA1->id)
        );
});

test('pegawai dashboard loads personal monthly stats and recent attendance history', function () {
    $rolePegawai = Role::factory()->create(['slug' => 'pegawai', 'name' => 'Pegawai']);
    $dept = Department::factory()->create();

    $userPegawai = User::factory()->create();
    $emp = Employee::factory()->create([
        'user_id' => $userPegawai->id,
        'department_id' => $dept->id,
        'role_id' => $rolePegawai->id,
    ]);

    $userOther = User::factory()->create();
    $empOther = Employee::factory()->create([
        'user_id' => $userOther->id,
        'department_id' => $dept->id,
        'role_id' => $rolePegawai->id,
    ]);

    $today = Carbon::today()->toDateString();
    $yesterday = Carbon::today()->subDay()->toDateString();

    Attendance::factory()->create(['employee_id' => $emp->id, 'date' => $today, 'status' => 'hadir']);
    Attendance::factory()->create(['employee_id' => $emp->id, 'date' => $yesterday, 'status' => 'terlambat']);
    Attendance::factory()->create(['employee_id' => $empOther->id, 'date' => $today, 'status' => 'hadir']);

    $response = $this->actingAs($userPegawai)->get('/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('role', 'pegawai')
            ->has('monthStats')
            ->where('monthStats.hadir', 1)
            ->where('monthStats.terlambat', 1)
            ->where('monthStats.total_present', 2)
            ->has('recentAttendances', 2)
            ->where('recentAttendances.0.employee_id', $emp->id)
            ->where('recentAttendances.1.employee_id', $emp->id)
        );
});

test('dashboard returns current localized today date and formatted date', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('today', Carbon::today()->toDateString())
            ->where('today_formatted', Carbon::now()->translatedFormat('l, d F Y'))
        );
});
