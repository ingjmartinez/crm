<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Volante de pago - {{ $detalle->nombre }}</title>
    <style>
        @page { margin: 42px 50px; }
        body { font-family: DejaVu Sans, sans-serif; color: #202a35; font-size: 12px; }
        .top { width: 100%; margin-bottom: 45px; }
        .top td { vertical-align: top; }
        .bank-logo { display: block; width: 285px; height: auto; }
        .bank-logo-crop { width: 285px; height: 95px; overflow: hidden; }
        .bank-logo-crop .bank-logo { margin-top: -95px; }
        .bank-wordmark { color: #005ca9; font-size: 27px; font-weight: bold; letter-spacing: .8px; margin-bottom: 8px; }
        .bank-info { margin: 8px 0 0 14px; font-size: 9px; font-weight: bold; line-height: 1.4; }
        .date { text-align: right; font-size: 10px; }
        h1 { font-size: 23px; margin: 0 0 18px; }
        .box { border: 1px solid #cfd5dc; border-radius: 16px; padding: 19px 22px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { width: 25%; vertical-align: top; padding: 0 16px 20px 0; }
        .label { font-weight: bold; margin-bottom: 5px; }
        .value { font-weight: bold; font-size: 13px; }
        .amount { font-size: 16px; }
        .detail-title { font-size: 20px; margin: 34px 0 12px; }
        .detail td { border-bottom: 1px solid #e3e6ea; padding: 10px 8px; }
        .detail .label-cell { width: 32%; font-weight: bold; color: #475569; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; color: #6b7280; font-size: 9px; }
    </style>
</head>
<body>
    @php
        $esBanreservas = $detalle->carga->banco === \App\Models\VolantePagoSocioCarga::BANCO_BANRESERVAS;
        $logoPath = public_path($esBanreservas
            ? 'images/banreservas-logo-volantes.png'
            : 'images/banco-santa-cruz-volantes.png');
        $logoData = is_file($logoPath)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
            : null;
        $bancoNombre = $detalle->carga->nombreBanco();
        $bancoRnc = $esBanreservas ? '4-01-01006-2' : '1-02-01292-1';
        $bancoContacto = $esBanreservas ? '809-960-2121' : '809-726-1000';
        $fechaVolante = $detalle->carga->fecha_correspondiente ?? $detalle->carga->fecha_transaccion;
    @endphp
    <table class="top">
        <tr>
            <td>
                @if ($logoData)
                    @if ($esBanreservas)
                        <div class="bank-logo-crop">
                            <img src="{{ $logoData }}" class="bank-logo" alt="{{ $bancoNombre }}">
                        </div>
                    @else
                        <img src="{{ $logoData }}" class="bank-logo" alt="{{ $bancoNombre }}">
                    @endif
                @elseif ($esBanreservas)
                    <div class="bank-wordmark">BANRESERVAS</div>
                @else
                    <div class="bank-wordmark">{{ strtoupper($bancoNombre) }}</div>
                @endif
                <div class="bank-info">{{ $bancoNombre }}<br>RNC: {{ $bancoRnc }}<br>Contacto: {{ $bancoContacto }}</div>
            </td>
            <td class="date">
                {{ $fechaVolante->format('d/m/Y') }}<br><br>
                Volante de pago
            </td>
        </tr>
    </table>

    <h1>Datos de la Transacción</h1>
    <div class="box">
        <table class="grid">
            <tr>
                <td><div class="label">No. de Solicitud</div><div class="value">{{ $detalle->id }}</div></td>
                <td><div class="label">Tasa de Cambio</div><div class="value">No Aplica</div></td>
                <td><div class="label">No. de Cuenta</div><div class="value">{{ $detalle->carga->cuenta_origen }}</div></td>
                <td><div class="label">Comisión</div><div class="value">RD$0.00</div></td>
            </tr>
            <tr>
                <td><div class="label">Tipo Cuenta</div><div class="value">Cuenta Corriente</div></td>
                <td><div class="label">Impuesto</div><div class="value">RD$0.00</div></td>
                <td><div class="label">Monto</div><div class="value amount">RD${{ number_format((float) $detalle->monto, 2) }}</div></td>
                <td><div class="label">Tipo de Transacción</div><div class="value">{{ $detalle->carga->tipo_transaccion }}</div></td>
            </tr>
            <tr>
                <td colspan="2"><div class="label">Fecha correspondiente</div><div class="value">{{ $fechaVolante->format('d/m/Y') }}</div></td>
                <td colspan="2"><div class="label">Estado</div><div class="value">{{ $detalle->estado }}</div></td>
            </tr>
        </table>
    </div>

    <h2 class="detail-title">Detalle del Archivo</h2>
    <table class="grid detail">
        <tr><td class="label-cell">Nombre</td><td>{{ $detalle->nombre }}</td></tr>
        <tr><td class="label-cell">{{ $detalle->tipo_identificacion }}</td><td>{{ $detalle->identificacion }}</td></tr>
        <tr><td class="label-cell">Cuenta del beneficiario</td><td>{{ $detalle->cuenta }}</td></tr>
        <tr><td class="label-cell">Tipo de cuenta</td><td>{{ $detalle->tipo_cuenta }}</td></tr>
        <tr><td class="label-cell">Monto pagado</td><td><strong>RD${{ number_format((float) $detalle->monto, 2) }}</strong></td></tr>
    </table>

    <div class="footer">Documento generado a partir del archivo {{ $detalle->carga->nombre_archivo }}.</div>
</body>
</html>
