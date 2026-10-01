<?php

namespace App\Services\Bi;

use App\Models\Agencia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AgenciaTerminalCatalogo
{
    public function ultimaActualizacion(): ?string
    {
        $fecha = Agencia::query()->max('updated_at');

        return $fecha ? substr((string) $fecha, 0, 10) : null;
    }

    public function paginar(?string $busqueda): LengthAwarePaginator
    {
        $query = Agencia::query()
            ->select(['id', 'terminal', 'agencia', 'nombre_agencia', 'empresa', 'ciudad', 'estatus'])
            ->whereNotNull('terminal')
            ->where('terminal', '<>', '');

        if ($busqueda !== null && $busqueda !== '') {
            $query->where(function (Builder $agencias) use ($busqueda): void {
                $agencias->where('terminal', 'like', "%{$busqueda}%")
                    ->orWhere('agencia', 'like', "%{$busqueda}%")
                    ->orWhere('nombre_agencia', 'like', "%{$busqueda}%");
            });
        }

        return $query->orderBy('terminal')->orderBy('id')->paginate(25)->withQueryString();
    }
}
