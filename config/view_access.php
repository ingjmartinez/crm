<?php

return [
    'extra_modules' => [
        'tareas' => [
            'titulo' => 'Tareas',
            'url' => '/accesos/tareas',
            'items' => [
                0 => [
                    'nombre' => 'Tareas',
                    'url' => '/tareas',
                ],
                1 => [
                    'nombre' => 'Proyecto',
                    'url' => '/tareas/proyecto',
                ],
            ],
        ],
        'apis_ventas' => [
            'titulo' => 'APIs de ventas',
            'url' => '/accesos/apis_ventas',
            'items' => [
                0 => [
                    'nombre' => 'Generar Lotobet',
                    'url' => '/generar-lotobet',
                ],
                1 => [
                    'nombre' => 'Generar Lotonet',
                    'url' => '/generar-lotonet',
                ],
                2 => [
                    'nombre' => 'Ventas MAR',
                    'url' => '/mar-ventas',
                ],
            ],
        ],
        'reportes_bi' => [
            'titulo' => 'Reportes BI',
            'url' => '/accesos/reportes_bi',
            'items' => [
                0 => [
                    'nombre' => 'Faltantes',
                    'url' => '/reportes-bi/faltantes',
                ],
                1 => [
                    'nombre' => 'Resumen de ventas',
                    'url' => '/reportes-bi/resumen-ventas',
                ],
                2 => [
                    'nombre' => 'Ventas por usuario',
                    'url' => '/reportes-bi/ventas-usuarios',
                ],
            ],
        ],
    ],
    'aliases' => [
        '/inicio/ventas-data' => [
            0 => '/',
        ],
        '/api-centros-costo' => [
            0 => '/contabilidad/centro-costo',
        ],
        '/api-cuentas' => [
            0 => '/contabilidad/centro-costo',
        ],
        '/api-entradas-diario' => [
            0 => '/contabilidad/movimiento-mayor',
        ],
        '/api-entradas' => [
            0 => '/contabilidad/inicio',
        ],
        '/incentivos/list' => [
            0 => '/incentivos/gestion',
            1 => '/incentivos/procesar',
        ],
        '/incentivos/save' => [
            0 => '/incentivos/gestion',
            1 => '/incentivos/procesar',
        ],
        '/incentivos/reporte-pago-incentivos' => [
            0 => '/incentivos/reporte-pagos',
        ],
        '/tareas-list' => [
            0 => '/tareas',
            1 => '/tareas/proyecto',
        ],
        '/tareas/gantt-data' => [
            0 => '/tareas/proyecto',
        ],
        '/tareas/stats' => [
            0 => '/tareas',
            1 => '/tareas/proyecto',
        ],
        '/tareas/usuarios' => [
            0 => '/tareas',
            1 => '/tareas/proyecto',
        ],
        '/tareas/departamentos' => [
            0 => '/tareas',
            1 => '/tareas/proyecto',
        ],
        '/usuarios-list' => [
            0 => '/usuarios',
        ],
        '/legal/contratos' => [
            0 => '/legal/bitacora-agencias',
        ],
        '/empleados-no-regularizados' => [
            0 => '/empleados-no-regularizados',
        ],
        '/ventas-sin-empleado' => [
            0 => '/ventas-sin-empleado',
        ],
        '/auto-proceso/lotobet/config' => [
            0 => '/generar-lotobet',
        ],
        '/ventas-usuarios-lotobet' => [
            0 => '/ventas-por-usuario-lotobet',
        ],
        '/get-ventas-usuarios-lotobet' => [
            0 => '/ventas-por-usuario-lotobet',
        ],
        '/save-ventas-usuarios-lotobet' => [
            0 => '/ventas-por-usuario-lotobet',
        ],
        '/delete-ventas-usuarios-lotobet' => [
            0 => '/ventas-por-usuario-lotobet',
        ],
        '/ventas-producto-lotobet' => [
            0 => '/ventas-por-producto-lotobet',
        ],
        '/get-ventas-producto-lotobet' => [
            0 => '/ventas-por-producto-lotobet',
        ],
        '/save-ventas-producto-lotobet' => [
            0 => '/ventas-por-producto-lotobet',
        ],
        '/delete-ventas-producto-lotobet' => [
            0 => '/ventas-por-producto-lotobet',
        ],
        '/faltantes-lotobet' => [
            0 => '/faltantes-lotobet',
        ],
        '/get-faltantes-lotobet' => [
            0 => '/faltantes-lotobet',
        ],
        '/save-faltantes-lotobet' => [
            0 => '/faltantes-lotobet',
        ],
        '/delete-faltantes-lotobet' => [
            0 => '/faltantes-lotobet',
        ],
        '/recargas-lotobet' => [
            0 => '/recargas-lotobet',
        ],
        '/get-recargas-lotobet' => [
            0 => '/recargas-lotobet',
        ],
        '/save-recargas-lotobet' => [
            0 => '/recargas-lotobet',
        ],
        '/delete-recargas-lotobet' => [
            0 => '/recargas-lotobet',
        ],
        '/premios-lotobet' => [
            0 => '/premios-lotobet',
        ],
        '/get-premios-lotobet' => [
            0 => '/premios-lotobet',
        ],
        '/save-premios-lotobet' => [
            0 => '/premios-lotobet',
        ],
        '/delete-premios-lotobet' => [
            0 => '/premios-lotobet',
        ],
        '/pagos-misma-empresa-lotobet' => [
            0 => '/pagos-misma-empresa-lotobet',
        ],
        '/get-pagos-misma-empresa-lotobet' => [
            0 => '/pagos-misma-empresa-lotobet',
        ],
        '/save-pagos-misma-empresa-lotobet' => [
            0 => '/pagos-misma-empresa-lotobet',
        ],
        '/delete-pagos-misma-empresa-lotobet' => [
            0 => '/pagos-misma-empresa-lotobet',
        ],
        '/pagos-aotra-empresa-lotobet' => [
            0 => '/pagos-aotra-empresa-lotobet',
        ],
        '/get-pagos-aotra-empresa-lotobet' => [
            0 => '/pagos-aotra-empresa-lotobet',
        ],
        '/save-pagos-aotra-empresa-lotobet' => [
            0 => '/pagos-aotra-empresa-lotobet',
        ],
        '/delete-pagos-aotra-empresa-lotobet' => [
            0 => '/pagos-aotra-empresa-lotobet',
        ],
        '/pagos-porotra-empresa-lotobet' => [
            0 => '/pagos-porotra-empresa-lotobet',
        ],
        '/get-pagos-porotra-empresa-lotobet' => [
            0 => '/pagos-porotra-empresa-lotobet',
        ],
        '/save-pagos-porotra-empresa-lotobet' => [
            0 => '/pagos-porotra-empresa-lotobet',
        ],
        '/delete-pagos-porotra-empresa-lotobet' => [
            0 => '/pagos-porotra-empresa-lotobet',
        ],
        '/asistencias-lotobet' => [
            0 => '/asistencias-lotobet',
        ],
        '/get-asistencias-lotobet' => [
            0 => '/asistencias-lotobet',
        ],
        '/save-asistencias-lotobet' => [
            0 => '/asistencias-lotobet',
        ],
        '/delete-asistencias-lotobet' => [
            0 => '/asistencias-lotobet',
        ],
        '/ventas-flash-lotobet' => [
            0 => '/ventas-flash-lotobet',
        ],
        '/get-ventas-flash-lotobet' => [
            0 => '/ventas-flash-lotobet',
        ],
        '/save-ventas-flash-lotobet' => [
            0 => '/ventas-flash-lotobet',
        ],
        '/delete-ventas-flash-lotobet' => [
            0 => '/ventas-flash-lotobet',
        ],
        '/auto-proceso/lotonet/config' => [
            0 => '/generar-lotonet',
        ],
        '/ventas-usuarios-lotonet' => [
            0 => '/ventas-por-usuario-lotonet',
        ],
        '/get-ventas-usuarios-lotonet' => [
            0 => '/ventas-por-usuario-lotonet',
        ],
        '/save-ventas-usuarios-lotonet' => [
            0 => '/ventas-por-usuario-lotonet',
        ],
        '/delete-ventas-usuarios-lotonet' => [
            0 => '/ventas-por-usuario-lotonet',
        ],
        '/ventas-producto-lotonet' => [
            0 => '/ventas-por-producto-lotonet',
        ],
        '/get-ventas-producto-lotonet' => [
            0 => '/ventas-por-producto-lotonet',
        ],
        '/save-ventas-producto-lotonet' => [
            0 => '/ventas-por-producto-lotonet',
        ],
        '/delete-ventas-producto-lotonet' => [
            0 => '/ventas-por-producto-lotonet',
        ],
        '/faltantes-lotonet' => [
            0 => '/faltantes-lotonet',
        ],
        '/get-faltantes-lotonet' => [
            0 => '/faltantes-lotonet',
        ],
        '/save-faltantes-lotonet' => [
            0 => '/faltantes-lotonet',
        ],
        '/delete-faltantes-lotonet' => [
            0 => '/faltantes-lotonet',
        ],
        '/recargas-lotonet' => [
            0 => '/recargas-lotonet',
        ],
        '/get-recargas-lotonet' => [
            0 => '/recargas-lotonet',
        ],
        '/save-recargas-lotonet' => [
            0 => '/recargas-lotonet',
        ],
        '/delete-recargas-lotonet' => [
            0 => '/recargas-lotonet',
        ],
        '/premios-lotonet' => [
            0 => '/premios-lotonet',
        ],
        '/get-premios-lotonet' => [
            0 => '/premios-lotonet',
        ],
        '/save-premios-lotonet' => [
            0 => '/premios-lotonet',
        ],
        '/delete-premios-lotonet' => [
            0 => '/premios-lotonet',
        ],
        '/pagos-misma-empresa-lotonet' => [
            0 => '/pagos-misma-empresa-lotonet',
        ],
        '/get-pagos-misma-empresa-lotonet' => [
            0 => '/pagos-misma-empresa-lotonet',
        ],
        '/save-pagos-misma-empresa-lotonet' => [
            0 => '/pagos-misma-empresa-lotonet',
        ],
        '/delete-pagos-misma-empresa-lotonet' => [
            0 => '/pagos-misma-empresa-lotonet',
        ],
        '/pagos-aotra-empresa-lotonet' => [
            0 => '/pagos-aotra-empresa-lotonet',
        ],
        '/get-pagos-aotra-empresa-lotonet' => [
            0 => '/pagos-aotra-empresa-lotonet',
        ],
        '/save-pagos-aotra-empresa-lotonet' => [
            0 => '/pagos-aotra-empresa-lotonet',
        ],
        '/delete-pagos-aotra-empresa-lotonet' => [
            0 => '/pagos-aotra-empresa-lotonet',
        ],
        '/pagos-porotra-empresa-lotonet' => [
            0 => '/pagos-porotra-empresa-lotonet',
        ],
        '/get-pagos-porotra-empresa-lotonet' => [
            0 => '/pagos-porotra-empresa-lotonet',
        ],
        '/save-pagos-porotra-empresa-lotonet' => [
            0 => '/pagos-porotra-empresa-lotonet',
        ],
        '/delete-pagos-porotra-empresa-lotonet' => [
            0 => '/pagos-porotra-empresa-lotonet',
        ],
        '/asistencias-lotonet' => [
            0 => '/asistencias-lotonet',
        ],
        '/get-asistencias-lotonet' => [
            0 => '/asistencias-lotonet',
        ],
        '/save-asistencias-lotonet' => [
            0 => '/asistencias-lotonet',
        ],
        '/delete-asistencias-lotonet' => [
            0 => '/asistencias-lotonet',
        ],
        '/ventas-flash-lotonet' => [
            0 => '/ventas-flash-lotonet',
        ],
        '/get-ventas-flash-lotonet' => [
            0 => '/ventas-flash-lotonet',
        ],
        '/save-ventas-flash-lotonet' => [
            0 => '/ventas-flash-lotonet',
        ],
        '/delete-ventas-flash-lotonet' => [
            0 => '/ventas-flash-lotonet',
        ],
        '/get-mar-ventas' => [
            0 => '/mar-ventas',
        ],
        '/get-paquetico-lotonet' => [
            0 => '/paquetico-lotonet',
        ],
        '/save-mar-ventas' => [
            0 => '/mar-ventas',
        ],
        '/save-paquetico-lotonet' => [
            0 => '/paquetico-lotonet',
        ],
        '/delete-mar-ventas' => [
            0 => '/mar-ventas',
        ],
        '/delete-paquetico-lotonet' => [
            0 => '/paquetico-lotonet',
        ],
        '/login-flash' => [
            0 => '/ventas-flash-lotobet',
            1 => '/generar-lotobet',
        ],
        '/generar-token' => [
            0 => '/generar-lotobet',
            1 => '/agencias/asistencia-comparativa',
            2 => '/ventas-por-usuario-lotobet',
            3 => '/ventas-por-producto-lotobet',
            4 => '/faltantes-lotobet',
            5 => '/recargas-lotobet',
            6 => '/premios-lotobet',
            7 => '/pagos-misma-empresa-lotobet',
            8 => '/pagos-aotra-empresa-lotobet',
            9 => '/pagos-porotra-empresa-lotobet',
            10 => '/asistencias-lotobet',
            11 => '/comercial/ventas-producto',
            12 => '/faltantes-lotonet',
        ],
        '/iniciar-session' => [
            0 => '/generar-lotonet',
            1 => '/agencias/asistencia-comparativa',
            2 => '/ventas-por-usuario-lotonet',
            3 => '/ventas-por-producto-lotonet',
            4 => '/faltantes-lotonet',
            5 => '/recargas-lotonet',
            6 => '/premios-lotonet',
            7 => '/pagos-misma-empresa-lotonet',
            8 => '/pagos-aotra-empresa-lotonet',
            9 => '/pagos-porotra-empresa-lotonet',
            10 => '/asistencias-lotonet',
            11 => '/paquetico-lotonet',
            12 => '/tecnologia/monitoreo-agentes-ventas',
        ],
    ],
];
