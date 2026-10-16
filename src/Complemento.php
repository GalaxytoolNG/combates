<?php

declare(strict_types=1);

namespace Complementos\Combates;

use App\Base\Contenedor;
use App\Complementos\ComplementoBase;

final class Complemento extends ComplementoBase
{
    public const NOMBRE = 'combates';

    public function rutas(): array
    {
        return [
            'GET #^$#' => [CombatesControlador::class, 'universos', 'acceso'],
            'GET #^/([^/]+)/(\d+)$#' => [CombatesControlador::class, 'listado', 'acceso'],
            'GET #^/([^/]+)/(\d+)/(\d+)$#' => [CombatesControlador::class, 'detalle', 'acceso'],
        ];
    }

    /** Da nivel acceso a los usuarios con algún universo asociado. */
    public function instalado(Contenedor $contenedor): void
    {
        $registro = $contenedor->registro();
        $usuarios = $contenedor->pdo()->query('SELECT DISTINCT id_usuario FROM usuarios_universos')->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($usuarios as $idUsuario) {
            $idUsuario = (int)$idUsuario;
            if (!isset($registro->nivelesDeUsuario($idUsuario)[self::NOMBRE])) {
                $registro->establecerNivel($idUsuario, self::NOMBRE, 'acceso');
            }
        }
    }
}
