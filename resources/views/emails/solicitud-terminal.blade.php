<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de terminales {{ $solicitud->numero }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.5;">
    <h2 style="color: #1e3a8a;">Solicitud de códigos de terminales</h2>
    <p>Saludos,</p>
    <p>
        Adjuntamos la solicitud <strong>{{ $solicitud->numero }}</strong> de Grupo Joselito,
        correspondiente a <strong>{{ $solicitud->cantidad }} códigos de terminales</strong>
        con el prefijo <strong>{{ $solicitud->prefijo_empresa }}{{ $solicitud->prefijo_seleccionado }}</strong>.
    </p>
    <p>El documento formal se encuentra adjunto en formato PDF.</p>
    <p>Atentamente,<br><strong>Tecnología Joselito</strong></p>
</body>
</html>
