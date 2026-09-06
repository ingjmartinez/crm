<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud {{ $solicitud->numero }}</title>
    <style>
        @page { margin: 35px 45px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        h1 { margin: 0 0 4px; font-size: 21px; color: #111827; }
        .subtitulo { color: #6b7280; margin-bottom: 28px; }
        .encabezado { width: 100%; margin-bottom: 24px; border-collapse: collapse; }
        .encabezado td { width: 50%; padding: 7px 0; vertical-align: top; }
        .etiqueta { color: #6b7280; font-size: 10px; text-transform: uppercase; }
        .valor { font-weight: bold; margin-top: 2px; }
        table.codigos { width: 100%; border-collapse: collapse; }
        table.codigos th { background: #1e3a8a; color: #fff; padding: 8px; text-align: left; }
        table.codigos td { padding: 8px; border-bottom: 1px solid #d1d5db; }
        table.codigos tr:nth-child(even) td { background: #f8fafc; }
        .pie { margin-top: 35px; color: #6b7280; font-size: 10px; }
        .firma { margin-top: 55px; width: 45%; border-top: 1px solid #374151; text-align: center; padding-top: 7px; }
    </style>
</head>
<body>
    <h1>Solicitud de códigos de terminales</h1>
    <div class="subtitulo">Dirigida a Loteka Central</div>

    <table class="encabezado">
        <tr>
            <td>
                <div class="etiqueta">Número de solicitud</div>
                <div class="valor">{{ $solicitud->numero }}</div>
            </td>
            <td>
                <div class="etiqueta">Fecha</div>
                <div class="valor">{{ $solicitud->created_at->format('d/m/Y') }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="etiqueta">Empresa solicitante</div>
                <div class="valor">Grupo Joselito</div>
            </td>
            <td>
                <div class="etiqueta">Solicitado por</div>
                <div class="valor">Tecnología Joselito</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="etiqueta">Prefijo</div>
                <div class="valor">{{ $solicitud->prefijo_empresa }}{{ $solicitud->prefijo_seleccionado }}</div>
            </td>
            <td>
                <div class="etiqueta">Cantidad solicitada</div>
                <div class="valor">{{ $solicitud->cantidad }} códigos</div>
            </td>
        </tr>
    </table>

    <p>Por medio de la presente solicitamos la asignación y aprobación de los siguientes códigos de terminales:</p>

    <table class="codigos">
        <thead>
            <tr>
                <th style="width: 12%;">N.º</th>
                <th>Código de terminal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($solicitud->codigos as $codigo)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $codigo->codigo }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="firma">Firma autorizada</div>
    <div class="pie">Documento generado por el sistema el {{ now()->format('d/m/Y h:i A') }}.</div>
</body>
</html>
