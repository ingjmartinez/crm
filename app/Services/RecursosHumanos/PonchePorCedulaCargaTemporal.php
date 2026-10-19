<?php

namespace App\Services\RecursosHumanos;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PonchePorCedulaCargaTemporal
{
    /** @param array<string, mixed> $datos */
    public function guardarFragmento(array $datos): void
    {
        $directorio = $this->directorio($datos['token']);
        if ($datos['indice'] === 0 && $datos['parte'] === 0) {
            $this->limpiarCaducados();
        }

        $disco = Storage::disk('local');
        $marca = $directorio.'/.iniciado';
        if (! $disco->exists($marca)) {
            $disco->put($marca, (string) now()->timestamp);
        }

        $metadatos = [
            'nombre' => $datos['nombre'],
            'partes' => (int) $datos['partes'],
            'tamano' => (int) $datos['tamano'],
        ];
        $rutaMetadatos = $directorio.'/metadatos/'.$datos['indice'].'.json';
        if ($disco->exists($rutaMetadatos)) {
            if (json_decode($disco->get($rutaMetadatos), true) !== $metadatos) {
                throw ValidationException::withMessages(['fragmento' => 'Los fragmentos del documento no coinciden. Selecciónalo de nuevo.']);
            }
        } else {
            $disco->put($rutaMetadatos, json_encode($metadatos, JSON_THROW_ON_ERROR));
        }

        $rutaGuardada = $disco->putFileAs(
            $directorio.'/fragmentos/'.$datos['indice'],
            $datos['fragmento'],
            (string) $datos['parte']
        );
        if ($rutaGuardada === false) {
            throw ValidationException::withMessages(['fragmento' => 'No se pudo guardar un fragmento del documento.']);
        }
    }

    /**
     * @param  array<int, array{indice: int, nombre: string, partes: int, tamano: int}>  $documentos
     * @return array<int, UploadedFile>
     */
    public function archivos(string $token, array $documentos): array
    {
        $disco = Storage::disk('local');
        $directorio = $this->directorio($token);
        $archivos = [];

        foreach ($documentos as $indiceDocumento => $documento) {
            $rutaMetadatos = $directorio.'/metadatos/'.$documento['indice'].'.json';
            $esperados = [
                'nombre' => $documento['nombre'],
                'partes' => $documento['partes'],
                'tamano' => $documento['tamano'],
            ];
            if (! $disco->exists($rutaMetadatos) || json_decode($disco->get($rutaMetadatos), true) !== $esperados) {
                throw ValidationException::withMessages(['documentos.'.$indiceDocumento => 'Falta un documento o sus datos no coinciden.']);
            }

            $rutaArchivo = $directorio.'/ensamblados/'.$documento['indice'];
            $disco->makeDirectory($directorio.'/ensamblados');
            $salida = fopen($disco->path($rutaArchivo), 'wb');
            if ($salida === false) {
                throw ValidationException::withMessages(['documentos.'.$indiceDocumento => 'No se pudo preparar el documento.']);
            }

            try {
                for ($parte = 0; $parte < $documento['partes']; $parte++) {
                    $rutaFragmento = $directorio.'/fragmentos/'.$documento['indice'].'/'.$parte;
                    if (! $disco->exists($rutaFragmento)) {
                        throw ValidationException::withMessages(['documentos.'.$indiceDocumento => 'Falta un fragmento de '.$documento['nombre'].'.']);
                    }
                    $entrada = $disco->readStream($rutaFragmento);
                    if ($entrada === false) {
                        throw ValidationException::withMessages(['documentos.'.$indiceDocumento => 'No se pudo leer un fragmento de '.$documento['nombre'].'.']);
                    }
                    try {
                        stream_copy_to_stream($entrada, $salida);
                    } finally {
                        fclose($entrada);
                    }
                }
            } finally {
                fclose($salida);
            }

            $rutaAbsoluta = $disco->path($rutaArchivo);
            if (filesize($rutaAbsoluta) !== $documento['tamano']) {
                throw ValidationException::withMessages(['documentos.'.$indiceDocumento => 'El tamaño de '.$documento['nombre'].' no coincide.']);
            }
            $archivos[] = new UploadedFile($rutaAbsoluta, $documento['nombre'], null, UPLOAD_ERR_OK, true);
        }

        return $archivos;
    }

    public function limpiar(string $token): void
    {
        Storage::disk('local')->deleteDirectory($this->directorio($token));
    }

    private function directorio(string $token): string
    {
        return 'ponche_carga_temporal/'.hash('sha256', session()->getId()).'/'.$token;
    }

    private function limpiarCaducados(): void
    {
        $disco = Storage::disk('local');
        foreach ($disco->directories('ponche_carga_temporal') as $directorioSesion) {
            foreach ($disco->directories($directorioSesion) as $directorioLote) {
                $marca = $directorioLote.'/.iniciado';
                if (! $disco->exists($marca) || $disco->lastModified($marca) < now()->subDay()->timestamp) {
                    $disco->deleteDirectory($directorioLote);
                }
            }
        }
    }
}
