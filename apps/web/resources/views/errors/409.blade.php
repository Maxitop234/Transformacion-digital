@extends('errors.base', ['codigo' => 409, 'titulo' => 'No se pudo completar la operación.', 'detalle' => $exception->getMessage() ?: 'El estado actual no lo permite.'])
