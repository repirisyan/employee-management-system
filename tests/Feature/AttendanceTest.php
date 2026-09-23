<?php

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SubDepartment;
use App\Models\User;
use Carbon\Carbon;

test('authenticated user can view attendances page', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/attendances');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendances/Index')
            ->has('attendances')
            ->has('stats')
        );
});

test('user can record manual attendance', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();
    $today = Carbon::today()->toDateString();

    $response = $this->actingAs($user)->post('/attendances', [
        'employee_id' => $employee->id,
        'date' => $today,
        'check_in' => '08:00',
        'status' => 'hadir',
        'notes' => 'Hadir tepat waktu',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('attendances', [
        'employee_id' => $employee->id,
        'date' => $today,
        'status' => 'hadir',
    ]);
});

test('authenticated employee can clock in and clock out', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $today = Carbon::today()->toDateString();

    // Check-in
    $responseIn = $this->actingAs($user)->post('/attendances/check-in', [
        'latitude' => -6.20876340,
        'longitude' => 106.84559900,
    ]);
    $responseIn->assertRedirect();

    $this->assertDatabaseHas('attendances', [
        'employee_id' => $employee->id,
        'date' => $today,
        'check_in_latitude' => -6.20876340,
        'check_in_longitude' => 106.84559900,
    ]);

    // Check-out during or after work end time (>= 17:00)
    Carbon::setTestNow("{$today} 17:05:00");
    $responseOut = $this->actingAs($user)->post('/attendances/check-out', [
        'latitude' => -6.20876340,
        'longitude' => 106.84559900,
    ]);
    $responseOut->assertRedirect();

    $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', $today)->first();
    expect($attendance->check_out)->not->toBeNull();
    Carbon::setTestNow();
});

test('user can record attendance with newly supported statuses', function (string $status) {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();
    $today = Carbon::today()->toDateString();

    $response = $this->actingAs($user)->post('/attendances', [
        'employee_id' => $employee->id,
        'date' => $today,
        'check_in' => '08:00',
        'status' => $status,
        'notes' => 'Catatan status: '.$status,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('attendances', [
        'employee_id' => $employee->id,
        'date' => $today,
        'status' => $status,
    ]);
})->with([
    'hadir',
    'izin',
    'terlambat',
    'sakit',
    'alpa',
    'dinas_pagi',
    'dinas_sore',
    'cuti',
]);

test('attendance rejects invalid status', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();
    $today = Carbon::today()->toDateString();

    $response = $this->actingAs($user)->post('/attendances', [
        'employee_id' => $employee->id,
        'date' => $today,
        'status' => 'status_tidak_valid',
    ]);

    $response->assertSessionHasErrors(['status']);
});

test('user can update existing attendance record without date validation error', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();
    $today = Carbon::today()->toDateString();

    $attendance = Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => $today,
        'check_in' => '08:00:00',
        'status' => 'hadir',
    ]);

    // Update via PUT route
    $response = $this->actingAs($user)->put("/attendances/{$attendance->id}", [
        'employee_id' => $employee->id,
        'date' => $today,
        'check_in' => '08:15:00',
        'check_out' => '17:00:00',
        'status' => 'terlambat',
        'notes' => 'Terlambat karena macet',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('attendances', [
        'id' => $attendance->id,
        'employee_id' => $employee->id,
        'date' => $today,
        'check_in' => '08:15:00',
        'check_out' => '17:00:00',
        'status' => 'terlambat',
        'notes' => 'Terlambat karena macet',
    ]);
});

test('attendance rejects duplicate record on same date for same employee', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();
    $today = Carbon::today()->toDateString();

    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => $today,
    ]);

    $response = $this->actingAs($user)->post('/attendances', [
        'employee_id' => $employee->id,
        'date' => $today,
        'status' => 'hadir',
    ]);

    $response->assertSessionHasErrors(['date']);
});

