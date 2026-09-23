<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\SubDepartment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard view with statistics and attendance status.
     */
    public function index(Request $request): Response
    {
        $today = Carbon::today()->toDateString();
        $user = $request->user();

        $employee = $user ? $user->getEmployeeRecord() : null;
        if ($employee) {
            $employee->loadMissing(['department', 'subDepartment', 'role']);
        }

        $todayMyAttendance = $employee
            ? Attendance::where('employee_id', $employee->id)->whereDate('date', $today)->first()
            : null;

        $isPegawai = $user && $user->isPegawai();
        $adminDeptId = $user ? $user->getAdminDepartmentId() : null;
        $adminDepartment = $adminDeptId ? Department::find($adminDeptId) : null;
        $monthStats = null;

        if ($isPegawai) {
            $employeeId = $employee ? $employee->id : 0;
            $totalEmployees = 1;
            $totalDepartments = 1;
            $totalSubDepartments = $employee?->sub_department_id ? 1 : 0;

            $todayStatsQuery = Attendance::whereDate('date', $today)
                ->where('employee_id', $employeeId);

            // Monthly stats for regular employee
            $startOfMonth = Carbon::today()->startOfMonth()->toDateString();
            $endOfMonth = Carbon::today()->endOfMonth()->toDateString();

            $monthStatsRaw = Attendance::where('employee_id', $employeeId)
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $hadirMonth = $monthStatsRaw['hadir'] ?? 0;
            $terlambatMonth = $monthStatsRaw['terlambat'] ?? 0;
            $izinMonth = $monthStatsRaw['izin'] ?? 0;
            $sakitMonth = $monthStatsRaw['sakit'] ?? 0;
            $cutiMonth = $monthStatsRaw['cuti'] ?? 0;
            $dinasPagiMonth = $monthStatsRaw['dinas_pagi'] ?? 0;
            $dinasSoreMonth = $monthStatsRaw['dinas_sore'] ?? 0;
            $alpaMonth = $monthStatsRaw['alpa'] ?? 0;

            $monthStats = [
                'month_name' => Carbon::today()->translatedFormat('F Y'),
                'hadir' => $hadirMonth,
                'terlambat' => $terlambatMonth,
                'total_present' => $hadirMonth + $terlambatMonth,
                'izin' => $izinMonth,
                'sakit' => $sakitMonth,
                'cuti' => $cutiMonth,
                'dinas' => $dinasPagiMonth + $dinasSoreMonth,
                'alpa' => $alpaMonth,
                'total_records' => array_sum($monthStatsRaw),
            ];

            $recentAttendances = Attendance::with(['employee.department', 'employee.role'])
                ->where('employee_id', $employeeId)
                ->latest('date')
                ->take(7)
                ->get();
        } else {
            $totalEmployees = $adminDeptId
                ? Employee::where('status', 'active')->where('department_id', $adminDeptId)->count()
                : Employee::where('status', 'active')->count();
            $totalDepartments = $adminDeptId ? 1 : Department::count();
            $totalSubDepartments = $adminDeptId
                ? SubDepartment::where('department_id', $adminDeptId)->count()
                : SubDepartment::count();

            $todayStatsQuery = Attendance::whereDate('date', $today);
            if ($adminDeptId) {
                $todayStatsQuery->whereHas('employee', fn ($q) => $q->where('department_id', $adminDeptId));
            }

            $recentAttendancesQuery = Attendance::with(['employee.department', 'employee.role'])
                ->whereDate('date', $today);
            if ($adminDeptId) {
                $recentAttendancesQuery->whereHas('employee', fn ($q) => $q->where('department_id', $adminDeptId));
            }

            $recentAttendances = $recentAttendancesQuery
                ->latest('updated_at')
                ->take(7)
                ->get();
        }

        $todayStats = $todayStatsQuery
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $roleSlug = 'super-admin';
        if ($isPegawai) {
            $roleSlug = 'pegawai';
        } elseif ($adminDeptId) {
            $roleSlug = 'admin-bagian';
        }

        return Inertia::render('Dashboard', [
            'today' => $today,
            'today_formatted' => Carbon::now()->translatedFormat('l, d F Y'),
            'role' => $roleSlug,
            'myEmployee' => $employee,
            'myAttendance' => $todayMyAttendance,
            'department' => $adminDepartment,
            'summary' => [
                'total_employees' => $totalEmployees,
                'total_departments' => $totalDepartments,
                'total_sub_departments' => $totalSubDepartments,
                'hadir' => $todayStats['hadir'] ?? 0,
                'terlambat' => $todayStats['terlambat'] ?? 0,
                'izin' => $todayStats['izin'] ?? 0,
                'sakit' => $todayStats['sakit'] ?? 0,
                'alpa' => $todayStats['alpa'] ?? 0,
                'dinas_pagi' => $todayStats['dinas_pagi'] ?? 0,
                'dinas_sore' => $todayStats['dinas_sore'] ?? 0,
                'cuti' => $todayStats['cuti'] ?? 0,
            ],
            'monthStats' => $monthStats,
            'recentAttendances' => $recentAttendances,
        ]);
    }
}
