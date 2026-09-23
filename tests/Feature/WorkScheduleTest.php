<?php

use App\Models\Attendance;
use App\Models\CompanyProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;

test('super admin can view work hours setting page', function () {
    $superAdminRole = Role::factory()->create(['slug' => 'super-admin', 'name' => 'Super Admin']);
    $user = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $user->id,
        'role_id' => $superAdminRole->id,
    ]);

    $response = $this->actingAs($user)->get('/work-hours');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-hours/Edit')
            ->has('schedule')
            ->where('schedule.work_start_time', '08:00')
            ->where('schedule.work_end_time', '17:00')
        );
});

test('admin bagian cannot access work hours setting page', function () {
    $role = Role::factory()->create(['slug' => 'admin-bagian', 'name' => 'Admin Bagian']);
    $dept = Department::factory()->create();
    $user = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'department_id' => $dept->id,
    ]);

    $response = $this->actingAs($user)->get('/work-hours');
    $response->assertForbidden();
});

test('pegawai cannot access work hours setting page', function () {
    $role = Role::factory()->create(['slug' => 'pegawai', 'name' => 'Pegawai']);
    $user = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $user->id,
        'role_id' => $role->id,
    ]);

    $response = $this->actingAs($user)->get('/work-hours');
    $response->assertForbidden();
});

test('super admin can update work hours and tolerance', function () {
    $superAdminRole = Role::factory()->create(['slug' => 'super-admin', 'name' => 'Super Admin']);
    $user = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $user->id,
        'role_id' => $superAdminRole->id,
    ]);

    $response = $this->actingAs($user)->post('/work-hours', [
        'work_start_time' => '07:30',
        'work_end_time' => '16:30',
        'late_tolerance_minutes' => 15,
    ]);

    $response->assertRedirect();
    $company = CompanyProfile::current();
    expect($company->work_start_time)->toBe('07:30')
        ->and($company->work_end_time)->toBe('16:30')
        ->and($company->late_tolerance_minutes)->toBe(15);
});

test('work hours update validates required fields and end time after start time', function () {
    $superAdminRole = Role::factory()->create(['slug' => 'super-admin', 'name' => 'Super Admin']);
    $user = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $user->id,
        'role_id' => $superAdminRole->id,
    ]);

    $response = $this->actingAs($user)->post('/work-hours', [
        'work_start_time' => '18:00',
        'work_end_time' => '08:00',
        'late_tolerance_minutes' => -5,
    ]);

    $response->assertSessionHasErrors(['work_end_time', 'late_tolerance_minutes']);
});

test('check-in marks status as terlambat when passing dynamic work start time plus tolerance', function () {
    // Configure work hours: 09:00 with 15 mins tolerance (threshold: 09:15)
    $company = CompanyProfile::current();
    $company->update([
        'work_start_time' => '09:00',
        'work_end_time' => '17:00',
        'late_tolerance_minutes' => 15,
    ]);

    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    // Test on time check-in at 09:10
    Carbon::setTestNow('2026-09-23 09:10:00');

    $this->actingAs($user)->post('/attendances/check-in', [
        'latitude' => -6.20876340,
        'longitude' => 106.84559900,
    ]);

    $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', '2026-09-23')->first();
    expect($attendance)->not->toBeNull()
        ->and($attendance->status)->toBe('hadir');

    // Reset for another day with late check-in at 09:20 (past 09:15)
    Carbon::setTestNow('2026-09-24 09:20:00');

    $this->actingAs($user)->post('/attendances/check-in', [
        'latitude' => -6.20876340,
        'longitude' => 106.84559900,
    ]);

    $attendanceLate = Attendance::where('employee_id', $employee->id)->whereDate('date', '2026-09-24')->first();
    expect($attendanceLate)->not->toBeNull()
        ->and($attendanceLate->status)->toBe('terlambat')
        ->and($attendanceLate->notes)->toContain('09:15');

    Carbon::setTestNow();
});

test('check-out before work end time is prohibited', function () {
    $company = CompanyProfile::current();
    $company->update([
        'work_start_time' => '08:00',
        'work_end_time' => '17:00',
    ]);

    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    Carbon::setTestNow('2026-09-23 08:00:00');
    $this->actingAs($user)->post('/attendances/check-in', [
        'latitude' => -6.20876340,
        'longitude' => 106.84559900,
    ]);

    // Check out at 15:30 (before 17:00) must be rejected
    Carbon::setTestNow('2026-09-23 15:30:00');
    $response = $this->actingAs($user)->post('/attendances/check-out', [
        'latitude' => -6.20876340,
        'longitude' => 106.84559900,
    ]);

    $response->assertSessionHasErrors(['check_out']);
    $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', '2026-09-23')->first();
    expect($attendance->check_out)->toBeNull();

    // Check out at 17:00 or after should succeed
    Carbon::setTestNow('2026-09-23 17:00:00');
    $responseOk = $this->actingAs($user)->post('/attendances/check-out', [
        'latitude' => -6.20876340,
        'longitude' => 106.84559900,
    ]);

    $responseOk->assertRedirect();
    $attendance->refresh();
    expect($attendance->check_out)->toBe('17:00:00');

    Carbon::setTestNow();
});