test('employee check-in records gps latitude and longitude', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $today = Carbon::today()->toDateString();

    $response = $this->actingAs($user)->post('/attendances/check-in', [
        'latitude' => -6.20876340,
        'longitude' => 106.84559900,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('attendances', [
        'employee_id' => $employee->id,
        'date' => $today,
        'check_in_latitude' => -6.20876340,
        'check_in_longitude' => 106.84559900,
    ]);

    $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', $today)->first();
    expect($attendance->check_in_map_url)->toContain('https://www.google.com/maps?q=-6.2087634,106.845599');
});

test('employee check-out records gps latitude and longitude', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $today = Carbon::today()->toDateString();

    // Check-in first
    $this->actingAs($user)->post('/attendances/check-in', [
        'latitude' => -6.20876340,
        'longitude' => 106.84559900,
    ]);

    // Check-out with different coordinates after work end time (>= 17:00)
    Carbon::setTestNow("{$today} 17:05:00");
    $response = $this->actingAs($user)->post('/attendances/check-out', [
        'latitude' => -6.21500000,
        'longitude' => 106.85000000,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('attendances', [
        'employee_id' => $employee->id,
        'date' => $today,
        'check_out_latitude' => -6.21500000,
        'check_out_longitude' => 106.85000000,
    ]);

    $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', $today)->first();
    expect($attendance->check_out_map_url)->toContain('https://www.google.com/maps?q=-6.215,106.85');
    Carbon::setTestNow();
});

test('check-in rejects invalid latitude or longitude', function () {
    $user = User::factory()->create();
    Employee::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post('/attendances/check-in', [
        'latitude' => 999.0, // Invalid latitude (> 90)
        'longitude' => 106.845599,
    ]);

    $response->assertSessionHasErrors(['latitude']);
});

test('check-in fails when gps is missing or inactive', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $today = Carbon::today()->toDateString();

    $response = $this->actingAs($user)->post('/attendances/check-in', []);

    $response->assertSessionHasErrors(['latitude', 'longitude']);
    $this->assertDatabaseMissing('attendances', [
        'employee_id' => $employee->id,
        'date' => $today,
    ]);
});

test('check-out fails when gps is missing or inactive', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $today = Carbon::today()->toDateString();

    // Check-in first with valid GPS
    $this->actingAs($user)->post('/attendances/check-in', [
        'latitude' => -6.20876340,
        'longitude' => 106.84559900,
    ]);

    // Check-out without GPS
    $response = $this->actingAs($user)->post('/attendances/check-out', []);

    $response->assertSessionHasErrors(['latitude', 'longitude']);

    $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', $today)->first();
    expect($attendance->check_out)->toBeNull();
});

test('admin bagian can only see attendances from their department', function () {
    $deptA = Department::factory()->create(['name' => 'Bagian A']);
    $deptB = Department::factory()->create(['name' => 'Bagian B']);

    $roleAdminBagian = Role::factory()->create(['slug' => 'admin-bagian', 'name' => 'Admin Bagian']);
    $rolePegawai = Role::factory()->create(['slug' => 'pegawai', 'name' => 'Pegawai']);

    $userAdmin = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $userAdmin->id,
        'role_id' => $roleAdminBagian->id,
        'department_id' => $deptA->id,
    ]);

    $empA = Employee::factory()->create(['department_id' => $deptA->id, 'role_id' => $rolePegawai->id]);
    $empB = Employee::factory()->create(['department_id' => $deptB->id, 'role_id' => $rolePegawai->id]);

    $today = Carbon::today()->toDateString();
    Attendance::factory()->create(['employee_id' => $empA->id, 'date' => $today]);
    Attendance::factory()->create(['employee_id' => $empB->id, 'date' => $today]);

    $response = $this->actingAs($userAdmin)->get('/attendances');
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendances/Index')
            ->has('attendances.data', 1)
            ->where('attendances.data.0.employee_id', $empA->id)
            ->has('departments', 1)
            ->where('departments.0.id', $deptA->id)
        );
});

test('admin bagian cannot record or delete attendance of employee from another department', function () {
    $deptA = Department::factory()->create();
    $deptB = Department::factory()->create();

    $roleAdminBagian = Role::factory()->create(['slug' => 'admin-bagian', 'name' => 'Admin Bagian']);
    $rolePegawai = Role::factory()->create(['slug' => 'pegawai', 'name' => 'Pegawai']);

    $userAdmin = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $userAdmin->id,
        'role_id' => $roleAdminBagian->id,
        'department_id' => $deptA->id,
    ]);

    $empB = Employee::factory()->create(['department_id' => $deptB->id, 'role_id' => $rolePegawai->id]);
    $today = Carbon::today()->toDateString();

    // Attempt create manual attendance for Dept B employee
    $responseStore = $this->actingAs($userAdmin)->post('/attendances', [
        'employee_id' => $empB->id,
        'date' => $today,
        'check_in' => '08:00',
        'status' => 'hadir',
    ]);
    $responseStore->assertForbidden();

    // Create record directly
    $attB = Attendance::factory()->create([
        'employee_id' => $empB->id,
        'date' => $today,
        'status' => 'hadir',
    ]);

    // Attempt update
    $responseUpdate = $this->actingAs($userAdmin)->put("/attendances/{$attB->id}", [
        'employee_id' => $empB->id,
        'date' => $today,
        'status' => 'terlambat',
    ]);
    $responseUpdate->assertForbidden();

    // Attempt delete
    $responseDelete = $this->actingAs($userAdmin)->delete("/attendances/{$attB->id}");
    $responseDelete->assertForbidden();
});

