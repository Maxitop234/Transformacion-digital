<?php

namespace App\Domain\Ia;

use RuntimeException;

/** La IA no respondió algo utilizable: sin clave, error HTTP, tiempo agotado o JSON inválido. */
class IaNoDisponible extends RuntimeException {}
