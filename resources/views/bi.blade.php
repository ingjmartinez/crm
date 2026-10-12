<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>BI</title>
    @vite('resources/js/bi/app.js')
    @inertiaHead
</head>
<body>
    <div id="bi-loading" role="status" style="padding: 24px; font: 16px Arial, sans-serif; color: #17334a;">Cargando BI... Si este mensaje permanece, actualiza la página. Si continúa, avisa al administrador para revisar los archivos compilados.</div>
    @inertia
</body>
</html>
