<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SubDepartment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('authenticated user can view employees list', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['name' => 'Ahmad Pegawai']);

    $response = $this->actingAs($user)->get('/employees');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 1)
            ->where('employees.data.0.name', 'Ahmad Pegawai')
        );
});

test('user can create an employee with nip, department, sub-department, and role', function () {
    $user = User::factory()->create();
    $dept = Department::factory()->create();
    $sub = SubDepartment::factory()->create(['department_id' => $dept->id]);
    $role = Role::factory()->create();

    $response = $this->actingAs($user)->post('/employees', [
        'nip' => '199501012020011005',
        'name' => 'Budi Santoso',
        'email' => 'budi.santoso@example.com',
        'phone' => '08123456789',
        'role_id' => $role->id,
        'department_id' => $dept->id,
        'sub_department_id' => $sub->id,
        'gender' => 'L',
        'status' => 'active',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('employees', [
        'nip' => '199501012020011005',
        'name' => 'Budi Santoso',
        'role_id' => $role->id,
        'department_id' => $dept->id,
        'sub_department_id' => $sub->id,
    ]);
});

test('authenticated user can sort employees by name server side', function () {
    $user = User::factory()->create();
    Employee::factory()->create(['name' => 'Zulfa']);
    Employee::factory()->create(['name' => 'Andi']);

    // Sort ascending
    $responseAsc = $this->actingAs($user)->get('/employees?sort=name&direction=asc');
    $responseAsc->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->where('employees.data.0.name', 'Andi')
            ->where('employees.data.1.name', 'Zulfa')
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'asc')
        );

    // Sort descending
    $responseDesc = $this->actingAs($user)->get('/employees?sort=name&direction=desc');
    $responseDesc->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->where('employees.data.0.name', 'Zulfa')
            ->where('employees.data.1.name', 'Andi')
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'desc')
        );
});

test('admin bagian can only view employees from their department', function () {
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

    $empA = Employee::factory()->create(['name' => 'Pegawai Dept A', 'department_id' => $deptA->id, 'role_id' => $rolePegawai->id]);
    $empB = Employee::factory()->create(['name' => 'Pegawai Dept B', 'department_id' => $deptB->id, 'role_id' => $rolePegawai->id]);

    $response = $this->actingAs($userAdmin)->get('/employees');
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 2) // userAdmin's employee + empA
            ->where('departments.0.id', $deptA->id)
            ->has('departments', 1)
        );
});

test('admin bagian cannot create employee for another department', function () {
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

    $response = $this->actingAs($userAdmin)->post('/employees', [
        'nip' => '199501012020011099',
        'name' => 'Pegawai Ilegal Dept B',
        'role_id' => $rolePegawai->id,
        'department_id' => $deptB->id, // Attempt to create in Dept B
        'status' => 'active',
    ]);

    $response->assertForbidden();
});

test('admin bagian cannot update or delete employee from another department', function () {
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

    // Attempt update
    $responseUpdate = $this->actingAs($userAdmin)->put("/employees/{$empB->id}", [
        'nip' => $empB->nip,
        'name' => 'Updated Name',
        'role_id' => $rolePegawai->id,
        'department_id' => $deptB->id,
        'status' => 'active',
    ]);
    $responseUpdate->assertForbidden();

    // Attempt delete
    $responseDelete = $this->actingAs($userAdmin)->delete("/employees/{$empB->id}");
    $responseDelete->assertForbidden();
});

test('regular pegawai cannot access employee index', function () {
    $rolePegawai = Role::factory()->create(['slug' => 'pegawai', 'name' => 'Pegawai']);
    $dept = Department::factory()->create();

    $user = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $user->id,
        'role_id' => $rolePegawai->id,
        'department_id' => $dept->id,
    ]);

    $response = $this->actingAs($user)->get('/employees');
    $response->assertForbidden();
});

