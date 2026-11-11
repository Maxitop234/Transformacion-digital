<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Comuna;
use App\Models\EstadoPedido;
use App\Models\EtapaPipeline;
use App\Models\ListaPrecio;
use App\Models\RangoConservacion;
use App\Models\Region;
use App\Models\Rol;
use App\Models\TipoCliente;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Catálogos que el sistema necesita para funcionar, también en producción.
 *
 * Idempotente: cada fila se busca por su clave natural (nombre o código) y se
 * crea o actualiza; correrlo dos veces no duplica nada.
 *
 *     php artisan db:seed --class=CatalogoSeeder
 */
class CatalogoSeeder extends Seeder
{
    /** Correo del usuario técnico que firma los cambios automáticos. */
    public const EMAIL_SISTEMA = 'sistema@cofrial.example';

    /** Lista de precios de los clientes B2C y de los B2B sin validar. */
    public const LISTA_PUBLICA = 'Público general';

    /** Inicio de vigencia de las listas de precios del año. */
    public const INICIO_VIGENCIA = '2026-01-01';

    /** Macrozona sur: regiones y comunas donde Cofrial despacha. */
    private const COMUNAS = [
        'Ñuble' => ['Chillán', 'Chillán Viejo', 'San Carlos', 'Bulnes'],
        'Biobío' => ['Concepción', 'Talcahuano', 'Los Ángeles', 'Chiguayante', 'San Pedro de la Paz'],
        'La Araucanía' => ['Temuco', 'Padre Las Casas', 'Villarrica', 'Pucón', 'Angol', 'Victoria', 'Lautaro', 'Nueva Imperial', 'Pitrufquén', 'Freire', 'Loncoche', 'Carahue'],
        'Los Ríos' => ['Valdivia', 'La Unión', 'Panguipulli', 'Río Bueno'],
        'Los Lagos' => ['Osorno', 'Puerto Montt', 'Puerto Varas', 'Castro'],
    ];

    private const ROLES = [
        'administrador' => 'Todo el sistema: carteras, catálogo, listas de precios y usuarios',
        'vendedor' => 'CRM: su cartera, oportunidades, pedidos telefónicos y órdenes de compra',
        'despachador' => 'CRM: despachos, flota, estados logísticos y alertas de frío',
        'cliente_b2b' => 'Portal: compras institucionales con orden de compra',
        'cliente_b2c' => 'Portal: compras con pago en línea',
    ];

    private const TIPOS_CLIENTE = [
        'B2B' => 'Institucional',
        'B2C' => 'Público general',
    ];

    /** nombre => [orden, es_final] (BUSINESS_RULES.md §4). */
    private const ESTADOS_PEDIDO = [
        'Pendiente' => [1, false],
        'Confirmado' => [2, false],
        'En preparación' => [3, false],
        'En ruta' => [4, false],
        'Entregado' => [5, true],
        'Rechazado' => [6, true],
        'Cancelado' => [7, true],
    ];

    /** nombre => [orden, es_cierre, es_ganada] (DATA_MODEL.md). */
    private const ETAPAS_PIPELINE = [
        'Prospecto nuevo' => [1, false, false],
        'Contactado' => [2, false, false],
        'Calificado' => [3, false, false],
        'Propuesta' => [4, false, false],
        'Negociación' => [5, false, false],
        'Ganado' => [6, true, true],
        'Perdido' => [7, true, false],
    ];

    private const CATEGORIAS = [
        'Cárnicos' => 'Vacuno y cerdo, frescos y congelados',
        'Aves' => 'Pollo y pavo, frescos y congelados',
        'Pescados y mariscos' => 'Productos del mar congelados',
        'Lácteos' => 'Leche, quesos, yogur y mantequilla',
        'Congelados' => 'Verduras, papas y preparados congelados',
        'Helados y postres' => 'Helados y postres congelados',
    ];

    /** nombre => [temp_min_c, temp_max_c]. Rangos de referencia (supuesto). */
    private const RANGOS = [
        'Congelado' => [-25.0, -18.0],
        'Refrigerado' => [0.0, 5.0],
        'Cárnico fresco' => [-1.0, 4.0],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->regionesYComunas();
            $this->porClave(Rol::class, 'nombre', self::ROLES, fn ($d) => ['descripcion' => $d]);
            $this->porClave(TipoCliente::class, 'codigo', self::TIPOS_CLIENTE, fn ($n) => ['nombre' => $n]);
            $this->porClave(EstadoPedido::class, 'nombre', self::ESTADOS_PEDIDO, fn ($v) => ['orden' => $v[0], 'es_final' => $v[1]]);
            $this->porClave(EtapaPipeline::class, 'nombre', self::ETAPAS_PIPELINE, fn ($v) => ['orden' => $v[0], 'es_cierre' => $v[1], 'es_ganada' => $v[2]]);
            $this->porClave(Categoria::class, 'nombre', self::CATEGORIAS, fn ($d) => ['descripcion' => $d]);
            $this->porClave(RangoConservacion::class, 'nombre', self::RANGOS, fn ($v) => ['temp_min_c' => $v[0], 'temp_max_c' => $v[1]]);
            ListaPrecio::firstOrCreate(['nombre' => self::LISTA_PUBLICA], ['vigente_desde' => self::INICIO_VIGENCIA]);
            $this->usuarioSistema();
        });
    }

    private function regionesYComunas(): void
    {
        foreach (self::COMUNAS as $nombreRegion => $comunas) {
            $region = Region::firstOrCreate(['nombre' => $nombreRegion]);

            foreach ($comunas as $comuna) {
                Comuna::firstOrCreate(['id_region' => $region->id_region, 'nombre' => $comuna]);
            }
        }
    }

    /**
     * @param  class-string<Model>  $modelo
     * @param  array<string, mixed>  $filas
     */
    private function porClave(string $modelo, string $clave, array $filas, callable $atributos): void
    {
        foreach ($filas as $valor => $datos) {
            $modelo::updateOrCreate([$clave => $valor], $atributos($datos));
        }
    }

    /**
     * Usuario técnico `sistema`: firma los cambios automáticos en los
     * historiales. Inactivo y con una contraseña aleatoria que nadie conoce,
     * así no sirve para iniciar sesión.
     */
    private function usuarioSistema(): void
    {
        if (Usuario::where('email', self::EMAIL_SISTEMA)->exists()) {
            return;
        }

        Usuario::create([
            'id_rol' => Rol::where('nombre', 'administrador')->value('id_rol'),
            'email' => self::EMAIL_SISTEMA,
            'password_hash' => Str::random(64),
            'activo' => false,
        ]);
    }
}
