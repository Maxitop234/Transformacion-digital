<?php

namespace App\Domain\Ventas;

use App\Domain\Catalogo\PerfilPrecio;
use App\Domain\Catalogo\Precios;
use App\Models\Carrito;
use App\Models\Cliente;
use App\Models\ItemCarrito;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Carrito de compras del portal (T-010, API.md §1.3).
 *
 * - Un solo carrito `activo` por cliente (índice único parcial en la base).
 * - El carrito no guarda precios: `resumen()` los pide en cada vista al
 *   servicio de precios con la lista vigente del cliente (RN-01).
 * - Agregar o cambiar cantidades no reserva stock (RN-04): solo se compara
 *   con el disponible para venta y, si lo supera, se ajusta y se avisa.
 * - Toda escritura bloquea la fila del carrito y le actualiza
 *   `actualizado_en`, que marca la última actividad (ver
 *   `MarcarCarritosAbandonados`).
 */
final class Carritos
{
    /** Unidades que se venden fraccionadas; el resto, por unidad entera. */
    private const UNIDADES_FRACCIONABLES = ['kg'];

    public function __construct(private readonly Precios $precios) {}

    /** Carrito activo del cliente, sin crearlo. */
    public function activo(Cliente $cliente): ?Carrito
    {
        return Carrito::where('id_cliente', $cliente->id_cliente)->where('estado', 'activo')->first();
    }

    /**
     * Agrega `cantidad` del producto al carrito activo (lo crea si no existe).
     * Si el producto ya estaba, suma la cantidad en la misma fila.
     *
     * @return string|null aviso para el cliente si la cantidad se ajustó
     */
    public function agregar(PerfilPrecio $perfil, string $sku, string $cantidad): ?string
    {
        $cliente = $this->clienteQueCompra($perfil);
        $producto = $this->productoVendible($sku, $perfil);
        $pedida = $this->centesimas($cantidad, $producto);

        return DB::transaction(function () use ($cliente, $producto, $pedida) {
            $carrito = $this->bloquearActivo($cliente, crear: true);

            $item = $this->item($carrito, $producto);
            $total = ($item ? self::aCentesimas((string) $item->cantidad) : 0) + $pedida;

            return $this->guardarItem($carrito, $producto, $item, $total);
        });
    }

    /** Fija la cantidad de un producto que ya está en el carrito. */
    public function cambiarCantidad(PerfilPrecio $perfil, string $sku, string $cantidad): ?string
    {
        $cliente = $this->clienteQueCompra($perfil);
        $producto = $this->productoVendible($sku, $perfil);
        $nueva = $this->centesimas($cantidad, $producto);

        return DB::transaction(function () use ($cliente, $producto, $nueva) {
            $carrito = $this->bloquearActivo($cliente, crear: false);
            $item = $carrito ? $this->item($carrito, $producto) : null;

            if ($item === null) {
                throw ValidationException::withMessages(['sku' => 'Ese producto no está en tu carrito.']);
            }

            return $this->guardarItem($carrito, $producto, $item, $nueva);
        });
    }

    /** Quita el producto del carrito. No falla si ya no estaba. */
    public function quitar(Cliente $cliente, string $sku): void
    {
        DB::transaction(function () use ($cliente, $sku) {
            $carrito = $this->bloquearActivo($cliente, crear: false);
            $idProducto = Producto::where('sku', $sku)->value('id_producto');

            if ($carrito === null || $idProducto === null) {
                return;
            }

            $borradas = ItemCarrito::where('id_carrito', $carrito->id_carrito)->where('id_producto', $idProducto)->delete();

            if ($borradas > 0) {
                $carrito->touch();
            }
        });
    }

    /**
     * Líneas y totales con los precios vigentes de la lista del cliente.
     * Neto = Σ precio × cantidad (al peso); IVA sobre el neto; total = neto + IVA.
     */
    public function resumen(PerfilPrecio $perfil): ResumenCarrito
    {
        $carrito = $perfil->cliente ? $this->activo($perfil->cliente) : null;

        if ($carrito === null) {
            return new ResumenCarrito(null, [], 0, 0, 0);
        }

        $items = $carrito->itemsCarrito()->with('producto.inventario')->get()
            ->sortBy(fn (ItemCarrito $item) => $item->producto->nombre)
            ->values();
        $precios = $this->precios->precios($items->pluck('id_producto'), $perfil->lista);

        $lineas = [];
        $neto = 0;

        foreach ($items as $item) {
            $producto = $item->producto;
            $precio = $producto->activo ? $precios->get($item->id_producto) : null;
            $subtotal = $precio === null ? null : self::subtotalNeto($precio, (string) $item->cantidad);
            $neto += $subtotal ?? 0;

            $lineas[] = new LineaCarrito(
                producto: $producto,
                cantidad: (string) $item->cantidad,
                precioNeto: $precio,
                subtotalNeto: $subtotal,
                disponible: $producto->disponibleParaVenta(),
            );
        }

        $iva = (int) round($neto * config('negocio.iva_pct') / 100);

        return new ResumenCarrito($carrito, $lineas, $neto, $iva, $neto + $iva);
    }