test('employee can be created with optional avatar and it is converted to webp', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $dept = Department::factory()->create();
    $role = Role::factory()->create();

    $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

    $response = $this->actingAs($user)->post('/employees', [
        'nip' => '199501012020011088',
        'name' => 'Foto Pegawai',
        'role_id' => $role->id,
        'department_id' => $dept->id,
        'status' => 'active',
        'avatar' => $file,
    ]);

    $response->assertRedirect();

    $employee = Employee::where('nip', '199501012020011088')->firstOrFail();
    expect($employee->avatar)->not->toBeNull();
    expect($employee->avatar)->toEndWith('.webp');
    expect($employee->avatar_url)->toContain($employee->avatar);

    Storage::disk('public')->assertExists($employee->avatar);
});

test('employee avatar can be updated and old avatar is deleted', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $dept = Department::factory()->create();
    $role = Role::factory()->create();

    // Create first with avatar
    $file1 = UploadedFile::fake()->image('first.jpg', 150, 150);
    $this->actingAs($user)->post('/employees', [
        'nip' => '199501012020011089',
        'name' => 'Avatar Update Test',
        'role_id' => $role->id,
        'department_id' => $dept->id,
        'status' => 'active',
        'avatar' => $file1,
    ]);

    $employee = Employee::where('nip', '199501012020011089')->firstOrFail();
    $oldAvatar = $employee->avatar;
    Storage::disk('public')->assertExists($oldAvatar);

    // Update with new avatar
    $file2 = UploadedFile::fake()->image('second.png', 150, 150);
    $response = $this->actingAs($user)->post("/employees/{$employee->id}", [
        'nip' => $employee->nip,
        'name' => 'Avatar Update Test',
        'role_id' => $role->id,
        'department_id' => $dept->id,
        'status' => 'active',
        'avatar' => $file2,
        '_method' => 'put',
    ]);

    $response->assertRedirect();

    $employee->refresh();
    expect($employee->avatar)->not->toBeNull();
    expect($employee->avatar)->not->toBe($oldAvatar);
    expect($employee->avatar)->toEndWith('.webp');

    Storage::disk('public')->assertMissing($oldAvatar);
    Storage::disk('public')->assertExists($employee->avatar);
});

test('employee avatar can be removed', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $dept = Department::factory()->create();
    $role = Role::factory()->create();

    $file = UploadedFile::fake()->image('to_remove.jpg', 150, 150);
    $this->actingAs($user)->post('/employees', [
        'nip' => '199501012020011090',
        'name' => 'Avatar Removal Test',
        'role_id' => $role->id,
        'department_id' => $dept->id,
        'status' => 'active',
        'avatar' => $file,
    ]);

    $employee = Employee::where('nip', '199501012020011090')->firstOrFail();
    $oldAvatar = $employee->avatar;
    Storage::disk('public')->assertExists($oldAvatar);

    // Remove avatar
    $response = $this->actingAs($user)->post("/employees/{$employee->id}", [
        'nip' => $employee->nip,
        'name' => 'Avatar Removal Test',
        'role_id' => $role->id,
        'department_id' => $dept->id,
        'status' => 'active',
        'remove_avatar' => true,
        '_method' => 'put',
    ]);

    $response->assertRedirect();

    $employee->refresh();
    expect($employee->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($oldAvatar);
});

