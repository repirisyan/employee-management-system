<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<Employee, $this>
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Get the attached employee record (either via user_id or email).
     */
    public function getEmployeeRecord(): ?Employee
    {
        return $this->employee ?? Employee::where('user_id', $this->id)->orWhere('email', $this->email)->first();
    }

    /**
     * Determine if the user is a Super Admin.
     */
    public function isSuperAdmin(): bool
    {
        $employee = $this->getEmployeeRecord();

        if (! $employee) {
            return true;
        }

        return $employee->role?->slug === 'super-admin';
    }

    /**
     * Determine if the user is an Admin Bagian.
     */
    public function isAdminBagian(): bool
    {
        $employee = $this->getEmployeeRecord();

        return $employee && $employee->role?->slug === 'admin-bagian';
    }

    /**
     * Determine if the user can manage employees (Super Admin or Admin Bagian).
     */
    public function canManageEmployees(): bool
    {
        return $this->isSuperAdmin() || $this->isAdminBagian();
    }

    /**
     * Determine if the user is a regular Pegawai.
     */
    public function isPegawai(): bool
    {
        return ! $this->canManageEmployees();
    }

    /**
     * Get the department ID for Admin Bagian (returns null for Super Admin to access all).
     */
    public function getAdminDepartmentId(): ?int
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        $employee = $this->getEmployeeRecord();

        return $employee?->department_id;
    }
}
