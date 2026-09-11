<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveRoleRequest;
use App\Services\AccesoVistaService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:roles.view')->only(['index']);
        $this->middleware('permission:roles.create')->only(['create', 'store']);
        $this->middleware('permission:roles.edit')->only(['edit', 'update']);
        $this->middleware('permission:roles.delete')->only(['destroy']);
    }

    public function index()
    {
        $roles = Role::withCount('permissions')
            ->orderBy('name')
            ->paginate(20);

        return view('roles.index', compact('roles'));
    }

    public function create()
    {
        app(AccesoVistaService::class)->syncPermissions();
        $permissions = Permission::orderBy('name')->get();

        return view('roles.create', compact('permissions'));
    }

    public function store(SaveRoleRequest $request)
    {
        $validated = $request->validated();

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('roles.index')
            ->with('success', 'Rol creado exitosamente.');
    }

    public function edit(Role $role)
    {
        app(AccesoVistaService::class)->syncPermissions();
        $permissions = Permission::orderBy('name')->get();
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return view('roles.edit', compact('role', 'permissions', 'rolePermissions'));
    }

    public function update(SaveRoleRequest $request, Role $role)
    {
        $validated = $request->validated();

        $role->update([
            'name' => $validated['name'],
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('roles.index')
            ->with('success', 'Rol actualizado exitosamente.');
    }

    public function destroy(Role $role)
    {
        if ($role->name === 'superadmin') {
            return redirect()->route('roles.index')
                ->with('error', 'No se puede eliminar el rol superadmin.');
        }

        if ($role->users()->exists()) {
            return redirect()->route('roles.index')
                ->with('error', 'No se puede eliminar un rol asignado a usuarios.');
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Rol eliminado exitosamente.');
    }
}