test('super admin can reset employee password for an employee with user account', function () {
    $superAdmin = User::factory()->create();
    $targetUser = User::factory()->create([
        'password' => Hash::make('oldpassword123'),
    ]);
    $employee = Employee::factory()->create([
        'user_id' => $targetUser->id,
        'email' => $targetUser->email,
        'name' => 'Target Pegawai',
    ]);

    $response = $this->actingAs($superAdmin)->post("/employees/{$employee->id}/reset-password", [
        'password' => 'newsecretpassword123',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $targetUser->refresh();
    expect(Hash::check('newsecretpassword123', $targetUser->password))->toBeTrue();
});

test('super admin can reset employee password and automatically create user account if not created yet', function () {
    $superAdmin = User::factory()->create();
    $employee = Employee::factory()->create([
        'user_id' => null,
        'email' => 'newaccount@example.com',
        'name' => 'Pegawai Tanpa User',
    ]);

    $response = $this->actingAs($superAdmin)->post("/employees/{$employee->id}/reset-password", [
        'password' => 'initialpassword123',
    ]);

    $response->assertRedirect();
    $employee->refresh();

    expect($employee->user_id)->not->toBeNull();
    $createdUser = User::find($employee->user_id);
    expect($createdUser)->not->toBeNull();
    expect($createdUser->email)->toBe('newaccount@example.com');
    expect(Hash::check('initialpassword123', $createdUser->password))->toBeTrue();
});

test('admin bagian can reset employee password in their own department', function () {
    $deptA = Department::factory()->create(['name' => 'Bagian A']);
    $roleAdminBagian = Role::factory()->create(['slug' => 'admin-bagian', 'name' => 'Admin Bagian']);
    $rolePegawai = Role::factory()->create(['slug' => 'pegawai', 'name' => 'Pegawai']);

    $userAdmin = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $userAdmin->id,
        'role_id' => $roleAdminBagian->id,
        'department_id' => $deptA->id,
    ]);

    $targetUser = User::factory()->create();
    $empA = Employee::factory()->create([
        'department_id' => $deptA->id,
        'role_id' => $rolePegawai->id,
        'user_id' => $targetUser->id,
        'email' => $targetUser->email,
    ]);

    $response = $this->actingAs($userAdmin)->post("/employees/{$empA->id}/reset-password", [
        'password' => 'deptAdminPassword123',
    ]);

    $response->assertRedirect();
    $targetUser->refresh();
    expect(Hash::check('deptAdminPassword123', $targetUser->password))->toBeTrue();
});

test('admin bagian cannot reset employee password in another department', function () {
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

    $targetUser = User::factory()->create();
    $empB = Employee::factory()->create([
        'department_id' => $deptB->id,
        'role_id' => $rolePegawai->id,
        'user_id' => $targetUser->id,
        'email' => $targetUser->email,
    ]);

    $response = $this->actingAs($userAdmin)->post("/employees/{$empB->id}/reset-password", [
        'password' => 'hackedpassword123',
    ]);

    $response->assertForbidden();
});

test('pegawai role cannot reset employee password', function () {
    $dept = Department::factory()->create();
    $rolePegawai = Role::factory()->create(['slug' => 'pegawai', 'name' => 'Pegawai']);

    $userPegawai = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $userPegawai->id,
        'role_id' => $rolePegawai->id,
        'department_id' => $dept->id,
    ]);

    $otherUser = User::factory()->create();
    $otherEmp = Employee::factory()->create([
        'user_id' => $otherUser->id,
        'department_id' => $dept->id,
        'role_id' => $rolePegawai->id,
    ]);

    $response = $this->actingAs($userPegawai)->post("/employees/{$otherEmp->id}/reset-password", [
        'password' => 'newpassword123',
    ]);

    $response->assertForbidden();
});

