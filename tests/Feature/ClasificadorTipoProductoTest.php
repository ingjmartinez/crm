<?php

namespace Tests\Feature;

use App\Services\Ventas\ClasificadorTipoProducto;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClasificadorTipoProductoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('catalogo_juegos');
        Schema::create('catalogo_juegos', function (Blueprint $table): void {
            $table->id();
            $table->string('producto_id')->nullable();
            $table->string('tipo')->nullable();
            $table->string('descripcion')->nullable();
        });

        DB::table('catalogo_juegos')->insert([
            ['producto_id' => '7', 'tipo' => 'Tradicional', 'descripcion' => 'NACIONAL NOCHE QUINIELA'],
            ['producto_id' => '34', 'tipo' => 'No Tradicional', 'descripcion' => 'CHANCE EXPRESS CHANCE EXPRESS'],
            ['producto_id' => '-1', 'tipo' => 'Recarga', 'descripcion' => ''],
        ]);
    }

    public function test_classifies_by_producto_id(): void
    {
        $clasificador = new ClasificadorTipoProducto;

        $this->assertSame('tradicional', $clasificador->clasificar(['producto_id' => '7', 'descripcion' => 'lo que sea']));
        $this->assertSame('no_tradicional', $clasificador->clasificar(['producto_id' => '34', 'descripcion' => 'lo que sea']));
    }

    public function test_falls_back_to_description_when_producto_id_is_unknown(): void
    {
        $clasificador = new ClasificadorTipoProducto;

        $this->assertSame(
            'no_tradicional',
            $clasificador->clasificar(['producto_id' => '9999', 'descripcion' => '  chance express   chance express '])
        );
    }

    public function test_products_outside_the_catalog_are_reported_as_otros(): void
    {
        $clasificador = new ClasificadorTipoProducto;

        $this->assertSame('otros', $clasificador->clasificar(['producto_id' => '9999', 'descripcion' => 'PRODUCTO NUEVO']));
        $this->assertSame('otros', $clasificador->clasificar(['producto_id' => '-1', 'descripcion' => '']));
    }

    public function test_uses_the_tipo_of_the_sale_when_the_catalog_has_no_match(): void
    {
        $clasificador = new ClasificadorTipoProducto;

        $this->assertSame(
            'tradicional',
            $clasificador->clasificar(['producto_id' => '9999', 'descripcion' => 'PRODUCTO NUEVO', 'tipo' => 'Tradicional'])
        );
    }

    public function test_enriches_a_list_of_sales_without_losing_the_original_fields(): void
    {
        $clasificador = new ClasificadorTipoProducto;

        $enriquecidas = $clasificador->enriquecer([
            ['producto_id' => '7', 'descripcion' => 'NACIONAL NOCHE QUINIELA', 'monto' => 100, 'agencia_id' => '00123'],
            ['producto_id' => '34', 'descripcion' => 'CHANCE EXPRESS CHANCE EXPRESS', 'monto' => 50, 'agencia_id' => '00123'],
        ]);

        $this->assertSame('tradicional', $enriquecidas[0]['tipo_categoria']);
        $this->assertSame('no_tradicional', $enriquecidas[1]['tipo_categoria']);
        $this->assertSame(100, $enriquecidas[0]['monto']);
        $this->assertSame('00123', $enriquecidas[0]['agencia_id']);
    }
}
