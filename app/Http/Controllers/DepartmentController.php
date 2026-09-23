<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $sort = $request->query('sort');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = Department::query()
            ->withCount(['subDepartments', 'employees'])
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });

        if ($sort === 'name') {
            $query->orderBy('name', $direction);
        } elseif ($sort === 'code') {
            $query->orderBy('code', $direction);
        } elseif ($sort === 'sub_departments_count') {
            $query->orderBy('sub_departments_count', $direction);
        } elseif ($sort === 'employees_count') {
            $query->orderBy('employees_count', $direction);
        } else {
            $query->latest('id');
        }

        $perPage = (int) $request->input('per_page', 10);
        if ($perPage < 5 || $perPage > 100) {
            $perPage = 10;
        }

        $departments = $query->paginate($perPage)->withQueryString();

        return Inertia::render('departments/Index', [
            'departments' => $departments,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $sort ? $direction : null,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(DepartmentRequest $request): RedirectResponse
    {
        Department::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Bagian berhasil ditambahkan.',
        ]);

        return back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Bagian berhasil diperbarui.',
        ]);

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department): RedirectResponse
    {
        if ($department->employees()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Bagian tidak dapat dihapus karena masih memiliki data pegawai terhubung.',
            ]);

            return back();
        }

        $department->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Bagian berhasil dihapus.',
        ]);

        return back();
    }
}
