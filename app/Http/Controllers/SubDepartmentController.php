<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubDepartmentRequest;
use App\Models\Department;
use App\Models\SubDepartment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubDepartmentController extends Controller
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
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = is_string($request->query('search')) ? trim($request->query('search')) : null;
        $rawDepartmentIds = $request->query('department_ids', $request->query('department_id', []));
        $departmentIds = $this->parseMultiIds($rawDepartmentIds);
        $sort = $request->query('sort');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = SubDepartment::query()
            ->with('department')
            ->withCount('employees')
            ->when(count($departmentIds) > 0, function ($query) use ($departmentIds) {
                $query->whereIn('department_id', $departmentIds);
            })
            ->when($search, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        if ($sort === 'name') {
            $query->orderBy('name', $direction);
        } elseif ($sort === 'code') {
            $query->orderBy('code', $direction);
        } elseif ($sort === 'department') {
            $query->orderBy(
                Department::select('name')->whereColumn('departments.id', 'sub_departments.department_id'),
                $direction
            );
        } elseif ($sort === 'employees_count') {
            $query->orderBy('employees_count', $direction);
        } else {
            $query->latest('id');
        }

        $perPage = (int) $request->input('per_page', 10);
        if ($perPage < 5 || $perPage > 100) {
            $perPage = 10;
        }

        $subDepartments = $query->paginate($perPage)->withQueryString();

        $departments = Department::orderBy('name')->get(['id', 'name', 'code']);

        return Inertia::render('sub-departments/Index', [
            'subDepartments' => $subDepartments,
            'departments' => $departments,
            'filters' => [
                'search' => $search,
                'department_ids' => $departmentIds,
                'department_id' => count($departmentIds) === 1 ? $departmentIds[0] : null,
                'sort' => $sort,
                'direction' => $sort ? $direction : null,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Get sub-departments by department id for dynamic dropdowns.
     */
    public function byDepartment(Department $department): JsonResponse
    {
        return response()->json(
            $department->subDepartments()->orderBy('name')->get(['id', 'name', 'code'])
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SubDepartmentRequest $request): RedirectResponse
    {
        SubDepartment::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Sub Bagian berhasil ditambahkan.',
        ]);

        return back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SubDepartmentRequest $request, SubDepartment $subDepartment): RedirectResponse
    {
        $subDepartment->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Sub Bagian berhasil diperbarui.',
        ]);

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SubDepartment $subDepartment): RedirectResponse
    {
        if ($subDepartment->employees()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Sub Bagian tidak dapat dihapus karena masih memiliki data pegawai terhubung.',
            ]);

            return back();
        }

        $subDepartment->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Sub Bagian berhasil dihapus.',
        ]);

        return back();
    }
}
