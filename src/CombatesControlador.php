<?php

declare(strict_types=1);

namespace Complementos\Combates;

use App\Base\ControladorWeb;
use App\Servicios\UsuarioServicio;

/**
 * Visor de combates en los que el jugador asociado al usuario es atacante o defensor.
 */
final class CombatesControlador extends ControladorWeb
{
    private const VISTAS = __DIR__ . '/../vistas/';

    /** GET /combates — elegir universo. */
    public function universos(): void
    {
        $this->requerirNivel(Complemento::NOMBRE, 'acceso');
        $usuario = $this->usuario;
        $universos = [];
        foreach ($this->usuarios->universosDeUsuario((int)$usuario['id']) as $fila) {
            $url = UsuarioServicio::urlDeUniverso($fila['id_universo']);
            $universos[] = [
                'id_universo' => $fila['id_universo'],
                'url' => $url,
                'id_player' => (int)$fila['id_player'],
                'nombre_jugador' => $fila['nombre_jugador'],
                'enlace' => '/combates/' . rawurlencode($url) . '/' . (int)$fila['id_player'],
            ];
        }
        $this->renderizar(self::VISTAS . 'universos.php', ['universos' => $universos], [
            'titulo' => 'Combates',
            'menu_activo' => Complemento::NOMBRE,
        ]);
    }

    /** GET /combates/{url}/{jugador}?desde=YYYY-MM-DD&hasta=YYYY-MM-DD */
    public function listado(string $url, string $jugadorId): void
    {
        $this->requerirNivel(Complemento::NOMBRE, 'acceso');
        $usuario = $this->usuario;
        $idJugador = (int)$jugadorId;

        try {
            $idUniverso = UsuarioServicio::idDeUrl($url);
        } catch (\RuntimeException $e) {
            http_response_code(404);
            $this->renderizar('error', ['mensaje' => 'Universo no válido'], ['titulo' => 'Combates']);
            return;
        }

        if (!$this->tieneNivel(Complemento::NOMBRE, 'operador') && !$this->jugadorAsociado((int)$usuario['id'], $idUniverso, $idJugador)) {
            http_response_code(403);
            $this->renderizar('error', [
                'mensaje' => 'Solo puede consultar los combates del jugador asociado a su usuario en este universo.',
            ], ['titulo' => 'Combates']);
            return;
        }

        [$desde, $hasta, $desdeTexto, $hastaTexto, $error] = $this->rangoFechas();

        $servicio = new CombateServicio($this->pdo);
        $combates = $error === '' ? $servicio->listar($idUniverso, $idJugador, $desde, $hasta) : [];

        $nombreJugador = $this->nombreJugador($idUniverso, $idJugador) ?? ('id ' . $idJugador);

        $this->renderizar(self::VISTAS . 'listado.php', [
            'id_universo' => $idUniverso,
            'url' => $url,
            'id_jugador' => $idJugador,
            'nombre_jugador' => $nombreJugador,
            'puede_otros' => $this->tieneNivel(Complemento::NOMBRE, 'operador'),
            'desde' => $desdeTexto,
            'hasta' => $hastaTexto,
            'error' => $error,
            'combates' => $combates,
            'resumen' => $servicio->resumen($combates),
        ], [
            'titulo' => 'Combates · ' . $idUniverso,
            'menu_activo' => Complemento::NOMBRE,
        ]);
    }

    /** GET /combates/{url}/{jugador}/{id_mensaje} — detalle de una batalla. */
    public function detalle(string $url, string $jugadorId, string $idMensaje): void
    {
        $this->requerirNivel(Complemento::NOMBRE, 'acceso');
        $usuario = $this->usuario;
        $idJugador = (int)$jugadorId;
        try {
            $idUniverso = UsuarioServicio::idDeUrl($url);
        } catch (\RuntimeException $e) {
            http_response_code(404);
            $this->renderizar('error', ['mensaje' => 'Universo no válido'], ['titulo' => 'Combates']);
            return;
        }
        if (!$this->tieneNivel(Complemento::NOMBRE, 'operador') && !$this->jugadorAsociado((int)$usuario['id'], $idUniverso, $idJugador)) {
            http_response_code(403);
            $this->renderizar('error', ['mensaje' => 'Solo puede consultar los combates de su jugador.'], ['titulo' => 'Combates']);
            return;
        }

        $combate = (new CombateServicio($this->pdo))->detalle($idUniverso, $idJugador, (int)$idMensaje);
        if ($combate === null) {
            http_response_code(404);
            $this->renderizar('error', ['mensaje' => 'No existe ese combate o el jugador no participa en él.'], ['titulo' => 'Combates']);
            return;
        }

        $volver = '/combates/' . rawurlencode($url) . '/' . $idJugador;
        if (isset($_GET['desde'], $_GET['hasta'])) {
            $volver .= '?' . http_build_query(['desde' => (string)$_GET['desde'], 'hasta' => (string)$_GET['hasta']]);
        }

        $localizacion = new \App\Servicios\LocalizacionServicio($this->pdo);
        $this->renderizar(self::VISTAS . 'detalle.php', [
            'nombre' => $localizacion->resolutor($idUniverso),
            'con_nombres' => $localizacion->tieneLocalizacion($idUniverso),
            'c' => $combate,
            'id_universo' => $idUniverso,
            'volver' => $volver,
        ], [
            'titulo' => 'Batalla en ' . $combate['coords'] . ' · ' . $idUniverso,
            'menu_activo' => Complemento::NOMBRE,
        ]);
    }

    /** @return array{0:int,1:int,2:string,3:string,4:string} desde, hasta (UNIX), textos y error */
    private function rangoFechas(): array
    {
        $hoy = date('Y-m-d');
        $dias = max(1, (int)($this->configuracionGlobal()['dias_por_defecto'] ?? 1));
        $desdeTexto = (string)($_GET['desde'] ?? date('Y-m-d', strtotime('-' . ($dias - 1) . ' days')));
        $hastaTexto = (string)($_GET['hasta'] ?? $hoy);
        $error = '';

        $valida = fn(string $f) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && strtotime($f) !== false;
        if (!$valida($desdeTexto) || !$valida($hastaTexto)) {
            return [0, 0, $hoy, $hoy, 'Fechas no válidas; use el formato AAAA-MM-DD.'];
        }
        $desde = (int)strtotime($desdeTexto . ' 00:00:00');
        $hasta = (int)strtotime($hastaTexto . ' 23:59:59');
        if ($hasta < $desde) {
            $error = 'La fecha final es anterior a la inicial.';
        }
        return [$desde, $hasta, $desdeTexto, $hastaTexto, $error];
    }

    private function jugadorAsociado(int $idUsuario, string $idUniverso, int $idJugador): bool
    {
        foreach ($this->usuarios->universosDeUsuario($idUsuario) as $fila) {
            if ($fila['id_universo'] === $idUniverso && (int)$fila['id_player'] === $idJugador) {
                return true;
            }
        }
        return false;
    }

    private function nombreJugador(string $idUniverso, int $idJugador): ?string
    {
        $stmt = $this->pdo->prepare('SELECT name FROM players WHERE id_universo = ? AND id_player = ?');
        $stmt->execute([$idUniverso, $idJugador]);
        $nombre = $stmt->fetchColumn();
        return $nombre === false ? null : (string)$nombre;
    }

    private function configuracionGlobal(): array
    {
        $contenedor = $this->contenedor();
        $manifiesto = $contenedor->cargador()->manifiestoDe(Complemento::NOMBRE);
        return $manifiesto !== null ? $contenedor->registro()->configuracion($manifiesto) : [];
    }
}
