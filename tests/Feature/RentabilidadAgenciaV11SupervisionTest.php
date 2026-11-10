<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

class RentabilidadAgenciaV11SupervisionTest extends TestCase
{
    public function test_busca_agrega_quita_restaura_y_exporta_solo_la_lista_de_supervision(): void
    {
        $this->ejecutarJavascript(<<<'JS'
await montar(configuracion);
assert.equal(opciones.columns.length,7);
assert.equal(opciones.columns[4].data,'periodos.0.ventas_tradicionales');
assert.equal(opciones.columns[5].data,'periodos.0.premios_tradicionales');
assert.equal(opciones.columns[4].render(123456.78,'export'),123456.78);
assert.equal(opciones.columns[5].render(34567.89,'export'),34567.89);
assert.ok(opciones.columns[4].render(100001,'display').includes('venta-alta'));
assert.equal(filasTabla.length,0);
assert.equal(excelHabilitado,false);
assert.ok(elemento('resultadosSupervisionSelectorAgencia').innerHTML.includes('&lt;script&gt;'));
assert.ok(!elemento('resultadosSupervisionSelectorAgencia').innerHTML.includes('<script>'));
elemento('tipoBusquedaSupervisionSelectorAgencia').value='nombre';
elemento('buscarSupervisionSelectorAgencia').value='aguila';
emitir('buscarSupervisionSelectorAgencia','input');
assert.ok(elemento('coincidenciasSupervisionSelectorAgencia').textContent.startsWith('1 coincidencias'));
assert.ok(!elemento('resultadosSupervisionSelectorAgencia').innerHTML.includes('Yulissa'));
marcar(aguila,true);
marcar(aguila,true);
assert.equal(filasTabla.length,1);
assert.equal(excelHabilitado,true);
assert.equal(JSON.parse(almacen.get(configuracion.claveSupervision)).length,1);
assert.ok(!almacen.get(configuracion.claveSupervision).includes('periodos'));
elemento('tipoBusquedaSupervisionSelectorAgencia').value='terminal';
elemento('buscarSupervisionSelectorAgencia').value='05378';
elemento('cantidadSupervisionSelectorAgencia').value='1';
emitir('agregarSupervisionSelectorAgencia','click');
assert.equal(filasTabla.length,2);
assert.equal(filasTabla[1].terminal,'05378');
assert.equal(opciones.columns[0].render(filasTabla[1].terminal,'export'),'05378');
const exportacion=opciones.buttons[0].exportOptions;
assert.equal(opciones.buttons[0].extend,'excel');
assert.equal(exportacion.columns(0),true);
assert.equal(exportacion.columns(6),false);
assert.equal(exportacion.orthogonal,'export');
assert.equal(exportacion.modifier.search,'applied');
assert.equal(exportacion.modifier.page,'all');
assert.equal(exportacion.format.header('',0),'Terminal');
assert.equal(exportacion.format.header('',5),'Premios tradicionales · 2026-07');
quitar(aguila);
assert.deepEqual(filasTabla.map(fila=>fila.terminal),['05378']);
marcar(yulissa,false);
assert.equal(filasTabla.length,0);
assert.equal(excelHabilitado,false);
elemento('cantidadSupervisionSelectorAgencia').value='2';
emitir('agregarSupervisionSelectorAgencia','click');
assert.equal(filasTabla.length,2);
assert.equal(new Set(filasTabla.map(fila=>fila.empresa)).size,2);
elemento('buscarSupervisionSelectorAgencia').value='';
elemento('cantidadSupervisionSelectorAgencia').value='100';
emitir('agregarSupervisionSelectorAgencia','click');
assert.equal(filasTabla.length,3);
await window.guardarSeleccionSelectorAgencia();
const guardado=almacen.get(configuracion.claveSupervision);
for (const invalida of ['0','-1','1.5','Infinity']) {
    elemento('cantidadSupervisionSelectorAgencia').value=invalida;
    emitir('agregarSupervisionSelectorAgencia','click');
    assert.ok(elemento('estadoSupervisionSelectorAgencia').textContent.includes('cantidad entera'));
    assert.equal(filasTabla.length,3);
}
await window.guardarSeleccionSelectorAgencia();
const nuevosDatos=configuracion.datos.map(fila=>({...fila,periodos:[{...fila.periodos[0],premios_tradicionales:9876.54}]}));
await montar({...configuracion,datos:nuevosDatos});
assert.equal(filasTabla.length,3);
assert.ok(filasTabla.every(fila=>fila.periodos[0].premios_tradicionales===9876.54));
assert.equal(almacen.get(configuracion.claveSupervision),guardado);
emitir('vaciarSupervisionSelectorAgencia','click');
assert.equal(filasTabla.length,0);
assert.equal(almacen.get(configuracion.claveSupervision),'[]');
await window.guardarSeleccionSelectorAgencia();
await montar({...configuracion,meses:[{codigo:'2026-07'},{codigo:'2026-08'},{codigo:'2026-09'}]});
assert.equal(opciones.columns.length,11);
assert.equal((elemento('tablaSupervisionSelectorAgencia').innerHTML.match(/colspan="2"/g)||[]).length,3);
assert.equal(opciones.buttons[0].exportOptions.format.header('',9),'Premios tradicionales · 2026-09');
assert.ok(!elemento('tablaSupervisionSelectorAgencia').innerHTML.includes('Categoría'));
assert.ok(!elemento('tablaSupervisionSelectorAgencia').innerHTML.includes('Utilidad'));
JS);
    }

