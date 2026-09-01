<?php

namespace App\Services\Contabilidad;

use App\Models\VolantePagoSocioCarga;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class VolantePagoSocioArchivoService
{
    public function __construct(
        private readonly VolantePagoSocioCsvService $csvService,
        private readonly VolantePagoSocioBanreservasService $banreservasService,
    ) {}

    /** @return array{carga: array<string, mixed>, detalles: array<int, array<string, mixed>>} */
    public function procesar(UploadedFile $archivo, string $banco): array
    {
        $esExcelBanreservas = $banco === VolantePagoSocioCarga::BANCO_BANRESERVAS
            && in_array(strtolower($archivo->getClientOriginalExtension()), ['xls', 'xlsx'], true);

        return $esExcelBanreservas
            ? $this->banreservasService->procesar($archivo)
            : $this->csvService->procesar($archivo);
    }
}
