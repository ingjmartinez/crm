<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Mail\NuevoUsuarioMail;
use App\Models\User;
use App\ViewPermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:usuarios.view')->only(['index']);
        $this->middleware('permission:usuarios.list')->only(['list']);
        $this->middleware('permission:usuarios.create')->only(['create', 'store']);
        $this->middleware('permission:usuarios.edit')->only(['edit', 'update', 'resetPassword']);
        $this->middleware('permission:usuarios.delete')->only(['destroy']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('usuarios.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $roles = Role::orderBy('name')->get();
        $viewPermissionsByModule = $this->viewPermissionsByModule();

        return view('usuarios.create', compact('roles', 'viewPermissionsByModule'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $plainPassword = '0000';
        $validated['password'] = Hash::make($plainPassword);
        $validated['must_change_password'] = true;

        $user = User::create($validated);

        $user->syncRoles($validated['roles'] ?? []);
        $this->syncViewPermissions($user, $validated['view_permissions'] ?? []);

        // Enviar correo con los datos de acceso
        try {
            Mail::to($user->email)->send(new NuevoUsuarioMail($user, $plainPassword));
        } catch (\Exception $e) {
            return redirect()->route('usuarios.index')
                ->with('success', 'Usuario creado exitosamente.')
                ->with('error', 'No se pudo enviar el correo: '.$e->getMessage());
        }

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado exitosamente. Se envió un correo con los datos de acceso.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function resetPassword(User $usuario)
    {
        $plainPassword = '0000';
        $usuario->password = Hash::make($plainPassword);
        $usuario->must_change_password = true;
        $usuario->save();

        try {
            Mail::to($usuario->email)->send(new NuevoUsuarioMail($usuario, $plainPassword, true));
        } catch (\Exception $e) {
            return redirect()->route('usuarios.index')
                ->with('success', 'Contraseña reseteada correctamente.')
                ->with('error', 'No se pudo enviar el correo: '.$e->getMessage());
        }

        return redirect()->route('usuarios.index')
            ->with('success', 'Contraseña reseteada correctamente. Se envió el correo con la clave temporal 0000.');
    }

    public function edit(User $usuario): View
    {
        $roles = Role::orderBy('name')->get();
        $userRoles = $usuario->roles->pluck('name')->toArray();
        $viewPermissionsByModule = $this->viewPermissionsByModule();
        $userViewPermissions = $usuario->viewPermissions->mapWithKeys(
            fn (Permission $permission): array => [$permission->name => $permission->pivot->allowed ? 'allow' : 'deny']
        )->all();

        return view('usuarios.edit', compact('usuario', 'roles', 'userRoles', 'viewPermissionsByModule', 'userViewPermissions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $usuario): RedirectResponse
    {
        $validated = $request->validated();

        $usuario->name = $validated['name'];
        $usuario->email = $validated['email'];

        if (! empty($validated['password'])) {
            $usuario->password = Hash::make($validated['password']);
        }

        $usuario->save();

        $usuario->syncRoles($validated['roles'] ?? []);

        if (array_key_exists('view_permissions', $validated)) {
            $this->syncViewPermissions($usuario, $validated['view_permissions']);
        }

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario actualizado exitosamente.');
    }

    /** @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, array<string, mixed>>> */
    private function viewPermissionsByModule(): \Illuminate\Support\Collection
    {
        return app(ViewPermissionCatalog::class)->items()
            ->whereNull('role')
            ->unique('permission')
            ->sortBy(['module', 'path'])
            ->groupBy('module');
    }

    /** @param array<int, array{permission: string, access: string}> $entries */
    private function syncViewPermissions(User $user, array $entries): void
    {
        $decisions = collect($entries)->reject(fn (array $entry): bool => $entry['access'] === 'inherit');
        $ids = Permission::query()->whereIn('name', $decisions->pluck('permission')->all())->pluck('id', 'name');
        $sync = $decisions->mapWithKeys(fn (array $entry): array => [
            $ids[$entry['permission']] => ['allowed' => $entry['access'] === 'allow'],
        ])->all();

        $user->viewPermissions()->sync($sync);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $usuario)
    {
        // Evitar que el usuario elimine su propia cuenta
        if ($usuario->id === auth()->id()) {
            return redirect()->route('usuarios.index')
                ->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        $usuario->delete();

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario eliminado exitosamente.');
    }

    /**
     * Get list of users for DataTable.
     */
    public function list(Request $request)
    {
        $query = User::query()->with('roles');

        // Búsqueda
        if ($request->has('search') && $request->search['value']) {
            $search = $request->search['value'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Total de registros
        $totalRecords = User::count();
        $filteredRecords = $query->count();

        // Ordenamiento
        $columns = ['id', 'name', 'email', 'created_at'];
        $orderColumn = $columns[$request->input('order.0.column', 0)] ?? 'id';
        $orderDir = $request->input('order.0.dir', 'desc');

        // Paginación
        $start = $request->input('start', 0);
        $length = $request->input('length', 10);

        $users = $query->orderBy($orderColumn, $orderDir)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name')->implode(', '),
                    'created_at' => $user->created_at?->format('d/m/Y H:i'),
                ];
            });

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $users,
        ]);
    }
}