    public function test_aisla_usuarios_y_maneja_terminales_fuera_de_consulta_o_almacenamiento_bloqueado(): void
    {
        $this->ejecutarJavascript(<<<'JS'
almacen.set(configuracion.claveSupervision,JSON.stringify([clave(aguila),clave(aguila),clave(yulissa)]));
await montar({...configuracion,datos:[aguila]});
assert.deepEqual(filasTabla.map(fila=>fila.terminal),['05001']);
assert.ok(elemento('estadoSupervisionSelectorAgencia').textContent.includes('1 guardadas fuera'));
assert.ok(elemento('ausentesSupervisionSelectorAgencia').innerHTML.includes('05378'));
quitar(yulissa,'ausentesSupervisionSelectorAgencia');
assert.deepEqual(JSON.parse(almacen.get(configuracion.claveSupervision)),[clave(aguila)]);
await window.guardarSeleccionSelectorAgencia();
await montar({...configuracion,claveSupervision:'supervision:usuario2'});
assert.equal(filasTabla.length,0);
assert.equal(JSON.parse(almacen.get(configuracion.claveSupervision)).length,1);
servidor.delete(configuracion.claveSupervision);
almacen.set(configuracion.claveSupervision,'{invalido');
await montar(configuracion);
assert.equal(filasTabla.length,0);
marcar(aguila,true);
assert.equal(JSON.parse(almacen.get(configuracion.claveSupervision)).length,1);
await window.guardarSeleccionSelectorAgencia();
await montar({...configuracion,datos:[]});
assert.equal(filasTabla.length,0);
assert.equal(excelHabilitado,false);
assert.ok(elemento('ausentesSupervisionSelectorAgencia').innerHTML.includes('05001'));
bloqueado=true;
await montar(configuracion);
marcar(aguila,true);
assert.equal(filasTabla.length,1);
await window.guardarSeleccionSelectorAgencia();
assert.ok(elemento('estadoSupervisionSelectorAgencia').textContent.includes('guardada en el sistema'));
assert.equal(servidor.get(configuracion.claveSupervision).length,1);
bloqueado=false;
await montar({...configuracion,datos:[aguila,yulissa]});
elemento('cantidadSupervisionSelectorAgencia').value='10';
emitir('agregarSupervisionSelectorAgencia','click');
assert.deepEqual(filasTabla.map(fila=>fila.terminal),['05001','05378']);
JS);
    }

    public function test_guardado_coalesce_cambios_y_prioriza_el_sistema_sobre_una_copia_local(): void
    {
        $this->ejecutarJavascript(<<<'JS'
servidor.set(configuracion.claveSupervision,[]);
almacen.set(configuracion.claveSupervision,JSON.stringify([clave(aguila)]));
await montar(configuracion);
assert.equal(filasTabla.length,0);
demorarGuardado=true;
marcar(aguila,true);
marcar(yulissa,true);
quitar(aguila);
assert.equal(filasTabla.length,1);
demorarGuardado=false;
liberarGuardado();
await window.guardarSeleccionSelectorAgencia();
assert.deepEqual(servidor.get(configuracion.claveSupervision),[{empresa:yulissa.empresa,terminal:yulissa.terminal}]);
fallarRed=true;
marcar(aguila,true);
assert.equal(await window.guardarSeleccionSelectorAgencia(),false);
assert.equal(elemento('reintentarGuardadoSelectorAgencia').hidden,false);
assert.ok(elemento('estadoSupervisionSelectorAgencia').textContent.includes('No se guardó'));
assert.equal(servidor.get(configuracion.claveSupervision).length,1);
fallarRed=false;
emitir('reintentarGuardadoSelectorAgencia','click');
assert.equal(await window.guardarSeleccionSelectorAgencia(),true);
assert.equal(servidor.get(configuracion.claveSupervision).length,2);
assert.equal(elemento('reintentarGuardadoSelectorAgencia').hidden,true);
JS);
    }

    public function test_consultar_muestra_sweetalert_evitar_duplicados_y_espera_el_guardado(): void
    {
        $this->ejecutarJavascript(<<<'JS'
usuarioActual=configuracion.claveSupervision;
window.selectorAgencia={...configuracion,seleccionGuardada:[],rutasDisponibles:[]};
iniciarPagina();
const evento={preventDefault(){}};
const primero=emitir('formSelectorAgencia','submit',evento);
const segundo=emitir('formSelectorAgencia','submit',evento);
await Promise.all([primero,segundo]);
await new Promise(resolve=>setTimeout(resolve,70));
assert.equal(cargas,1);
assert.equal(envios,1);
assert.equal(elemento('botonConsultar').disabled,true);
eventosVentana.pageshow();
assert.equal(elemento('botonConsultar').disabled,false);
fallarRed=true;
marcar(aguila,true);
await emitir('formSelectorAgencia','submit',evento);
await new Promise(resolve=>setTimeout(resolve,70));
assert.equal(envios,1);
assert.equal(elemento('botonConsultar').disabled,false);
assert.equal(alertas.at(-1).icon,'error');
fallarRed=false;
await window.guardarSeleccionSelectorAgencia();
await emitir('formSelectorAgencia','submit',evento);
await new Promise(resolve=>setTimeout(resolve,70));
assert.equal(envios,2);
assert.equal(servidor.get(configuracion.claveSupervision).length,1);
JS);
    }