test('regular pegawai can only view their own attendance records', function () {
    $rolePegawai = Role::factory()->create(['slug' => 'pegawai', 'name' => 'Pegawai']);
    $userA = User::factory()->create();
    $empA = Employee::factory()->create(['user_id' => $userA->id, 'role_id' => $rolePegawai->id]);

    $userB = User::factory()->create();
    $empB = Employee::factory()->create(['user_id' => $userB->id, 'role_id' => $rolePegawai->id]);

    $today = Carbon::today()->toDateString();
    Attendance::factory()->create(['employee_id' => $empA->id, 'date' => $today]);
    Attendance::factory()->create(['employee_id' => $empB->id, 'date' => $today]);

    $response = $this->actingAs($userA)->get('/attendances');
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendances/Index')
            ->has('attendances.data', 1)
            ->where('attendances.data.0.employee_id', $empA->id)
            ->has('departments', 0)
        );
});

test('regular pegawai cannot manually store, update, or delete attendance records', function () {
    $rolePegawai = Role::factory()->create(['slug' => 'pegawai', 'name' => 'Pegawai']);
    $user = User::factory()->create();
    $emp = Employee::factory()->create(['user_id' => $user->id, 'role_id' => $rolePegawai->id]);
    $today = Carbon::today()->toDateString();

    // Attempt create manual attendance
    $responseStore = $this->actingAs($user)->post('/attendances', [
        'employee_id' => $emp->id,
        'date' => $today,
        'status' => 'hadir',
    ]);
    $responseStore->assertForbidden();

    // Create record directly
    $att = Attendance::factory()->create([
        'employee_id' => $emp->id,
        'date' => $today,
        'status' => 'hadir',
    ]);

    // Attempt update
    $responseUpdate = $this->actingAs($user)->put("/attendances/{$att->id}", [
        'employee_id' => $emp->id,
        'date' => $today,
        'status' => 'terlambat',
    ]);
    $responseUpdate->assertForbidden();

    // Attempt delete
    $responseDelete = $this->actingAs($user)->delete("/attendances/{$att->id}");
    $responseDelete->assertForbidden();
});

test('user can filter attendances by sub-department', function () {
    $user = User::factory()->create();
    $dept = Department::factory()->create();
    $sub1 = SubDepartment::factory()->create(['department_id' => $dept->id, 'name' => 'Sub IT']);
    $sub2 = SubDepartment::factory()->create(['department_id' => $dept->id, 'name' => 'Sub HR']);

    $emp1 = Employee::factory()->create(['name' => 'Pegawai IT', 'department_id' => $dept->id, 'sub_department_id' => $sub1->id]);
    $emp2 = Employee::factory()->create(['name' => 'Pegawai HR', 'department_id' => $dept->id, 'sub_department_id' => $sub2->id]);

    $today = Carbon::today()->toDateString();
    Attendance::factory()->create(['employee_id' => $emp1->id, 'date' => $today, 'status' => 'hadir']);
    Attendance::factory()->create(['employee_id' => $emp2->id, 'date' => $today, 'status' => 'hadir']);

    $response = $this->actingAs($user)->get("/attendances?sub_department_id={$sub1->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendances/Index')
            ->has('attendances.data', 1)
            ->where('attendances.data.0.employee.name', 'Pegawai IT')
            ->where('filters.sub_department_id', $sub1->id)
            ->has('subDepartments')
        );
});

test('user can filter attendances by department', function () {
    $user = User::factory()->create();
    $deptA = Department::factory()->create(['name' => 'Bagian A']);
    $deptB = Department::factory()->create(['name' => 'Bagian B']);

    $empA = Employee::factory()->create(['name' => 'Pegawai Dept A', 'department_id' => $deptA->id]);
    $empB = Employee::factory()->create(['name' => 'Pegawai Dept B', 'department_id' => $deptB->id]);

    $today = Carbon::today()->toDateString();
    Attendance::factory()->create(['employee_id' => $empA->id, 'date' => $today, 'status' => 'hadir']);
    Attendance::factory()->create(['employee_id' => $empB->id, 'date' => $today, 'status' => 'hadir']);

    $response = $this->actingAs($user)->get("/attendances?department_id={$deptA->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendances/Index')
            ->has('attendances.data', 1)
            ->where('attendances.data.0.employee.name', 'Pegawai Dept A')
            ->where('filters.department_id', $deptA->id)
        );
});

