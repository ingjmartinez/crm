<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\NuevoUsuarioMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
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
    public function create()
    {
        return view('usuarios.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $plainPassword = $validated['password'];
        $validated['password'] = Hash::make($plainPassword);

        $user = User::create($validated);

        // Enviar correo con los datos de acceso
        try {
            Mail::to($user->email)->send(new NuevoUsuarioMail($user, $plainPassword));
        } catch (\Exception $e) {
            return redirect()->route('usuarios.index')
                ->with('success', 'Usuario creado exitosamente.')
                ->with('error', 'No se pudo enviar el correo: ' . $e->getMessage());
        }

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario creado exitosamente. Se envió un correo con los datos de acceso.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $usuario)
    {
        return view('usuarios.edit', compact('usuario'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $usuario)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $usuario->id,
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $usuario->name = $validated['name'];
        $usuario->email = $validated['email'];

        if (!empty($validated['password'])) {
            $usuario->password = Hash::make($validated['password']);
        }

        $usuario->save();

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario actualizado exitosamente.');
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
        $query = User::query();

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