    private function ejecutarJavascript(string $prueba): void
    {
        $preparacion = <<<'JS'
const fs=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');
let elementos=new Map();
let opciones;
let filasTabla=[];
let excelHabilitado=false;
let bloqueado=false;
const almacen=new Map();
const servidor=new Map();
let usuarioActual;
let fallarRed=false;
let demorarGuardado=false;
let liberarGuardado;
let iniciarPagina;
const eventosVentana={};
let envios=0;
let alertas=[];
let cargas=0;
function elemento(id) {
    if(!elementos.has(id)) elementos.set(id,{value:'',innerHTML:'',textContent:'',scrollTop:0,disabled:false,eventos:{},querySelector(){return elemento('botonConsultar');},addEventListener(tipo,fn){this.eventos[tipo]=fn;}});
    return elementos.get(id);
}
global.document={getElementById:elemento,addEventListener(evento,fn){iniciarPagina=fn;}};
global.HTMLFormElement={prototype:{submit(){envios++;}}};
global.fetch=async(url,opciones)=>{
 assert.equal(opciones.method,'POST');
 assert.equal(opciones.headers['X-CSRF-TOKEN'],'csrf-prueba');
 const propietario=usuarioActual;
 const terminales=JSON.parse(opciones.body).terminales;
 if(demorarGuardado) await new Promise(resolve=>{liberarGuardado=resolve;});
 if(fallarRed) throw new Error('Sin conexion');
 servidor.set(propietario,terminales);
 return {ok:true,redirected:false,json:async()=>({guardada:true,terminales})};
};
global.window={addEventListener(evento,fn){eventosVentana[evento]=fn;},Swal:{fire(opciones){alertas.push(opciones);opciones.didOpen?.();},showLoading(){cargas++;},close(){}},localStorage:{getItem(clave){if(bloqueado)throw new Error('Bloqueado');return almacen.get(clave)??null;},setItem(clave,valor){if(bloqueado)throw new Error('Bloqueado');almacen.set(clave,valor);}},jQuery(){return {DataTable(config){opciones=config;filasTabla=[];const api={clear(){filasTabla=[];return api;},rows:{add(filas){filasTabla=[...filas];}},draw(){},button(){return {enable(valor){excelHabilitado=valor;}};}};return api;}};}};
vm.runInThisContext(fs.readFileSync(process.argv[2],'utf8'));
const periodo={ventas_tradicionales:100,premios_tradicionales:25};
const aguila={terminal:'05001',nombre:'Águila <script>',empresa:'Grupo Joselito',ruta:'Norte',clasificacion:{etiqueta:'Rentables',fondo:'#d1e7dd',color:'#0f5132'},periodos:[periodo]};
const yulissa={...aguila,terminal:'05378',nombre:'Yulissa'};
const otra={...yulissa,nombre:'Yulissa Sur',empresa:'Negosur'};
const configuracion={datos:[aguila,yulissa,otra],meses:[{codigo:'2026-07',nombre:'Julio 2026'}],claveSupervision:'supervision:usuario1',urlGuardar:'/guardar',csrfToken:'csrf-prueba'};
function montar(config) {
    elementos=new Map();
    elemento('tipoBusquedaSupervisionSelectorAgencia').value='ambos';
    elemento('cantidadSupervisionSelectorAgencia').value='10';
    usuarioActual=config.claveSupervision;
    const seleccionGuardada=config.seleccionGuardada===undefined?(servidor.get(usuarioActual)??null):config.seleccionGuardada;
    window.iniciarSelectorAgencia({...config,seleccionGuardada});
    return window.guardarSeleccionSelectorAgencia();
}
function emitir(id,tipo,evento={}) {return elemento(id).eventos[tipo](evento);}
function clave(fila) {return encodeURIComponent(JSON.stringify([fila.empresa,fila.terminal]));}
function marcar(fila,checked) {emitir('resultadosSupervisionSelectorAgencia','change',{target:{dataset:{supervisionClave:clave(fila)},checked}});}
function quitar(fila,id='tablaSupervisionSelectorAgencia') {emitir(id,'click',{target:{closest(){return {dataset:{quitarSupervision:clave(fila)}};}}});}
JS;
        $proceso = new Process(['node', '-', public_path('js/pages/selector-agencia.js')]);
        $proceso->setInput("(async () => {\n".$preparacion."\n".$prueba."\n})().catch(error => {console.error(error); process.exitCode = 1;});")->run();
        $this->assertTrue($proceso->isSuccessful(), $proceso->getErrorOutput().$proceso->getOutput());
    }
}
