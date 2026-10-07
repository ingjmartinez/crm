<?php

namespace App;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ViewPermissionCatalog
{
    /** @return Collection<int, array{path: string, name: string, module: string, permission: string, role: ?string}> */
    public function items(): Collection
    {
        $modules = collect(config('module_hubs', []))->flatMap(function (array $hub, string $module): Collection {
            return collect($hub['items'] ?? [])->map(fn (array $item): array => $this->entry($module, $item));
        });

        $legacy = collect(config('view_permissions', []))->flatMap(function (array $pages, string $module): Collection {
            return collect($pages)->map(fn (string $permission, string $path): array => [
                'path' => $path,
                'name' => ucwords(str_replace(['-', '_', '/'], ' ', trim($path, '/'))),
                'module' => $module,
                'permission' => $permission,
                'role' => null,
            ]);
        });

        return $modules
            ->concat(collect(config('recursos_humanos', []))->map(fn (array $item): array => $this->entry('recursos_humanos', $item)))
            ->concat(collect(config('reportes', []))->map(fn (array $item): array => $this->entry('reportes', $item)))
            ->concat($legacy)
            ->values();
    }

    /** @param array<string, mixed> $item */
    public function permission(string $module, array $item): string
    {
        if (! empty($item['permission'])) {
            return (string) $item['permission'];
        }

        $path = '/'.ltrim((string) parse_url((string) ($item['url'] ?? ''), PHP_URL_PATH), '/');
        $existing = [
            '/comercial/resumen' => 'comercial.resumen.view',
            '/comercial/agencia-plan' => 'comercial.agencia_plan.view',
            '/comercial/ventas-producto' => 'comercial.ventas_producto.view',
            '/comercial/gestion-usuarios' => 'comercial.gestion_usuarios.view',
            '/contabilidad/inicio' => 'contabilidad.inicio.view',
            '/contabilidad/reportes/estado-resultado' => 'contabilidad.estado_resultado.view',
            '/contabilidad/reportes/flujo-ruta' => 'contabilidad.flujo_ruta.view',
            '/contabilidad/centro-costo' => 'contabilidad.centro_costo.view',
            '/contabilidad/movimiento-mayor' => 'contabilidad.movimiento_mayor.view',
            '/operaciones/panel' => 'operaciones.panel.view',
            '/operaciones/gestion' => 'operaciones.gestion.view',
            '/operaciones/ruta' => 'operaciones.ruta.view',
            '/incentivos/procesar' => 'incentivos.procesar.view',
            '/incentivos/gestion' => 'incentivos.gestion.view',
            '/incentivos/empleados' => 'incentivos.empleados.view',
            '/incentivos/reporte-pagos' => 'incentivos.reporte_pagos.view',
            '/incentivos/reporte-nuevo-incentivo-view' => 'incentivos.reporte_nuevo.view',
            '/incentivos/reporte-nuevo-incentivo-v2-view' => 'incentivos.reporte_nuevo_v2.view',
            '/incentivos/reporte-nuevo-incentivo-v3-view' => 'incentivos.reporte_nuevo_v3.view',
            '/incentivos/reporte-nuevo-incentivo-v4-view' => 'incentivos.reporte_nuevo_v4.view',
            '/agencias' => 'agencias.view',
            '/agencias-incumplimientos-horario' => 'agencias.incumplimientos_horario.view',
            '/agencias/asistencia-comparativa' => 'agencias.asistencia_comparativa.view',
            '/mantenimiento/catalogo-juegos' => 'catalogo_juegos.view',
            '/coordinador-operador' => 'coordinador_operador.view',
            '/usuarios' => 'usuarios.view',
            '/incentivos/reporte-nuevo-incentivo-v5-view' => 'incentivos.reporte_nuevo_v5.view',
        ];

        return $existing[$path] ?? $module.'.'.Str::slug((string) $item['nombre'], '_').'.view';
    }

    /** @param array<string, mixed> $item */
    public function canAccess(?User $user, string $module, array $item): bool
    {
        if (! (bool) ($item['activo'] ?? true) || $user === null) {
            return false;
        }

        if (! empty($item['role']) && ! $user->hasRole($item['role'])) {
            return false;
        }

        return $user->can($this->permission($module, $item));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{path: string, name: string, module: string, permission: string, role: ?string}
     */
    private function entry(string $module, array $item): array
    {
        return [
            'path' => '/'.trim((string) parse_url((string) ($item['url'] ?? ''), PHP_URL_PATH), '/'),
            'name' => (string) ($item['nombre'] ?? ''),
            'module' => $module,
            'permission' => $this->permission($module, $item),
            'role' => $item['role'] ?? null,
        ];
    }
}
