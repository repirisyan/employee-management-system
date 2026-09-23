<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceRequest;
use App\Models\Attendance;
use App\Models\CompanyProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\SubDepartment;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
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
     * Display a listing of attendance records and daily recap.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $adminDeptId = $user ? $user->getAdminDepartmentId() : null;
        $employee = $user ? $user->getEmployeeRecord() : null;
        $isPegawai = $user && $user->isPegawai();

        $startDate = $request->query('start_date', $request->query('date', Carbon::today()->toDateString()));
        $endDate = $request->query('end_date', $request->query('date', Carbon::today()->toDateString()));

        if ($startDate && $endDate && $startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $rawDepartmentIds = $request->query('department_ids', $request->query('department_id', []));
        $departmentIds = $this->parseMultiIds($rawDepartmentIds);
        if ($adminDeptId) {
            $departmentIds = [$adminDeptId];
        }

        $rawSubDepartmentIds = $request->query('sub_department_ids', $request->query('sub_department_id', []));
        $subDepartmentIds = $this->parseMultiIds($rawSubDepartmentIds);

        $rawStatuses = $request->query('statuses', $request->query('status', []));
        $statuses = $this->parseMultiStrings($rawStatuses);

        $search = $request->query('search');
        $sort = $request->query('sort');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = Attendance::query()
            ->with([
                'employee:id,nip,name,department_id,sub_department_id,role_id',
                'employee.department:id,name,code',
                'employee.subDepartment:id,name,code',
                'employee.role:id,name',
            ])
            ->when($startDate, fn ($q) => $q->whereDate('date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('date', '<=', $endDate));

        if ($isPegawai) {
            $query->where('employee_id', $employee?->id ?? 0);
        } else {
            $query
                ->when(! empty($departmentIds), function ($query) use ($departmentIds) {
                    $query->whereHas('employee', fn ($q) => $q->whereIn('department_id', $departmentIds));
                })
                ->when(! empty($subDepartmentIds), function ($query) use ($subDepartmentIds) {
                    $query->whereHas('employee', fn ($q) => $q->whereIn('sub_department_id', $subDepartmentIds));
                })
                ->when($search, function ($query, $search) {
                    $query->whereHas('employee', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('nip', 'like', "%{$search}%");
                    });
                });
        }

        $query->when(! empty($statuses), fn ($query) => $query->whereIn('status', $statuses));

        if ($sort === 'employee') {
            $query->orderBy(
                Employee::select('name')->whereColumn('employees.id', 'attendances.employee_id'),
                $direction
            );
        } elseif ($sort === 'department') {
            $query->orderBy(
                Department::select('name')
                    ->join('employees', 'employees.department_id', '=', 'departments.id')
                    ->whereColumn('employees.id', 'attendances.employee_id'),
                $direction
            );
        } elseif ($sort === 'date') {
            $query->orderBy('date', $direction);
        } elseif ($sort === 'check_in') {
            $query->orderBy('check_in', $direction);
        } elseif ($sort === 'check_out') {
            $query->orderBy('check_out', $direction);
        } elseif ($sort === 'status') {
            $query->orderBy('status', $direction);
        } else {
            $query->latest('date')->latest('id');
        }

        $perPage = (int) $request->input('per_page', 15);
        if ($perPage < 5 || $perPage > 100) {
            $perPage = 15;
        }

        $attendances = $query->paginate($perPage)->withQueryString();

        // Calculate statistics for the selected date range
        $daysCount = ($startDate && $endDate)
            ? Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1
            : 1;

        if ($isPegawai) {
            $totalEmployees = 1;
            $dailyStatsQuery = Attendance::query()
                ->when($startDate, fn ($q) => $q->whereDate('date', '>=', $startDate))
                ->when($endDate, fn ($q) => $q->whereDate('date', '<=', $endDate))
                ->where('employee_id', $employee?->id ?? 0);
        } else {
            $totalEmployees = $adminDeptId
                ? Employee::where('status', 'active')->where('department_id', $adminDeptId)->count()
                : Employee::where('status', 'active')->count();

            $dailyStatsQuery = Attendance::query()
                ->when($startDate, fn ($q) => $q->whereDate('date', '>=', $startDate))
                ->when($endDate, fn ($q) => $q->whereDate('date', '<=', $endDate));
            if ($adminDeptId) {
                $dailyStatsQuery->whereHas('employee', fn ($q) => $q->where('department_id', $adminDeptId));
            }
        }

        $dailyStats = $dailyStatsQuery
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $expectedAttendanceRecords = (int) ($totalEmployees * $daysCount);

        $stats = [
            'total_employees' => $totalEmployees,
            'days_count' => $daysCount,
            'expected_records' => $expectedAttendanceRecords,
            'hadir' => $dailyStats['hadir'] ?? 0,
            'terlambat' => $dailyStats['terlambat'] ?? 0,
            'izin' => $dailyStats['izin'] ?? 0,
            'sakit' => $dailyStats['sakit'] ?? 0,
            'alpa' => $dailyStats['alpa'] ?? 0,
            'dinas_pagi' => $dailyStats['dinas_pagi'] ?? 0,
            'dinas_sore' => $dailyStats['dinas_sore'] ?? 0,
            'cuti' => $dailyStats['cuti'] ?? 0,
            'recorded' => array_sum($dailyStats),
            'unrecorded' => max(0, $expectedAttendanceRecords - array_sum($dailyStats)),
        ];

        if ($isPegawai) {
            $departments = [];
            $subDepartments = [];
            $employees = $employee ? [$employee] : [];
        } elseif ($adminDeptId) {
            $departments = Department::where('id', $adminDeptId)->get(['id', 'name', 'code']);
            $subDepartments = SubDepartment::where('department_id', $adminDeptId)->orderBy('name')->get(['id', 'department_id', 'name', 'code']);
            $employees = Employee::where('status', 'active')->where('department_id', $adminDeptId)->orderBy('name')->get(['id', 'nip', 'name', 'department_id']);
        } else {
            $departments = Department::orderBy('name')->get(['id', 'name', 'code']);
            $subDepartments = SubDepartment::orderBy('name')->get(['id', 'department_id', 'name', 'code']);
            $employees = Employee::where('status', 'active')->orderBy('name')->get(['id', 'nip', 'name', 'department_id']);
        }

        return Inertia::render('attendances/Index', [
            'attendances' => $attendances,
            'departments' => $departments,
            'subDepartments' => $subDepartments,
            'employees' => $employees,
            'selectedDate' => $startDate === $endDate ? $startDate : Carbon::today()->toDateString(),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'stats' => $stats,
            'filters' => [
                'date' => $startDate === $endDate ? $startDate : null,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'department_ids' => $departmentIds,
                'sub_department_ids' => $subDepartmentIds,
                'department_id' => count($departmentIds) === 1 ? $departmentIds[0] : null,
                'sub_department_id' => count($subDepartmentIds) === 1 ? $subDepartmentIds[0] : null,
                'statuses' => $statuses,
                'status' => count($statuses) === 1 ? $statuses[0] : null,
                'search' => $search,
                'sort' => $sort,
                'direction' => $sort ? $direction : null,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Store or update attendance record manually.
     */
    public function store(AttendanceRequest $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->isPegawai()) {
            abort(403, 'Akses ditolak. Pegawai hanya dapat melakukan presensi mandiri.');
        }

        $adminDeptId = $user ? $user->getAdminDepartmentId() : null;
        $validated = $request->validated();

        if ($adminDeptId) {
            $targetEmployee = Employee::find($validated['employee_id']);
            if (! $targetEmployee || $targetEmployee->department_id !== $adminDeptId) {
                abort(403, 'Anda hanya dapat mengelola data kehadiran pegawai dari bagian Anda.');
            }
        }

        $id = $request->input('id') ?? $request->input('attendance_id');
        if ($id) {
            $existing = Attendance::find($id);
            if ($existing) {
                if ($adminDeptId && $existing->employee->department_id !== $adminDeptId) {
                    abort(403, 'Anda hanya dapat mengelola data kehadiran pegawai dari bagian Anda.');
                }

                $existing->update([
                    'employee_id' => $validated['employee_id'],
                    'date' => $validated['date'],
                    'check_in' => $validated['check_in'] ?? null,
                    'check_in_latitude' => $validated['check_in_latitude'] ?? $existing->check_in_latitude,
                    'check_in_longitude' => $validated['check_in_longitude'] ?? $existing->check_in_longitude,
                    'check_out' => $validated['check_out'] ?? null,
                    'check_out_latitude' => $validated['check_out_latitude'] ?? $existing->check_out_latitude,
                    'check_out_longitude' => $validated['check_out_longitude'] ?? $existing->check_out_longitude,
                    'status' => $validated['status'],
                    'notes' => $validated['notes'] ?? null,
                ]);

                Inertia::flash('toast', [
                    'type' => 'success',
                    'message' => 'Data kehadiran berhasil diperbarui.',
                ]);

                return back();
            }
        }

        Attendance::updateOrCreate(
            [
                'employee_id' => $validated['employee_id'],
                'date' => $validated['date'],
            ],
            [
                'check_in' => $validated['check_in'] ?? null,
                'check_in_latitude' => $validated['check_in_latitude'] ?? null,
                'check_in_longitude' => $validated['check_in_longitude'] ?? null,
                'check_out' => $validated['check_out'] ?? null,
                'check_out_latitude' => $validated['check_out_latitude'] ?? null,
                'check_out_longitude' => $validated['check_out_longitude'] ?? null,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data kehadiran berhasil disimpan.',
        ]);

        return back();
    }

    /**
     * Update attendance record.
     */
    public function update(AttendanceRequest $request, Attendance $attendance): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->isPegawai()) {
            abort(403, 'Akses ditolak. Pegawai hanya dapat melakukan presensi mandiri.');
        }

        $adminDeptId = $user ? $user->getAdminDepartmentId() : null;
        $validated = $request->validated();

        if ($adminDeptId) {
            if ($attendance->employee->department_id !== $adminDeptId) {
                abort(403, 'Anda hanya dapat mengelola data kehadiran pegawai dari bagian Anda.');
            }
            $targetEmployee = Employee::find($validated['employee_id']);
            if (! $targetEmployee || $targetEmployee->department_id !== $adminDeptId) {
                abort(403, 'Anda hanya dapat mengelola data kehadiran pegawai dari bagian Anda.');
            }
        }

        $attendance->update([
            'employee_id' => $validated['employee_id'],
            'date' => $validated['date'],
            'check_in' => $validated['check_in'] ?? null,
            'check_in_latitude' => $validated['check_in_latitude'] ?? $attendance->check_in_latitude,
            'check_in_longitude' => $validated['check_in_longitude'] ?? $attendance->check_in_longitude,
            'check_out' => $validated['check_out'] ?? null,
            'check_out_latitude' => $validated['check_out_latitude'] ?? $attendance->check_out_latitude,
            'check_out_longitude' => $validated['check_out_longitude'] ?? $attendance->check_out_longitude,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data kehadiran berhasil diperbarui.',
        ]);

        return back();
    }

    /**
     * Remove attendance record.
     */
    public function destroy(Request $request, Attendance $attendance): RedirectResponse
    {
        $user = $request->user();
        if ($user && $user->isPegawai()) {
            abort(403, 'Akses ditolak. Pegawai hanya dapat melakukan presensi mandiri.');
        }

        $adminDeptId = $user ? $user->getAdminDepartmentId() : null;

        if ($adminDeptId && $attendance->employee->department_id !== $adminDeptId) {
            abort(403, 'Anda hanya dapat menghapus data kehadiran pegawai dari bagian Anda.');
        }

        $attendance->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data kehadiran berhasil dihapus.',
        ]);

        return back();
    }

    /**
     * Check-in action for the authenticated user's employee.
     */
    public function checkIn(Request $request): RedirectResponse
    {
        if (! $request->filled('latitude') || ! $request->filled('longitude')) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'GPS harus aktif untuk melakukan presensi masuk. Harap aktifkan lokasi pada perangkat Anda.',
            ]);

            return back()->withErrors([
                'latitude' => 'Lokasi GPS aktif diperlukan untuk melakukan presensi masuk.',
                'longitude' => 'Lokasi GPS aktif diperlukan untuk melakukan presensi masuk.',
            ]);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'latitude.required' => 'Lokasi GPS aktif diperlukan untuk melakukan presensi masuk.',
            'longitude.required' => 'Lokasi GPS aktif diperlukan untuk melakukan presensi masuk.',
            'latitude.numeric' => 'Format koordinat latitude tidak valid.',
            'longitude.numeric' => 'Format koordinat longitude tidak valid.',
            'latitude.between' => 'Koordinat latitude di luar rentang yang valid (-90 s/d 90).',
            'longitude.between' => 'Koordinat longitude di luar rentang yang valid (-180 s/d 180).',
        ]);

        $user = $request->user();
        $employee = $user->employee ?? Employee::where('email', $user->email)->first();

        if (! $employee) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Akun Anda belum terhubung dengan data pegawai.',
            ]);

            return back();
        }

        $today = Carbon::today()->toDateString();
        $now = Carbon::now();

        $existing = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if ($existing && $existing->check_in) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Anda sudah melakukan presensi masuk hari ini.',
            ]);

            return back();
        }

        $company = CompanyProfile::current();
        $workStart = $company->work_start_time ? substr($company->work_start_time, 0, 5) : '08:00';
        $tolerance = (int) ($company->late_tolerance_minutes ?? 0);

        // Batas waktu keterlambatan dihitung dari jam masuk + toleransi
        $lateThreshold = Carbon::createFromTimeString($workStart)->addMinutes($tolerance)->format('H:i');
        $isLate = $now->format('H:i') > $lateThreshold;
        $status = $isLate ? 'terlambat' : 'hadir';

        Attendance::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'date' => $today,
            ],
            [
                'check_in' => $now->format('H:i:s'),
                'check_in_latitude' => $validated['latitude'],
                'check_in_longitude' => $validated['longitude'],
                'status' => $status,
                'notes' => $isLate ? "Presensi masuk lewat pukul {$lateThreshold}" : null,
            ]
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Presensi masuk berhasil dicatat ('.$now->format('H:i').') dengan koordinat GPS.',
        ]);

        return back();
    }

    /**
     * Check-out action for the authenticated user's employee.
     */
    public function checkOut(Request $request): RedirectResponse
    {
        if (! $request->filled('latitude') || ! $request->filled('longitude')) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'GPS harus aktif untuk melakukan presensi pulang. Harap aktifkan lokasi pada perangkat Anda.',
            ]);

            return back()->withErrors([
                'latitude' => 'Lokasi GPS aktif diperlukan untuk melakukan presensi pulang.',
                'longitude' => 'Lokasi GPS aktif diperlukan untuk melakukan presensi pulang.',
            ]);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ], [
            'latitude.required' => 'Lokasi GPS aktif diperlukan untuk melakukan presensi pulang.',
            'longitude.required' => 'Lokasi GPS aktif diperlukan untuk melakukan presensi pulang.',
            'latitude.numeric' => 'Format koordinat latitude tidak valid.',
            'longitude.numeric' => 'Format koordinat longitude tidak valid.',
            'latitude.between' => 'Koordinat latitude di luar rentang yang valid (-90 s/d 90).',
            'longitude.between' => 'Koordinat longitude di luar rentang yang valid (-180 s/d 180).',
        ]);

        $user = $request->user();
        $employee = $user->employee ?? Employee::where('email', $user->email)->first();

        if (! $employee) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Akun Anda belum terhubung dengan data pegawai.',
            ]);

            return back();
        }

        $today = Carbon::today()->toDateString();
        $now = Carbon::now();

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $today)
            ->first();

        if (! $attendance || ! $attendance->check_in) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Anda belum melakukan presensi masuk hari ini.',
            ]);

            return back();
        }

        if ($attendance->check_out) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Anda sudah melakukan presensi pulang hari ini.',
            ]);

            return back();
        }

        $company = CompanyProfile::current();
        $workEnd = $company->work_end_time ? substr($company->work_end_time, 0, 5) : '17:00';

        if ($now->format('H:i') < $workEnd) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Presensi pulang belum dibuka. Anda baru dapat melakukan presensi pulang mulai pukul '.$workEnd.' WIB.',
            ]);

            return back()->withErrors([
                'check_out' => 'Presensi pulang baru dapat dilakukan mulai pukul '.$workEnd.' WIB.',
            ]);
        }

        $attendance->update([
            'check_out' => $now->format('H:i:s'),
            'check_out_latitude' => $validated['latitude'],
            'check_out_longitude' => $validated['longitude'],
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Presensi pulang berhasil dicatat ('.$now->format('H:i').') dengan koordinat GPS.',
        ]);

        return back();
    }
}
