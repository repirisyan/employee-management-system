<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = is_string($request->query('search')) ? trim($request->query('search')) : null;

        $perPage = (int) $request->input('per_page', 10);
        if ($perPage < 5 || $perPage > 100) {
            $perPage = 10;
        }

        $roles = Role::query()
            ->withCount('employees')
            ->when($search, function ($query, string $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('roles/Index', [
            'roles' => $roles,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RoleRequest $request): RedirectResponse
    {
        Role::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role berhasil ditambahkan.',
        ]);

        return back();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role berhasil diperbarui.',
        ]);

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->employees()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Role tidak dapat dihapus karena masih digunakan oleh data pegawai.',
            ]);

            return back();
        }

        $role->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role berhasil dihapus.',
        ]);

        return back();
    }
}