test('reset password requires at least 8 characters', function () {
    $superAdmin = User::factory()->create();
    $employee = Employee::factory()->create([
        'email' => 'test@example.com',
    ]);

    $response = $this->actingAs($superAdmin)->post("/employees/{$employee->id}/reset-password", [
        'password' => 'short',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('user can filter employees by sub-department', function () {
    $user = User::factory()->create();
    $dept = Department::factory()->create();
    $sub1 = SubDepartment::factory()->create(['department_id' => $dept->id, 'name' => 'Sub IT']);
    $sub2 = SubDepartment::factory()->create(['department_id' => $dept->id, 'name' => 'Sub HR']);

    Employee::factory()->create(['name' => 'Pegawai IT', 'department_id' => $dept->id, 'sub_department_id' => $sub1->id]);
    Employee::factory()->create(['name' => 'Pegawai HR', 'department_id' => $dept->id, 'sub_department_id' => $sub2->id]);

    $response = $this->actingAs($user)->get("/employees?sub_department_id={$sub1->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 1)
            ->where('employees.data.0.name', 'Pegawai IT')
            ->where('filters.sub_department_id', $sub1->id)
        );
});

test('user can filter employees by department', function () {
    $user = User::factory()->create();
    $deptA = Department::factory()->create(['name' => 'Bagian A']);
    $deptB = Department::factory()->create(['name' => 'Bagian B']);

    Employee::factory()->create(['name' => 'Pegawai Dept A', 'department_id' => $deptA->id]);
    Employee::factory()->create(['name' => 'Pegawai Dept B', 'department_id' => $deptB->id]);

    $response = $this->actingAs($user)->get("/employees?department_id={$deptA->id}");

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 1)
            ->where('employees.data.0.name', 'Pegawai Dept A')
            ->where('filters.department_id', $deptA->id)
        );
});

test('user can filter employees by multiple departments and multiple sub-departments', function () {
    $user = User::factory()->create();
    $deptA = Department::factory()->create(['name' => 'Bagian A']);
    $deptB = Department::factory()->create(['name' => 'Bagian B']);
    $deptC = Department::factory()->create(['name' => 'Bagian C']);

    $subA1 = SubDepartment::factory()->create(['department_id' => $deptA->id, 'name' => 'Sub A1']);
    $subA2 = SubDepartment::factory()->create(['department_id' => $deptA->id, 'name' => 'Sub A2']);
    $subB1 = SubDepartment::factory()->create(['department_id' => $deptB->id, 'name' => 'Sub B1']);

    Employee::factory()->create(['name' => 'Pegawai A1', 'department_id' => $deptA->id, 'sub_department_id' => $subA1->id]);
    Employee::factory()->create(['name' => 'Pegawai A2', 'department_id' => $deptA->id, 'sub_department_id' => $subA2->id]);
    Employee::factory()->create(['name' => 'Pegawai B1', 'department_id' => $deptB->id, 'sub_department_id' => $subB1->id]);
    Employee::factory()->create(['name' => 'Pegawai C', 'department_id' => $deptC->id]);

    // Test multiple departments
    $responseDept = $this->actingAs($user)->get("/employees?department_ids[]={$deptA->id}&department_ids[]={$deptB->id}");
    $responseDept->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 3)
            ->where('filters.department_ids', [$deptA->id, $deptB->id])
        );

    // Test multiple sub-departments
    $responseSub = $this->actingAs($user)->get("/employees?sub_department_ids[]={$subA1->id}&sub_department_ids[]={$subB1->id}");
    $responseSub->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 2)
            ->where('filters.sub_department_ids', [$subA1->id, $subB1->id])
        );
});

test('user can filter employees by multiple roles and multiple statuses', function () {
    $user = User::factory()->create();
    $roleA = Role::factory()->create(['name' => 'Role A']);
    $roleB = Role::factory()->create(['name' => 'Role B']);
    $roleC = Role::factory()->create(['name' => 'Role C']);

    Employee::factory()->create(['name' => 'Emp A Active', 'role_id' => $roleA->id, 'status' => 'active']);
    Employee::factory()->create(['name' => 'Emp B Inactive', 'role_id' => $roleB->id, 'status' => 'inactive']);
    Employee::factory()->create(['name' => 'Emp C Active', 'role_id' => $roleC->id, 'status' => 'active']);

    // Filter by multiple roles
    $responseRoles = $this->actingAs($user)->get("/employees?role_ids[]={$roleA->id}&role_ids[]={$roleB->id}");
    $responseRoles->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 2)
            ->where('filters.role_ids', [$roleA->id, $roleB->id])
        );

    // Filter by multiple statuses
    $responseStatuses = $this->actingAs($user)->get('/employees?statuses[]=active&statuses[]=inactive');
    $responseStatuses->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 3)
            ->where('filters.statuses', ['active', 'inactive'])
        );
});

test('user can filter employees with custom page size', function () {
    $user = User::factory()->create();
    Employee::factory()->count(15)->create();

    $response = $this->actingAs($user)->get('/employees?per_page=25');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/Index')
            ->has('employees.data', 15)
            ->where('employees.per_page', 25)
            ->where('filters.per_page', 25)
        );
});
