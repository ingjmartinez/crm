<?php

namespace App\Http\Controllers;

use App\Models\Agencia;
use App\Models\CoordinadorOperador;
use Illuminate\Http\Request;

class CoordinadorOperadorController extends Controller
{
    public function index()
    {
        $registros = CoordinadorOperador::with('agencias:id,agencia,nombre_agencia,terminal')
            ->withCount('agencias')
            ->orderByDesc('id')
            ->paginate(15);

        $agencias = Agencia::select('id', 'agencia', 'nombre_agencia')
            ->addSelect('terminal')
            ->orderBy('agencia')
            ->get();

        return view('coordinador_operador.index', compact('registros', 'agencias'));
    }

    public function create()
    {
        return view('coordinador_operador.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:150'],
            'cedula' => ['required', 'regex:/^\d{11}$/', 'unique:coordinador_operador,cedula'],
            'telefono' => ['required', 'regex:/^\d{10}$/'],
            'puesto' => ['required', 'in:coordinador,operador'],
        ], [
            'cedula.regex' => 'La cédula debe contener exactamente 11 dígitos numéricos.',
            'telefono.required' => 'Campo de 10 Digitos obligatorios',
            'telefono.regex' => 'Campo de 10 Digitos obligatorios',
            'puesto.in' => 'El puesto debe ser coordinador u operador.',
        ]);

        CoordinadorOperador::create($validated);

        return redirect()->route('coordinador-operador.index')
            ->with('success', 'Registro creado correctamente.');
    }

    public function edit(CoordinadorOperador $coordinador_operador)
    {
        return view('coordinador_operador.edit', [
            'registro' => $coordinador_operador,
        ]);
    }

    public function update(Request $request, CoordinadorOperador $coordinador_operador)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:150'],
            'cedula' => ['required', 'regex:/^\d{11}$/', 'unique:coordinador_operador,cedula,' . $coordinador_operador->id],
            'telefono' => ['required', 'regex:/^\d{10}$/'],
            'puesto' => ['required', 'in:coordinador,operador'],
        ], [
            'cedula.regex' => 'La cédula debe contener exactamente 11 dígitos numéricos.',
            'telefono.required' => 'Campo de 10 Digitos obligatorios',
            'telefono.regex' => 'Campo de 10 Digitos obligatorios',
            'puesto.in' => 'El puesto debe ser coordinador u operador.',
        ]);

        $coordinador_operador->update($validated);

        return redirect()->route('coordinador-operador.index')
            ->with('success', 'Registro actualizado correctamente.');
    }

    public function destroy(CoordinadorOperador $coordinador_operador)
    {
        $coordinador_operador->delete();

        return redirect()->route('coordinador-operador.index')
            ->with('success', 'Registro eliminado correctamente.');
    }

    public function asignarAgencias(Request $request, CoordinadorOperador $coordinador_operador)
    {
        $validated = $request->validate([
            'agencias' => ['nullable', 'array'],
            'agencias.*' => ['integer', 'exists:agencias,id'],
        ]);

        $coordinador_operador->agencias()->sync($validated['agencias'] ?? []);

        return redirect()->route('coordinador-operador.index')
            ->with('success', 'Agencias asignadas correctamente.');
    }
}
