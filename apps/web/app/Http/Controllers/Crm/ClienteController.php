<?php

namespace App\Http\Controllers\Crm;

use App\Domain\Clientes\RegistrarProspecto;
use App\Domain\Clientes\Rut;
use App\Domain\Clientes\ValidarEmpresa;
use App\Domain\Crm\RegistrarInteraccion;
use App\Domain\Ventas\Montos;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ListaPrecio;
use App\Models\Pedido;
use App\Models\Vendedor;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cartera de clientes del CRM (T-014). El vendedor solo ve la suya (RN-10):
 * un cliente ajeno no se encuentra y responde 404, también al validarlo o
 * al registrarle algo. La lógica vive en `App\Domain\Clientes` y
 * `App\Domain\Crm`.
 */
class ClienteController extends Controller
{
    public const ESTADOS = ['prospecto', 'pendiente_validacion', 'activo', 'inactivo'];

    public const ORIGENES = ['vendedor', 'telefono', 'referido'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Cliente::class);

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', Rule::in(['B2B', 'B2C'])],
            'estado' => ['nullable', Rule::in(self::ESTADOS)],
        ]);

        $clientes = Cliente::visiblePara($request->user())
            ->with(['tipoCliente', 'vendedor'])
            ->when(trim($filtros['q'] ?? ''), function (Builder $q, string $texto) {
                $like = '%'.addcslashes($texto, '%_\\').'%';
                $rut = preg_replace('/[^0-9kK]/', '', $texto);

                $q->where(fn (Builder $q) => $q
                    ->where('nombre_razon_social', 'ilike', $like)
                    ->when($rut !== '', fn (Builder $q) => $q->orWhereRaw("replace(rut, '-', '') ilike ?", ['%'.$rut.'%'])));
            })
            ->when($filtros['tipo'] ?? null, fn (Builder $q, $tipo) => $q->whereHas('tipoCliente', fn (Builder $q) => $q->where('codigo', $tipo)))
            ->when($filtros['estado'] ?? null, fn (Builder $q, $estado) => $q->where('estado_cliente', $estado))
            ->orderBy('nombre_razon_social')
            ->paginate(25)
            ->withQueryString();

        return view('crm.clientes.index', ['clientes' => $clientes, 'estados' => self::ESTADOS]);
    }

    public function show(Request $request, int $cliente): View
    {
        $cliente = $this->clienteVisible($request, $cliente);
        $this->authorize('view', $cliente);

        $cliente->load([
            'contactosCliente' => fn ($q) => $q->orderByDesc('es_principal')->orderBy('nombre'),
            'direccionesDespacho' => fn ($q) => $q->with('comuna')->orderByDesc('activa')->orderBy('alias'),
            'interacciones' => fn ($q) => $q->with(['vendedor', 'oportunidad'])->orderByDesc('fecha_hora')->limit(30),
            'oportunidades' => fn ($q) => $q->with('etapaPipeline')->orderByDesc('fecha_creacion'),
            'tickets' => fn ($q) => $q->orderByDesc('creado_en')->limit(20),
        ]);

        $pedidos = Pedido::whereIn('id_direccion', $cliente->direccionesDespacho->pluck('id_direccion'))
            ->with(['estadoPedido', 'vendedorRegistra', 'detallesPedido'])
            ->orderByDesc('fecha_pedido')
            ->limit(20)
            ->get()
            ->each(fn (Pedido $p) => $p->setAttribute('totales', Montos::totales(
                $p->detallesPedido->map(fn ($d) => [(string) $d->precio_unitario_pactado, (string) $d->cantidad]),
            )));

        return view('crm.clientes.show', [
            'cliente' => $cliente,
            'pedidos' => $pedidos,
            'tiposInteraccion' => RegistrarInteraccion::TIPOS,
            'listas' => ListaPrecio::where('activa', true)->where('nombre', '<>', CatalogoSeeder::LISTA_PUBLICA)->orderBy('nombre')->get()
                ->filter(fn (ListaPrecio $l) => ValidarEmpresa::vigente($l))->values(),
            'vendedores' => Vendedor::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('viewAny', Cliente::class);

        return view('crm.clientes.create', [
            'origenes' => self::ORIGENES,
            'vendedores' => Vendedor::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request, RegistrarProspecto $registrar): RedirectResponse
    {
        $this->authorize('viewAny', Cliente::class);
        $usuario = $request->user();

        $datos = $request->validate([
            'tipo' => ['required', Rule::in(['B2B', 'B2C'])],
            'nombre_razon_social' => ['required', 'string', 'max:150'],
            'rut' => ['nullable', 'string', 'max:12', function ($atributo, $valor, $falla) {
                if (! Rut::esValido($valor)) {
                    $falla('El RUT no es válido.');
                } elseif (Cliente::where('rut', Rut::desdeTexto($valor)->normalizado())->exists()) {
                    $falla('Ya existe un cliente con ese RUT.');
                }
            }],
            'origen' => ['required', Rule::in(self::ORIGENES)],
            'titulo_oportunidad' => ['required', 'string', 'max:150'],
            'id_vendedor' => [Rule::requiredIf($usuario->esAdministrador()), 'nullable', 'integer', Rule::exists('vendedor', 'id_vendedor')->where('activo', true)],
            'contacto_nombre' => ['nullable', 'string', 'max:100'],
            'contacto_cargo' => ['nullable', 'string', 'max:80'],
            'contacto_email' => ['nullable', 'required_with:contacto_nombre', 'email', 'max:120'],
            'contacto_telefono' => ['nullable', 'string', 'max:20'],
        ], [], [
            'nombre_razon_social' => 'nombre o razón social',
            'titulo_oportunidad' => 'título de la oportunidad',
            'id_vendedor' => 'vendedor',
            'contacto_email' => 'correo del contacto',
        ]);

        // El vendedor ingresa prospectos a su propia cartera (RN-10).
        $idVendedor = $usuario->esAdministrador() ? (int) $datos['id_vendedor'] : $usuario->vendedor?->id_vendedor;
        abort_if($idVendedor === null, 403);

        $cliente = $registrar($usuario, $idVendedor, $datos);

        return redirect()->route('crm.clientes.show', $cliente->id_cliente)->with('estado', 'Prospecto ingresado con su oportunidad en «Prospecto nuevo».');
    }

    public function validar(Request $request, int $cliente, ValidarEmpresa $validar): RedirectResponse
    {
        $cliente = $this->clienteVisible($request, $cliente);
        $this->authorize('update', $cliente);

        $datos = $request->validate([
            'id_lista_precio' => ['required', 'integer'],
            'id_vendedor' => ['nullable', 'integer'],
        ], [], ['id_lista_precio' => 'lista de precios', 'id_vendedor' => 'vendedor']);

        $validar($cliente, $request->user(), (int) $datos['id_lista_precio'], isset($datos['id_vendedor']) ? (int) $datos['id_vendedor'] : null);

        return redirect()->route('crm.clientes.show', $cliente->id_cliente)->with('estado', 'Empresa validada: ya ve su lista y puede comprar.');
    }

    /** RN-10: un cliente fuera de la cartera responde 404. */
    private function clienteVisible(Request $request, int $id): Cliente
    {
        return Cliente::visiblePara($request->user())
            ->with(['tipoCliente', 'vendedor', 'listaPrecio'])
            ->findOrFail($id);
    }

    /** Usado por los controladores hijos de la ficha. */
    public static function visibleOFalla(Request $request, int $id): Cliente
    {
        $cliente = Cliente::visiblePara($request->user())->with(['tipoCliente', 'listaPrecio'])->findOrFail($id);

        abort_unless($request->user()->can('update', $cliente), 403);

        return $cliente;
    }
}
