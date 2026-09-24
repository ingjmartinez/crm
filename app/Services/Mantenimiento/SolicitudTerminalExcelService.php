<?php

namespace App\Services\Mantenimiento;

use App\Models\SolicitudTerminal;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use RuntimeException;

class SolicitudTerminalExcelService
{
    /**
     * Generate and store the agency request workbook from the supplied template.
     */
    public function generar(SolicitudTerminal $solicitud): string
    {
        $template = resource_path('templates/solicitud_agencias_loteka.xlsx');

        if (! is_file($template)) {
            throw new RuntimeException('No se encontró la plantilla de solicitud de agencias.');
        }

        $solicitud->load(['codigos' => fn ($query) => $query->orderBy('codigo')]);

        $libro = IOFactory::load($template);
        $hoja = $libro->getSheet(0);
        $hoja->getProtection()->setSheet(false);
        $hoja->setCellValue('B1', 'Estatus');
        $hoja->setShowGridlines(true);
        $hoja->getSheetView()->setZoomScale(100);
        $hoja->getDefaultRowDimension()->setZeroHeight(false)->setVisible(true)->setCollapsed(false);
        foreach (range('A', 'O') as $columna) {
            $hoja->getColumnDimension($columna)
                ->setOutlineLevel(0)
                ->setCollapsed(false)
                ->setVisible(true);
        }

        for ($numeroFila = 1; $numeroFila <= $hoja->getHighestRow(); $numeroFila++) {
            $hoja->getRowDimension($numeroFila)
                ->setOutlineLevel(0)
                ->setCollapsed(false)
                ->setVisible(true)
                ->setZeroHeight(false);
        }

        for ($numeroFila = 2; $numeroFila <= $hoja->getHighestRow(); $numeroFila++) {
            foreach (range('A', 'O') as $columna) {
                $hoja->setCellValue("{$columna}{$numeroFila}", null);
            }
        }

        $fila = 2;

        foreach ($solicitud->codigos as $codigo) {
            if ($fila > 265) {
                $hoja->insertNewRowBefore($fila, 1);
            }

            $estaAprobada = $codigo->estado === 'aprobado';
            $hoja->setCellValue("A{$fila}", $fila - 1);
            $hoja->setCellValueExplicit("B{$fila}", $estaAprobada ? 'Aprobada' : 'Pendiente', DataType::TYPE_STRING);
            $hoja->getStyle("B{$fila}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB($estaAprobada ? 'FFC6EFCE' : 'FFFCE4D6');
            $hoja->setCellValueExplicit("C{$fila}", 'Grupo Joselito', DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("D{$fila}", (string) ($codigo->nombre_banca ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("E{$fila}", (string) $codigo->codigo, DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("F{$fila}", (string) ($codigo->region ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("G{$fila}", (string) ($codigo->provincia ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("H{$fila}", (string) ($codigo->municipio ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("I{$fila}", (string) ($codigo->ciudad ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("J{$fila}", (string) ($codigo->sector ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("K{$fila}", (string) ($codigo->calle ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("L{$fila}", (string) ($codigo->direccion_local ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("M{$fila}", (string) ($codigo->latitud ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("N{$fila}", (string) ($codigo->longitud ?? ''), DataType::TYPE_STRING);
            $hoja->setCellValueExplicit("O{$fila}", (string) ($codigo->rja ?? ''), DataType::TYPE_STRING);
            $fila++;
        }

        foreach ($hoja->getTableCollection() as $tabla) {
            $tabla->setRange('A1:O'.max(265, $fila - 1));
        }

        $ruta = 'solicitudes-terminales/Solicitud de agencia loteka #'.$solicitud->id.'.xlsx';
        $temporal = tempnam(sys_get_temp_dir(), 'solicitud_agencias_');

        if ($temporal === false) {
            throw new RuntimeException('No se pudo preparar el archivo Excel.');
        }

        try {
            IOFactory::createWriter($libro, 'Xlsx')->save($temporal);
            Storage::disk('local')->put($ruta, file_get_contents($temporal));
        } finally {
            @unlink($temporal);
            $libro->disconnectWorksheets();
        }

        return $ruta;
    }
}