test('user can filter attendances by multiple departments and multiple sub-departments', function () {
    $user = User::factory()->create();
    $deptA = Department::factory()->create(['name' => 'Bagian A']);
    $deptB = Department::factory()->create(['name' => 'Bagian B']);
    $deptC = Department::factory()->create(['name' => 'Bagian C']);

    $subA1 = SubDepartment::factory()->create(['department_id' => $deptA->id, 'name' => 'Sub A1']);
    $subB1 = SubDepartment::factory()->create(['department_id' => $deptB->id, 'name' => 'Sub B1']);

    $empA1 = Employee::factory()->create(['name' => 'Pegawai A1', 'department_id' => $deptA->id, 'sub_department_id' => $subA1->id]);
    $empB1 = Employee::factory()->create(['name' => 'Pegawai B1', 'department_id' => $deptB->id, 'sub_department_id' => $subB1->id]);
    $empC = Employee::factory()->create(['name' => 'Pegawai C', 'department_id' => $deptC->id]);

    $today = Carbon::today()->toDateString();
    Attendance::factory()->create(['employee_id' => $empA1->id, 'date' => $today, 'status' => 'hadir']);
    Attendance::factory()->create(['employee_id' => $empB1->id, 'date' => $today, 'status' => 'hadir']);
    Attendance::factory()->create(['employee_id' => $empC->id, 'date' => $today, 'status' => 'hadir']);

    // Multiple department_ids
    $responseDept = $this->actingAs($user)->get("/attendances?department_ids[]={$deptA->id}&department_ids[]={$deptB->id}");
    $responseDept->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendances/Index')
            ->has('attendances.data', 2)
            ->where('filters.department_ids', [$deptA->id, $deptB->id])
        );

    // Multiple sub_department_ids
    $responseSub = $this->actingAs($user)->get("/attendances?sub_department_ids[]={$subA1->id}");
    $responseSub->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendances/Index')
            ->has('attendances.data', 1)
            ->where('filters.sub_department_ids', [$subA1->id])
        );
});

test('user can filter attendances by date range', function () {
    $user = User::factory()->create();
    $emp = Employee::factory()->create();

    $date1 = '2026-09-10';
    $date2 = '2026-09-15';
    $date3 = '2026-09-20';

    Attendance::factory()->create(['employee_id' => $emp->id, 'date' => $date1, 'status' => 'hadir']);
    Attendance::factory()->create(['employee_id' => $emp->id, 'date' => $date2, 'status' => 'hadir']);
    Attendance::factory()->create(['employee_id' => $emp->id, 'date' => $date3, 'status' => 'hadir']);

    // Range covering date1 and date2
    $response = $this->actingAs($user)->get('/attendances?start_date=2026-09-10&end_date=2026-09-15');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendances/Index')
            ->has('attendances.data', 2)
            ->where('filters.start_date', '2026-09-10')
            ->where('filters.end_date', '2026-09-15')
            ->where('startDate', '2026-09-10')
            ->where('endDate', '2026-09-15')
        );
});

test('user can filter attendances by multiple statuses', function () {
    $user = User::factory()->create();
    $emp1 = Employee::factory()->create();
    $emp2 = Employee::factory()->create();
    $emp3 = Employee::factory()->create();
    $date = '2026-09-20';

    Attendance::factory()->create(['employee_id' => $emp1->id, 'date' => $date, 'status' => 'hadir']);
    Attendance::factory()->create(['employee_id' => $emp2->id, 'date' => $date, 'status' => 'terlambat']);
    Attendance::factory()->create(['employee_id' => $emp3->id, 'date' => $date, 'status' => 'izin']);

    $response = $this->actingAs($user)->get("/attendances?date={$date}&statuses[]=hadir&statuses[]=terlambat");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendances/Index')
            ->has('attendances.data', 2)
            ->where('filters.statuses', ['hadir', 'terlambat'])
        );
});

test('user can filter attendances with custom page size', function () {
    $user = User::factory()->create();
    $date = '2026-09-20';

    for ($i = 0; $i < 20; $i++) {
        $emp = Employee::factory()->create();
        Attendance::factory()->create(['employee_id' => $emp->id, 'date' => $date]);
    }

    $response = $this->actingAs($user)->get("/attendances?date={$date}&per_page=25");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendances/Index')
            ->has('attendances.data', 20)
            ->where('attendances.per_page', 25)
            ->where('filters.per_page', 25)
        );
});
