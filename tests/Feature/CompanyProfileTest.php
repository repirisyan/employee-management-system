<?php

use App\Models\CompanyProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('super admin can view company profile edit page', function () {
    $superAdmin = User::factory()->create();

    $response = $this->actingAs($superAdmin)->get('/company-profile');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('company-profile/Edit')
            ->has('company')
        );
});

test('super admin can update company name', function () {
    $superAdmin = User::factory()->create();

    $response = $this->actingAs($superAdmin)->post('/company-profile', [
        'name' => 'PT Nusantara Sejahtera',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $company = CompanyProfile::current();
    expect($company->name)->toBe('PT Nusantara Sejahtera');
});

test('super admin can upload company logo and converts to webp', function () {
    Storage::fake('public');

    $superAdmin = User::factory()->create();
    $logoFile = UploadedFile::fake()->image('company_logo.png', 200, 200);

    $response = $this->actingAs($superAdmin)->post('/company-profile', [
        'name' => 'PT Solusi Terpadu',
        'logo' => $logoFile,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $company = CompanyProfile::current();
    expect($company->name)->toBe('PT Solusi Terpadu');
    expect($company->logo)->not->toBeNull();
    expect($company->logo)->toEndWith('.webp');
    expect($company->logo_url)->toContain($company->logo);

    Storage::disk('public')->assertExists($company->logo);
});

test('super admin can remove company logo', function () {
    Storage::fake('public');

    $superAdmin = User::factory()->create();
    $logoFile = UploadedFile::fake()->image('first_logo.png', 200, 200);

    // Initial upload
    $this->actingAs($superAdmin)->post('/company-profile', [
        'name' => 'PT Solusi Terpadu',
        'logo' => $logoFile,
    ]);

    $company = CompanyProfile::current();
    $oldLogo = $company->logo;
    Storage::disk('public')->assertExists($oldLogo);

    // Remove logo
    $response = $this->actingAs($superAdmin)->post('/company-profile', [
        'name' => 'PT Solusi Terpadu',
        'remove_logo' => true,
    ]);

    $response->assertRedirect();
    $company->refresh();
    expect($company->logo)->toBeNull();
    Storage::disk('public')->assertMissing($oldLogo);
});

test('admin bagian is forbidden from accessing company profile', function () {
    $dept = Department::factory()->create();
    $roleAdminBagian = Role::factory()->create(['slug' => 'admin-bagian', 'name' => 'Admin Bagian']);

    $userAdmin = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $userAdmin->id,
        'role_id' => $roleAdminBagian->id,
        'department_id' => $dept->id,
    ]);

    $response = $this->actingAs($userAdmin)->get('/company-profile');
    $response->assertForbidden();

    $postResponse = $this->actingAs($userAdmin)->post('/company-profile', [
        'name' => 'Hacked Company Name',
    ]);
    $postResponse->assertForbidden();
});

test('regular pegawai is forbidden from accessing company profile', function () {
    $dept = Department::factory()->create();
    $rolePegawai = Role::factory()->create(['slug' => 'pegawai', 'name' => 'Pegawai']);

    $userPegawai = User::factory()->create();
    Employee::factory()->create([
        'user_id' => $userPegawai->id,
        'role_id' => $rolePegawai->id,
        'department_id' => $dept->id,
    ]);

    $response = $this->actingAs($userPegawai)->get('/company-profile');
    $response->assertForbidden();

    $postResponse = $this->actingAs($userPegawai)->post('/company-profile', [
        'name' => 'Hacked Company Name',
    ]);
    $postResponse->assertForbidden();
});

test('unauthenticated user is redirected to login', function () {
    $response = $this->get('/company-profile');
    $response->assertRedirect('/login');
});

test('company name is required and validated', function () {
    $superAdmin = User::factory()->create();

    $response = $this->actingAs($superAdmin)->post('/company-profile', [
        'name' => '',
    ]);

    $response->assertSessionHasErrors(['name']);
});
