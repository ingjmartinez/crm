<!doctype html>
<html lang="es">
<body style="margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#1f2937">
    <div style="max-width:620px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:28px">
        <h2 style="margin-top:0;color:#17619a">Volante de pago</h2>
        <p>Adjuntamos el volante correspondiente a <strong>{{ $detalle->nombre }}</strong>.</p>
        <p>Monto: <strong>RD${{ number_format((float) $detalle->monto, 2) }}</strong></p>
        <p style="margin-bottom:0;color:#6b7280;font-size:13px">Fecha de transacción: {{ $detalle->carga->fecha_transaccion->format('d/m/Y h:i:s A') }}</p>
    </div>
</body>
</html>
