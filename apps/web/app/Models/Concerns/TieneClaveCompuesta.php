<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Clave primaria compuesta para Eloquent (`detalle_pedido`, `item_carrito`,
 * `detalle_lista_precio`, `despacho_pedido`).
 *
 * El modelo declara `$primaryKey` como lista de columnas y `$incrementing =
 * false`. Guardar y borrar filtran por todas las columnas de la clave; para
 * buscar una fila se usa `where()` sobre ellas (no `find()`).
 */
trait TieneClaveCompuesta
{
    /**
     * @return list<string>
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function getKey()
    {
        $clave = [];

        foreach ($this->getKeyName() as $columna) {
            $clave[$columna] = $this->getAttribute($columna);
        }

        return $clave;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function setKeysForSaveQuery($query)
    {
        foreach ($this->getKeyName() as $columna) {
            $query->where($columna, '=', $this->getKeyForSaveQueryColumn($columna));
        }

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function setKeysForSelectQuery($query)
    {
        return $this->setKeysForSaveQuery($query);
    }

    protected function getKeyForSaveQueryColumn(string $columna): mixed
    {
        return $this->original[$columna] ?? $this->getAttribute($columna);
    }
}