    /** precio (NUMERIC 2 dec.) × cantidad (NUMERIC 2 dec.) en enteros, redondeado al peso. */
    public static function subtotalNeto(string $precio, string $cantidad): int
    {
        $diezMilesimas = self::aCentesimas($precio) * self::aCentesimas($cantidad);

        return intdiv($diezMilesimas + 5000, 10000);
    }

    private function clienteQueCompra(PerfilPrecio $perfil): Cliente
    {
        if ($perfil->cliente === null) {
            abort(403);
        }

        if (! $perfil->puedeComprar) {
            throw ValidationException::withMessages(['sku' => $perfil->esB2B
                ? 'Tu empresa aún está pendiente de validación: podrás comprar cuando un ejecutivo la valide.'
                : 'Tu cuenta no está habilitada para comprar.']);
        }

        return $perfil->cliente;
    }

    private function productoVendible(string $sku, PerfilPrecio $perfil): Producto
    {
        $producto = Producto::where('sku', $sku)->where('activo', true)->with('inventario')->first();

        if ($producto === null) {
            throw ValidationException::withMessages(['sku' => 'El producto no existe o ya no se vende.']);
        }

        if ($this->precios->precio($producto, $perfil->lista) === null) {
            throw ValidationException::withMessages(['sku' => "{$producto->nombre} no tiene precio en tu lista."]);
        }

        return $producto;
    }

    /** Carrito activo con su fila bloqueada hasta el fin de la transacción. */
    private function bloquearActivo(Cliente $cliente, bool $crear): ?Carrito
    {
        if ($crear) {
            // Si dos peticiones lo crean a la vez, el índice único parcial
            // deja pasar una y la otra toma la existente.
            Carrito::createOrFirst(['id_cliente' => $cliente->id_cliente, 'estado' => 'activo']);
        }

        return Carrito::where('id_cliente', $cliente->id_cliente)->where('estado', 'activo')->lockForUpdate()->first();
    }

    private function item(Carrito $carrito, Producto $producto): ?ItemCarrito
    {
        return ItemCarrito::where('id_carrito', $carrito->id_carrito)->where('id_producto', $producto->id_producto)->first();
    }

    /** Guarda la cantidad ajustada al disponible para venta (RN-04, sin reservar). */
    private function guardarItem(Carrito $carrito, Producto $producto, ?ItemCarrito $item, int $centesimas): ?string
    {
        $disponible = self::aCentesimas((string) $producto->disponibleParaVenta());

        if (! $this->fraccionable($producto)) {
            $disponible -= $disponible % 100;
        }

        if ($disponible <= 0) {
            throw ValidationException::withMessages(['sku' => "{$producto->nombre} no tiene stock disponible."]);
        }

        $aviso = null;

        if ($centesimas > $disponible) {
            $aviso = sprintf(
                '%s: pediste %s %s, pero solo hay %s disponibles. Ajustamos la cantidad.',
                $producto->nombre,
                self::legible($centesimas),
                $producto->unidad_medida,
                self::legible($disponible),
            );
            $centesimas = $disponible;
        }

        $item ??= new ItemCarrito(['id_carrito' => $carrito->id_carrito, 'id_producto' => $producto->id_producto]);
        $item->cantidad = self::decimal($centesimas);
        $item->save();
        $carrito->touch();

        return $aviso;
    }

    private function centesimas(string $cantidad, Producto $producto): int
    {
        $centesimas = self::aCentesimas($cantidad);

        if ($centesimas <= 0) {
            throw ValidationException::withMessages(['cantidad' => 'La cantidad debe ser mayor que cero.']);
        }

        if (! $this->fraccionable($producto) && $centesimas % 100 !== 0) {
            throw ValidationException::withMessages(['cantidad' => "{$producto->nombre} se vende por {$producto->unidad_medida} entera."]);
        }

        return $centesimas;
    }

    private function fraccionable(Producto $producto): bool
    {
        return in_array($producto->unidad_medida, self::UNIDADES_FRACCIONABLES, true);
    }

    /** Las cantidades y precios son NUMERIC(12,2): se opera en centésimas enteras. */
    private static function aCentesimas(string $valor): int
    {
        return (int) round(((float) $valor) * 100);
    }

    private static function decimal(int $centesimas): string
    {
        return number_format($centesimas / 100, 2, '.', '');
    }

    private static function legible(int $centesimas): string
    {
        return rtrim(rtrim(number_format($centesimas / 100, 2, ',', '.'), '0'), ',');
    }
}
