<?php

namespace Database\Seeders;

use App\ViewPermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MissingViewPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(ViewPermissionCatalog $catalog): void
    {
        $items = $catalog->items();

        foreach ($items->pluck('permission')->unique() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (Role::query()->with('permissions')->get() as $role) {
            $modules = match ($role->name) {
                'superadmin', 'admin', 'admin2' => $items->pluck('module')->unique()->all(),
                'rh' => ['recursos_humanos', 'reportes'],
                'contabilidad' => ['contabilidad'],
                'comercial' => ['comercial'],
                'Conta_Rep' => ['reportes'],
                'servicios_generales' => ['servicios_generales'],
                default => $role->permissions->pluck('name')
                    ->filter(fn (string $name): bool => str_ends_with($name, '.view'))
                    ->map(fn (string $name): string => substr($name, 0, -5))
                    ->all(),
            };

            if ($role->permissions->contains('name', 'dashboard.view')) {
                $modules[] = 'ventas_api';
            }

            $grants = $items->filter(fn (array $item): bool => in_array($item['module'], $modules, true))
                ->filter(fn (array $item): bool => $item['role'] === null || $role->name === $item['role'])
                ->pluck('permission')->push('dashboard.tablero_principal.view')->unique()->all();

            if ($grants !== []) {
                $role->givePermissionTo($grants);
            }
        }
    }
}
