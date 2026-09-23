<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Role;
use App\Models\SubDepartment;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    /**
     * Parse single or multiple IDs from query into an array of integers.
     *
     * @return int[]
     */
    private function parseMultiIds(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('intval', $raw)));
        }

        if (is_string($raw) && strlen(trim($raw)) > 0) {
            return array_values(array_filter(array_map('intval', explode(',', $raw))));
        }

        if (is_numeric($raw)) {
            return [(int) $raw];
        }

        return [];
    }

    /**
     * Parse single or multiple strings from query into an array of strings.
     *
     * @return string[]
     */
    private function parseMultiStrings(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw), fn ($s) => strlen(trim($s)) > 0));
        }

        if (is_string($raw) && strlen(trim($raw)) > 0) {
            return array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($s) => strlen($s) > 0));
        }

        return [];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (! $user || ! $user->canManageEmployees()) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk mengelola data pegawai.');
        }

        $adminDeptId = $user->getAdminDepartmentId();

        $search = is_string($request->query('search')) ? trim($request->query('search')) : null;

        $rawDepartmentIds = $request->query('department_ids', $request->query('department_id', []));
        $departmentIds = $this->parseMultiIds($rawDepartmentIds);
        if ($adminDeptId) {
            $departmentIds = [$adminDeptId];
        }

        $rawSubDepartmentIds = $request->query('sub_department_ids', $request->query('sub_department_id', []));
        $subDepartmentIds = $this->parseMultiIds($rawSubDepartmentIds);

        $rawRoleIds = $request->query('role_ids', $request->query('role_id', []));
        $roleIds = $this->parseMultiIds($rawRoleIds);

        $rawStatuses = $request->query('statuses', $request->query('status', []));
        $statuses = $this->parseMultiStrings($rawStatuses);

        $sort = $request->query('sort');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = Employee::query()
            ->with(['department:id,name,code', 'subDepartment:id,name,code', 'role:id,name', 'user:id,name,email'])
            ->when($search, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nip', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(! empty($departmentIds), fn ($query) => $query->whereIn('department_id', $departmentIds))
            ->when(! empty($subDepartmentIds), fn ($query) => $query->whereIn('sub_department_id', $subDepartmentIds))
            ->when(! empty($roleIds), fn ($query) => $query->whereIn('role_id', $roleIds))
            ->when(! empty($statuses), fn ($query) => $query->whereIn('status', $statuses));

        if ($sort === 'name') {
            $query->orderBy('name', $direction);
        } elseif ($sort === 'nip') {
            $query->orderBy('nip', $direction);
        } elseif ($sort === 'department') {
            $query->orderBy(
                Department::select('name')->whereColumn('departments.id', 'employees.department_id'),
                $direction
            );
        } elseif ($sort === 'role') {
            $query->orderBy(
                Role::select('name')->whereColumn('roles.id', 'employees.role_id'),
                $direction
            );
        } elseif ($sort === 'status') {
            $query->orderBy('status', $direction);
        } else {
            $query->latest('id');
        }

        $perPage = (int) $request->input('per_page', 10);
        if ($perPage < 5 || $perPage > 100) {
            $perPage = 10;
        }

        $employees = $query->paginate($perPage)->withQueryString();

        if ($adminDeptId) {
            $departments = Department::where('id', $adminDeptId)->get(['id', 'name', 'code']);
            $subDepartments = SubDepartment::where('department_id', $adminDeptId)->orderBy('name')->get(['id', 'department_id', 'name', 'code']);
            $statsQuery = Employee::where('department_id', $adminDeptId);
        } else {
            $departments = Department::orderBy('name')->get(['id', 'name', 'code']);
            $subDepartments = SubDepartment::orderBy('name')->get(['id', 'department_id', 'name', 'code']);
            $statsQuery = Employee::query();
        }

        $roles = Role::orderBy('name')->get(['id', 'name']);

        return Inertia::render('employees/Index', [
            'employees' => $employees,
            'departments' => $departments,
            'subDepartments' => $subDepartments,
            'roles' => $roles,
            'stats' => [
                'total' => (clone $statsQuery)->count(),
                'active' => (clone $statsQuery)->where('status', 'active')->count(),
                'inactive' => (clone $statsQuery)->where('status', 'inactive')->count(),
                'with_user' => (clone $statsQuery)->whereNotNull('user_id')->count(),
            ],
            'filters' => [
                'search' => $search,
                'department_ids' => $departmentIds,
                'sub_department_ids' => $subDepartmentIds,
                'department_id' => count($departmentIds) === 1 ? $departmentIds[0] : null,
                'sub_department_id' => count($subDepartmentIds) === 1 ? $subDepartmentIds[0] : null,
                'role_ids' => $roleIds,
                'role_id' => count($roleIds) === 1 ? $roleIds[0] : null,
                'statuses' => $statuses,
                'status' => count($statuses) === 1 ? $statuses[0] : null,
                'sort' => $sort,
                'direction' => $sort ? $direction : null,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EmployeeRequest $request, ImageService $imageService): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->canManageEmployees()) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk mengelola data pegawai.');
        }

        $validated = $request->validated();
        $adminDeptId = $user->getAdminDepartmentId();

        if ($adminDeptId) {
            if ((int) $validated['department_id'] !== $adminDeptId) {
                abort(403, 'Anda hanya dapat menambahkan pegawai untuk bagian Anda.');
            }
            if (! empty($validated['sub_department_id'])) {
                $sub = SubDepartment::where('id', $validated['sub_department_id'])->first();
                if ($sub && $sub->department_id !== $adminDeptId) {
                    abort(403, 'Sub Bagian tidak valid untuk bagian Anda.');
                }
            }
        }

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $imageService->convertToWebp($request->file('avatar'));
        }

        DB::transaction(function () use ($validated, $avatarPath) {
            $userId = $validated['user_id'] ?? null;

            if (! empty($validated['create_user_account']) && ! empty($validated['email'])) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['user_password'] ?? 'password'),
                    'email_verified_at' => now(),
                ]);
                $userId = $user->id;
            }

            Employee::create([
                'user_id' => $userId,
                'nip' => $validated['nip'],
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'role_id' => $validated['role_id'],
                'department_id' => $validated['department_id'],
                'sub_department_id' => $validated['sub_department_id'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'address' => $validated['address'] ?? null,
                'avatar' => $avatarPath,
                'status' => $validated['status'] ?? 'active',
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data pegawai berhasil ditambahkan.',
        ]);

        return back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EmployeeRequest $request, Employee $employee, ImageService $imageService): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->canManageEmployees()) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk mengelola data pegawai.');
        }

        $adminDeptId = $user->getAdminDepartmentId();
        if ($adminDeptId) {
            if ($employee->department_id !== $adminDeptId) {
                abort(403, 'Anda hanya dapat mengedit pegawai dari bagian Anda.');
            }
            if ((int) $request->input('department_id') !== $adminDeptId) {
                abort(403, 'Anda tidak dapat memindahkan pegawai ke bagian lain.');
            }
        }

        $validated = $request->validated();

        $avatarPath = $employee->avatar;
        if ($request->boolean('remove_avatar')) {
            $imageService->deleteAvatar($employee->avatar);
            $avatarPath = null;
        } elseif ($request->hasFile('avatar')) {
            $imageService->deleteAvatar($employee->avatar);
            $avatarPath = $imageService->convertToWebp($request->file('avatar'));
        }

        DB::transaction(function () use ($validated, $employee, $avatarPath) {
            $employee->update([
                'nip' => $validated['nip'],
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'role_id' => $validated['role_id'],
                'department_id' => $validated['department_id'],
                'sub_department_id' => $validated['sub_department_id'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'address' => $validated['address'] ?? null,
                'avatar' => $avatarPath,
                'status' => $validated['status'] ?? 'active',
            ]);

            if ($employee->user && ! empty($validated['email'])) {
                $employee->user->update([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data pegawai berhasil diperbarui.',
        ]);

        return back();
    }

    /**
     * Reset password for an employee's user account.
     */
    public function resetPassword(Request $request, Employee $employee): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->canManageEmployees()) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk mengelola data pegawai.');
        }

        $adminDeptId = $user->getAdminDepartmentId();
        if ($adminDeptId && $employee->department_id !== $adminDeptId) {
            abort(403, 'Anda hanya dapat mereset kata sandi pegawai dari bagian Anda.');
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8'],
        ]);

        if (! $employee->user_id && empty($employee->email)) {
            throw ValidationException::withMessages([
                'password' => 'Pegawai belum memiliki alamat email untuk akun login pengguna.',
            ]);
        }

        DB::transaction(function () use ($employee, $validated) {
            $user = $employee->user;

            if ($user) {
                $user->update([
                    'password' => Hash::make($validated['password']),
                ]);
            } else {
                $accountUser = User::where('email', $employee->email)->first();
                if ($accountUser) {
                    $accountUser->update([
                        'password' => Hash::make($validated['password']),
                    ]);
                    $employee->update(['user_id' => $accountUser->id]);
                } else {
                    $accountUser = User::create([
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'password' => Hash::make($validated['password']),
                        'email_verified_at' => now(),
                    ]);
                    $employee->update(['user_id' => $accountUser->id]);
                }
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Kata sandi untuk {$employee->name} berhasil direset.",
        ]);

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Employee $employee, ImageService $imageService): RedirectResponse
    {
        $user = $request->user();
        if (! $user || ! $user->canManageEmployees()) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin untuk mengelola data pegawai.');
        }

        $adminDeptId = $user->getAdminDepartmentId();
        if ($adminDeptId && $employee->department_id !== $adminDeptId) {
            abort(403, 'Anda hanya dapat menghapus pegawai dari bagian Anda.');
        }

        if ($employee->avatar) {
            $imageService->deleteAvatar($employee->avatar);
        }

        $employee->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data pegawai berhasil dihapus.',
        ]);

        return back();
    }
}
