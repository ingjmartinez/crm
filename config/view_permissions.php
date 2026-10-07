<?php

return [
    'ventas_api' => [
        '/generar-lotobet' => 'ventas_api.generar_lotobet.view',
        '/generar-lotonet' => 'ventas_api.generar_lotonet.view',
        '/ventas-por-usuario-lotobet' => 'ventas_api.ventas_por_usuario_lotobet.view',
        '/faltantes-lotobet' => 'ventas_api.faltantes_lotobet.view',
        '/ventas-por-producto-lotobet' => 'ventas_api.ventas_por_producto_lotobet.view',
        '/recargas-lotobet' => 'ventas_api.recargas_lotobet.view',
        '/premios-lotobet' => 'ventas_api.premios_lotobet.view',
        '/pagos-misma-empresa-lotobet' => 'ventas_api.pagos_misma_empresa_lotobet.view',
        '/pagos-aotra-empresa-lotobet' => 'ventas_api.pagos_a_otra_empresa_lotobet.view',
        '/pagos-porotra-empresa-lotobet' => 'ventas_api.pagos_por_otra_empresa_lotobet.view',
        '/asistencias-lotobet' => 'ventas_api.asistencias_lotobet.view',
        '/ventas-por-usuario-lotonet' => 'ventas_api.ventas_por_usuario_lotonet.view',
        '/faltantes-lotonet' => 'ventas_api.faltantes_lotonet.view',
        '/paquetico-lotonet' => 'ventas_api.paquetico_lotonet.view',
        '/recargas-lotonet' => 'ventas_api.recargas_lotonet.view',
        '/ventas-por-producto-lotonet' => 'ventas_api.ventas_por_producto_lotonet.view',
        '/premios-lotonet' => 'ventas_api.premios_lotonet.view',
        '/pagos-misma-empresa-lotonet' => 'ventas_api.pagos_misma_empresa_lotonet.view',
        '/pagos-aotra-empresa-lotonet' => 'ventas_api.pagos_a_otra_empresa_lotonet.view',
        '/pagos-porotra-empresa-lotonet' => 'ventas_api.pagos_por_otra_empresa_lotonet.view',
        '/asistencias-lotonet' => 'ventas_api.asistencias_lotonet.view',
        '/mar-ventas' => 'ventas_api.mar_ventas.view',
        '/ventas-flash-lotobet' => 'ventas_api.ventas_flash_lotobet.view',
        '/ventas-flash-lotonet' => 'ventas_api.ventas_flash_lotonet.view',
    ],
    'reportes' => [
        '/reportes-bi/resumen-ventas' => 'reportes.bi_resumen_ventas.view',
        '/reportes-bi/ventas-usuarios' => 'reportes.bi_ventas_usuarios.view',
        '/reportes-bi/faltantes' => 'reportes.bi_faltantes.view',
    ],
    'tareas' => [
        '/tareas' => 'tareas.panel.view',
        '/tareas/proyecto' => 'tareas.proyecto.view',
    ],
    'mantenimiento' => [
        '/agencias-boletines' => 'agencias.view',
    ],
];
