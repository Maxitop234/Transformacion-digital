<?php

namespace App\Domain\Ventas;

use App\Domain\Catalogo\PerfilPrecio;
use App\Models\Pedido;
use Illuminate\Validation\ValidationException;

/**
 * Repetir un pedido (T-012, API.md §1.3, B2B): copia sus productos y
 * cantidades al carrito activo a través de `Carritos::agregar`. El carrito no
 * guarda precios, así que lo que se paga es el precio vigente de la lista del
 * cliente, no el pactado en el pedido original (RN-01).
 *
 * Un producto que ya no se vende, no tiene precio en la lista o no tiene stock
 * se omite con un aviso; uno con menos stock se ajusta al disponible (RN-04).
 */
final class RepetirPedido
{
    public function __construct(private readonly Carritos $carritos) {}

    /**
     * @return array{agregados: int, avisos: list<string>}
     */
    public function __invoke(PerfilPrecio $perfil, Pedido $pedido): array
    {
        $agregados = 0;
        $avisos = [];

        foreach ($pedido->detallesPedido()->with('producto')->get() as $detalle) {
            try {
                $aviso = $this->carritos->agregar($perfil, $detalle->producto->sku, (string) $detalle->cantidad);
                $agregados++;

                if ($aviso !== null) {
                    $avisos[] = $aviso;
                }
            } catch (ValidationException $e) {
                // Si la cuenta no puede comprar, ningún producto entrará: se informa una vez.
                if (! $perfil->puedeComprar) {
                    throw $e;
                }

                $avisos[] = collect($e->errors())->flatten()->first();
            }
        }

        return ['agregados' => $agregados, 'avisos' => $avisos];
    }
}
