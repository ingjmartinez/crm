<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $basePermissions = [
            'usuarios.view',
            'usuarios.list',
            'usuarios.create',
            'usuarios.edit',
            'usuarios.delete',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',
            'recursos_humanos.view',
            'reportes.view',
            'servicios_generales.view',
            'servicios_generales.create',
            'servicios_generales.manage',
            'servicios_generales.close',
        ];

        $modulePermissions = [
            'dashboard.view',
            'gerencia.view',
            'procesos.view',
            'contabilidad.view',
            'mantenimiento.view',
            'comercial.view',
            'operaciones.view',
            'incentivos.view',
            'tecnologia.view',
            'tareas.view',
        ];

        $viewPermissions = [
            'dashboard.tablero_principal.view',
            'dashboard.lotobet_ventas.view',
            'dashboard.kpi_metas.view',
            'dashboard.lotobet_flash.view',
            'dashboard.lotonet_ventas.view',
            'dashboard.mar_ventas.view',
            'gerencia.gerencial.view',
            'gerencia.venta_gerencial.view',
            'gerencia.venta_comparativa.view',
            'procesos.gerencia.view',
            'procesos.contabilidad.view',
            'procesos.recursos_humanos.view',
            'procesos.operaciones.view',
            'procesos.comercial.view',
            'procesos.mantenimiento.view',
            'procesos.tecnologia.view',
            'contabilidad.inicio.view',
            'contabilidad.comisiones.view',
            'contabilidad.comisiones_por_grupo.view',
            'contabilidad.estado_resultado.view',
            'contabilidad.flujo_ruta.view',
            'contabilidad.electricidad.view',
            'contabilidad.centro_costo.view',
            'contabilidad.movimiento_mayor.view',
            'agencias.view',
            'agencias.incumplimientos_horario.view',
            'agencias.asistencia_comparativa.view',
            'catalogo_juegos.view',
            'coordinador_operador.view',
            'comercial.resumen.view',
            'comercial.kpi_ventas.view',
            'comercial.kpi_ventas_v.view',
            'comercial.agencia_plan.view',
            'comercial.meta_incentivo.view',
            'comercial.ventas_producto.view',
            'comercial.gestion_usuarios.view',
            'operaciones.panel.view',
            'operaciones.gestion.view',
            'operaciones.operador_ruta.view',
            'operaciones.ruta.view',
            'operaciones.rutas_consolidadas.view',
            'operaciones.deposito_ruta.view',
            'operaciones.reporte_diario.view',
            'operaciones.reporte_mensual.view',
            'tecnologia.solicitudes.view',
            'incentivos.procesar.view',
            'incentivos.gestion.view',
            'incentivos.empleados.view',
            'incentivos.reporte_pagos.view',
            'incentivos.reporte_nuevo.view',
            'incentivos.reporte_nuevo_v2.view',
            'incentivos.reporte_nuevo_v3.view',
            'incentivos.reporte_nuevo_v4.view',
            'incentivos.reporte_nuevo_v5.view',
            'incentivos.incentivo_administrativo.view',
            'incentivos.porcentaje_incentivo.view',
            'tareas.panel.view',
            'tareas.proyecto.view',
        ];

        $permissions = array_values(array_unique(array_merge(
            $basePermissions,
            $modulePermissions,
            $viewPermissions
        )));

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'superadmin' => $permissions,
            'admin' => $permissions,
            'contabilidad' => ['usuarios.view', 'usuarios.list'],
            'rh' => ['recursos_humanos.view', 'reportes.view'],
            'comercial' => ['usuarios.view', 'usuarios.list'],
            'monitoreo' => ['usuarios.view', 'usuarios.list'],
            'servicios_generales' => [
                'servicios_generales.view',
                'servicios_generales.create',
                'servicios_generales.manage',
                'servicios_generales.close',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($rolePermissions);
        }

        $superAdminEmail = env('SUPERADMIN_EMAIL', 'admin@joselitogroud.com');
        $superAdmin = User::where('email', $superAdminEmail)->first();

        if ($superAdmin) {
            $superAdmin->syncRoles(['superadmin']);
        }
    }
}
