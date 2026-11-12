<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\TransicionesOportunidad as T;
use App\Domain\Ventas\TransicionesPedido;
use App\Models\Cliente;
use App\Models\EtapaPipeline;
use App\Models\HistorialEtapa;
use App\Models\Oportunidad;
use App\Models\Pedido;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreaUsuarios;
use Tests\Support\CreaVentas;
use Tests\TestCase;

/** Pipeline de oportunidades (T-015, BUSINESS_RULES.md §5 y §6). Datos ficticios. */
class PipelineOportunidadesTest extends TestCase
{
    use CreaUsuarios;
    use CreaVentas;
    use RefreshDatabase;

    private Usuario $ana;

    private Usuario $beto;

    private Usuario $empresa;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararVentas();
        $this->productoVenta('TST-OPP', publico: 30000, convenio: 80000, nombre: 'Queso de prueba');
        $this->ana = $this->vendedor('Ana Vendedora');
        $this->beto = $this->vendedor('Beto Vendedor');
        $this->empresa = $this->compradorCon('B2B', $this->ana);
        $this->cliente = $this->empresa->cliente;
        $this->cliente->update(['nombre_razon_social' => 'Casino Ficticio de Ana', 'fecha_validacion' => now()->subMonth()]);
    }

    // --- Alta y alcance (RN-10) ---------------------------------------------------

    public function test_rn09_crear_una_oportunidad_la_deja_en_prospecto_nuevo_con_historial(): void
    {
        $this->actingAs($this->ana)->post('/crm/oportunidades', [
            'id_cliente' => $this->cliente->id_cliente, 'titulo' => 'Convenio de lácteos', 'origen' => 'telefono', 'monto_estimado' => '1200000',
        ])->assertRedirect();

        $oportunidad = Oportunidad::sole();
        $this->assertSame([T::PROSPECTO, 'telefono', $this->ana->vendedor->id_vendedor, '1200000.00'], [
            $oportunidad->etapaPipeline->nombre, $oportunidad->origen, $oportunidad->id_vendedor, (string) $oportunidad->monto_estimado,
        ]);
        $this->assertSame($this->ana->id_usuario, HistorialEtapa::where('id_oportunidad', $oportunidad->id_oportunidad)->sole()->id_usuario);

        $this->actingAs($this->ana)->post('/crm/oportunidades', ['id_cliente' => $this->cliente->id_cliente, 'titulo' => 'X', 'origen' => 'portal'])
            ->assertSessionHasErrors('origen');
    }

    public function test_rn10_el_vendedor_ve_solo_su_pipeline(): void
    {
        $deAna = $this->oportunidadEn(T::PROSPECTO, 'Oportunidad de Ana');
        $clienteDeBeto = $this->clienteDe($this->beto, 'Cliente de Beto');
        $deBeto = $this->oportunidadEn(T::PROSPECTO, 'Oportunidad de Beto', $clienteDeBeto);

        $this->actingAs($this->ana)->get('/crm/oportunidades')->assertOk()
            ->assertSee('Oportunidad de Ana')->assertDontSee('Oportunidad de Beto')->assertDontSee('Cliente de Beto');
        $this->actingAs($this->ana)->get("/crm/oportunidades/{$deBeto->id_oportunidad}")->assertNotFound();
        $this->actingAs($this->ana)->patch("/crm/oportunidades/{$deBeto->id_oportunidad}/etapa", ['etapa' => T::PERDIDO, 'motivo' => 'x'])->assertNotFound();
        $this->actingAs($this->ana)->post('/crm/oportunidades', ['id_cliente' => $clienteDeBeto->id_cliente, 'titulo' => 'Intrusa', 'origen' => 'vendedor'])->assertNotFound();

        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->get('/crm/oportunidades')->assertOk()
            ->assertSee('Oportunidad de Ana')->assertSee('Oportunidad de Beto');
        $this->actingAs($this->usuario(Rol::DESPACHADOR))->get('/crm/oportunidades')->assertForbidden();
        $this->assertNotNull($deAna);
    }

    // --- Condiciones de cada transición (BUSINESS_RULES.md §5) -----------------------

    public function test_prospecto_a_contactado_exige_una_interaccion(): void
    {
        $oportunidad = $this->oportunidadEn(T::PROSPECTO);

        $this->mover($oportunidad, T::CONTACTADO)->assertSessionHasErrors(['etapa' => 'Registra al menos una interacción con el cliente antes de marcarla como contactada.']);
        $this->assertEtapa($oportunidad, T::PROSPECTO);

        $this->interaccion($oportunidad);
        $this->mover($oportunidad, T::CONTACTADO, ['comentario' => 'Primera llamada'])->assertSessionHasNoErrors();

        $this->assertEtapa($oportunidad, T::CONTACTADO);
        $ultimo = HistorialEtapa::where('id_oportunidad', $oportunidad->id_oportunidad)->orderByDesc('id_historial')->first();
        $this->assertSame([$this->ana->id_usuario, 'Primera llamada'], [$ultimo->id_usuario, $ultimo->comentario]);
    }

    public function test_contactado_a_calificado_exige_monto_estimado(): void
    {
        $oportunidad = $this->oportunidadEn(T::CONTACTADO);

        $this->mover($oportunidad, T::CALIFICADO)->assertSessionHasErrors(['etapa' => 'Define el monto estimado antes de calificarla.']);
        $this->assertEtapa($oportunidad, T::CONTACTADO);

        $this->actingAs($this->ana)->patch("/crm/oportunidades/{$oportunidad->id_oportunidad}", ['titulo' => 'Convenio', 'monto_estimado' => '900000'])->assertRedirect();
        $this->mover($oportunidad, T::CALIFICADO)->assertSessionHasNoErrors();
        $this->assertEtapa($oportunidad, T::CALIFICADO);
    }

    public function test_calificado_a_propuesta_exige_cliente_validado_con_lista(): void
    {
        $this->cliente->update(['estado_cliente' => 'pendiente_validacion', 'fecha_validacion' => null]);
        $oportunidad = $this->oportunidadEn(T::CALIFICADO);

        $this->mover($oportunidad, T::PROPUESTA)->assertSessionHasErrors(['etapa' => 'El cliente aún no está validado: valida la empresa antes de enviar la propuesta.']);
        $this->assertEtapa($oportunidad, T::CALIFICADO);

        $this->cliente->update(['estado_cliente' => 'activo', 'fecha_validacion' => now()]);
        $this->mover($oportunidad, T::PROPUESTA)->assertSessionHasNoErrors();
        $this->assertEtapa($oportunidad, T::PROPUESTA);
    }

    public function test_propuesta_a_negociacion_exige_una_respuesta_posterior_a_la_propuesta(): void
    {
        $this->interaccion(null); // anterior a la propuesta: no cuenta
        $this->travel(5)->minutes();
        $oportunidad = $this->oportunidadEn(T::PROPUESTA);

        $this->mover($oportunidad, T::NEGOCIACION)->assertSessionHasErrors(['etapa' => 'Registra la respuesta del cliente a la propuesta (una interacción) antes de pasar a negociación.']);

        $this->travel(5)->minutes();
        $this->interaccion($oportunidad);
        $this->mover($oportunidad, T::NEGOCIACION)->assertSessionHasNoErrors();
        $this->assertEtapa($oportunidad, T::NEGOCIACION);
    }

    public function test_propuesta_o_negociacion_a_ganado_exige_un_pedido_vinculado(): void
    {
        $oportunidad = $this->oportunidadEn(T::NEGOCIACION);

        $this->mover($oportunidad, T::GANADO)->assertSessionHasErrors(['etapa' => 'Para ganarla debe haber un pedido vinculado: elige un pedido del cliente.']);
        $this->assertEtapa($oportunidad, T::NEGOCIACION);

        // Un pedido de otro cliente no sirve.
        $ajeno = $this->pedidoB2B($this->compradorCon('B2B', $this->ana));
        $this->mover($oportunidad, T::GANADO, ['id_pedido' => $ajeno->id_pedido])->assertSessionHasErrors('id_pedido');
        $this->assertNull($ajeno->refresh()->id_oportunidad);

        $pedido = $this->pedidoB2B($this->empresa);
        $this->mover($oportunidad, T::GANADO, ['id_pedido' => $pedido->id_pedido])->assertSessionHasNoErrors();

        $oportunidad->refresh();
        $this->assertEtapa($oportunidad, T::GANADO);
        $this->assertNotNull($oportunidad->fecha_cierre);
        $this->assertSame($oportunidad->id_oportunidad, $pedido->refresh()->id_oportunidad);
    }

    public function test_perder_sin_motivo_se_rechaza(): void
    {
        $oportunidad = $this->oportunidadEn(T::CALIFICADO);

        $this->mover($oportunidad, T::PERDIDO)->assertSessionHasErrors(['motivo' => 'Indica el motivo de la pérdida.']);
        $this->mover($oportunidad, T::PERDIDO, ['motivo' => '   '])->assertSessionHasErrors('motivo');
        $this->assertEtapa($oportunidad, T::CALIFICADO);

        $this->mover($oportunidad, T::PERDIDO, ['motivo' => 'Eligió otro proveedor'])->assertSessionHasNoErrors();
        $oportunidad->refresh();
        $this->assertEtapa($oportunidad, T::PERDIDO);
        $this->assertSame('Eligió otro proveedor', $oportunidad->motivo_perdida);
        $this->assertNotNull($oportunidad->fecha_cierre);
    }

    public function test_no_se_puede_mover_ni_editar_una_oportunidad_cerrada(): void
    {
        foreach ([T::GANADO, T::PERDIDO] as $cierre) {
            $oportunidad = $this->oportunidadEn($cierre);

            foreach ([T::PROSPECTO, T::NEGOCIACION, T::PERDIDO, T::GANADO] as $destino) {
                $this->mover($oportunidad, $destino, ['motivo' => 'Reabrir'])->assertStatus(409);
            }

            $this->actingAs($this->ana)->patch("/crm/oportunidades/{$oportunidad->id_oportunidad}", ['titulo' => 'Otro título'])->assertStatus(409);
            $this->assertEtapa($oportunidad, $cierre);
        }
    }

    public function test_no_se_puede_saltar_etapas(): void
    {
        $oportunidad = $this->oportunidadEn(T::PROSPECTO);
        $this->interaccion($oportunidad);

        $this->mover($oportunidad, T::PROPUESTA)->assertStatus(409);
        $this->mover($oportunidad, T::GANADO)->assertStatus(409);
        $this->assertEtapa($oportunidad, T::PROSPECTO);
    }

    // --- Vínculo automático con pedidos (§6) ---------------------------------------------

    public function test_un_pedido_b2b_gana_la_oportunidad_en_propuesta_y_el_vendedor_puede_deshacerlo(): void
    {
        $oportunidad = $this->oportunidadEn(T::PROPUESTA);
        $pedido = $this->pedidoB2B($this->empresa);

        $this->actingAs($this->ana)->post("/crm/pedidos/{$pedido->id_pedido}/confirmar-oc")->assertRedirect();

        $this->assertEtapa($oportunidad, T::GANADO);
        $this->assertSame($oportunidad->id_oportunidad, $pedido->refresh()->id_oportunidad);
        $ultimo = HistorialEtapa::where('id_oportunidad', $oportunidad->id_oportunidad)->orderByDesc('id_historial')->first();
        $this->assertSame([TransicionesPedido::idUsuarioSistema(), "Ganada con el pedido N.º {$pedido->id_pedido}"], [$ultimo->id_usuario, $ultimo->comentario]);

        // Otro vendedor no la ve; la dueña la deshace con motivo.
        $this->actingAs($this->beto)->post("/crm/oportunidades/{$oportunidad->id_oportunidad}/deshacer-vinculo", ['motivo' => 'x'])->assertNotFound();
        $this->actingAs($this->ana)->get("/crm/oportunidades/{$oportunidad->id_oportunidad}")->assertOk()->assertSee('data-deshacer', false);
        $this->actingAs($this->ana)->post("/crm/oportunidades/{$oportunidad->id_oportunidad}/deshacer-vinculo", ['motivo' => 'Era una recompra del convenio anterior'])
            ->assertRedirect();

        $oportunidad->refresh();
        $this->assertEtapa($oportunidad, T::PROPUESTA);
        $this->assertNull($oportunidad->fecha_cierre);
        $this->assertNull($pedido->refresh()->id_oportunidad);
        $this->assertStringContainsString('Vínculo deshecho', HistorialEtapa::where('id_oportunidad', $oportunidad->id_oportunidad)->orderByDesc('id_historial')->value('comentario'));

        // Un cierre manual no se «deshace»: no fue el sistema.
        $this->mover($oportunidad, T::GANADO, ['id_pedido' => $pedido->id_pedido])->assertSessionHasNoErrors();
        $this->actingAs($this->ana)->post("/crm/oportunidades/{$oportunidad->id_oportunidad}/deshacer-vinculo", ['motivo' => 'x'])->assertStatus(409);
    }

    public function test_una_recompra_b2b_sin_oportunidad_abierta_no_crea_una_nueva(): void
    {
        $this->oportunidadEn(T::GANADO, 'Convenio ya ganado');
        $this->oportunidadEn(T::CALIFICADO, 'Ampliación en evaluación'); // abierta, pero no en Propuesta ni Negociación
        $recompra = $this->pedidoB2B($this->empresa);

        $this->actingAs($this->ana)->post("/crm/pedidos/{$recompra->id_pedido}/confirmar-oc")->assertRedirect();

        $this->assertNull($recompra->refresh()->id_oportunidad);
        $this->assertSame(2, Oportunidad::count());
        $this->assertSame(1, Oportunidad::whereHas('etapaPipeline', fn ($q) => $q->where('nombre', T::GANADO))->count());
    }

    // --- Vista -------------------------------------------------------------------------------

    public function test_el_pipeline_muestra_columnas_con_monto_y_dias_en_la_etapa(): void
    {
        $oportunidad = $this->oportunidadEn(T::CALIFICADO, 'Convenio casino', monto: 750000);
        $this->travel(3)->days();

        $respuesta = $this->actingAs($this->ana)->get('/crm/oportunidades')->assertOk();
        foreach (EtapaPipeline::orderBy('orden')->pluck('nombre') as $etapa) {
            $respuesta->assertSee("data-etapa=\"{$etapa}\"", false);
        }
        $respuesta->assertSee('Convenio casino')->assertSee('Casino Ficticio de Ana')->assertSee('$750.000')
            ->assertSee('data-dias-en-etapa="3"', false);

        $this->actingAs($this->ana)->get("/crm/oportunidades/{$oportunidad->id_oportunidad}")->assertOk()
            ->assertSee('data-destino="Propuesta"', false)->assertSee('data-destino="Perdido"', false);
    }

    // --- Apoyo ---------------------------------------------------------------------------------

    /** Oportunidad del cliente puesta directamente en una etapa, con su historial de entrada. */
    private function oportunidadEn(string $etapa, string $titulo = 'Oportunidad de prueba', ?Cliente $cliente = null, ?int $monto = null): Oportunidad
    {
        $cliente ??= $this->cliente;
        $idEtapa = EtapaPipeline::where('nombre', $etapa)->value('id_etapa');
        $oportunidad = Oportunidad::create([
            'id_cliente' => $cliente->id_cliente,
            'id_vendedor' => $cliente->id_vendedor,
            'id_etapa' => $idEtapa,
            'titulo' => $titulo,
            'origen' => 'vendedor',
            'monto_estimado' => $monto,
            'fecha_creacion' => now(),
            'fecha_cierre' => in_array($etapa, [T::GANADO, T::PERDIDO], true) ? now() : null,
        ]);
        HistorialEtapa::create(['id_oportunidad' => $oportunidad->id_oportunidad, 'id_etapa' => $idEtapa, 'id_usuario' => $this->ana->id_usuario, 'fecha_hora' => now()]);

        return $oportunidad;
    }

    private function mover(Oportunidad $oportunidad, string $etapa, array $extra = []): TestResponse
    {
        return $this->actingAs($this->ana)->patch("/crm/oportunidades/{$oportunidad->id_oportunidad}/etapa", ['etapa' => $etapa, ...$extra]);
    }

    private function interaccion(?Oportunidad $oportunidad): void
    {
        $this->actingAs($this->ana)->post("/crm/clientes/{$this->cliente->id_cliente}/interacciones", array_filter([
            'tipo' => 'llamada', 'resumen' => 'Conversación de prueba', 'id_oportunidad' => $oportunidad?->id_oportunidad,
        ]))->assertSessionHasNoErrors();
    }

    private function pedidoB2B(Usuario $cuenta): Pedido
    {
        $this->actingAs($cuenta)->post('/carrito/items', ['sku' => 'TST-OPP', 'cantidad' => 2])->assertSessionHasNoErrors();
        $this->actingAs($cuenta)->post('/pedidos', $this->datosCheckout($cuenta))->assertSessionHasNoErrors();

        return Pedido::latest('id_pedido')->firstOrFail();
    }

    private function assertEtapa(Oportunidad $oportunidad, string $etapa): void
    {
        $this->assertSame($etapa, $oportunidad->refresh()->etapaPipeline->nombre);
    }
}
