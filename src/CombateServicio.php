<?php

declare(strict_types=1);

namespace Complementos\Combates;

/**
 * Consulta de informes de batalla desde el punto de vista de un jugador.
 */
final class CombateServicio
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Combates entre dos instantes en los que el jugador participa como atacante o defensor.
     *
     * @return array<int, array> Lista ordenada por fecha descendente, un combate por hashCode.
     */
    public function listar(string $idUniverso, int $idJugador, int $desde, int $hasta): array
    {
        // Prefiltro en SQL por el id del jugador dentro del JSON de flotas; se confirma al decodificar.
        $stmt = $this->pdo->prepare(
            'SELECT id_mensaje, timestamp, coords, defenderSpaceObject, fleets, result, hashCode
             FROM combats
             WHERE id_universo = ? AND timestamp BETWEEN ? AND ? AND fleets LIKE ?
             ORDER BY timestamp DESC, id_mensaje DESC'
        );
        $stmt->execute([$idUniverso, $desde, $hasta, '%"id":' . $idJugador . ',%']);

        $combates = [];
        $vistos = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $fila) {
            $combate = $this->normalizar($fila, $idJugador);
            if ($combate === null) {
                continue;
            }
            $clave = $combate['hashCode'] ?? ('msg:' . $combate['id_mensaje']);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $combates[] = $combate;
        }

        return $combates;
    }

    /**
     * Detalle completo de un combate (cabecera normalizada, participantes, rondas y resultado),
     * o null si no existe o el jugador no participa.
     */
    public function detalle(string $idUniverso, int $idJugador, int $idMensaje): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id_mensaje, timestamp, coords, defenderSpaceObject, fleets, result, hashCode, combatRounds
             FROM combats WHERE id_universo = ? AND id_mensaje = ?'
        );
        $stmt->execute([$idUniverso, $idMensaje]);
        $fila = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$fila) {
            return null;
        }
        $cabecera = $this->normalizar($fila, $idJugador);
        if ($cabecera === null) {
            return null;
        }

        $flotas = json_decode((string)$fila['fleets'], true) ?: [];
        $rondas = json_decode((string)$fila['combatRounds'], true) ?: [];
        $resultado = json_decode((string)$fila['result'], true) ?: [];

        // Participantes: una columna por flota (en ataque conjunto, varias por jugador).
        $participantes = [];
        $idsUnidades = [];
        foreach ($flotas as $flota) {
            $jugador = $flota['player'] ?? [];
            $tecnologias = [];
            foreach ($flota['combatResearchPercentage'] ?? [] as $t) {
                $tecnologias[(int)($t['id'] ?? 0)] = (int)($t['percentage'] ?? 0);
            }
            $inicio = [];
            foreach ($flota['combatTechnologies'] ?? [] as $u) {
                $id = (int)($u['technologyId'] ?? 0);
                $inicio[$id] = (int)($u['amount'] ?? 0);
                $idsUnidades[$id] = true;
            }
            $participantes[] = [
                'fleetId' => (int)($flota['fleetId'] ?? 0),
                'lado' => ($flota['side'] ?? '') === 'attacker' ? 'atacante' : 'defensor',
                'id' => (int)($jugador['id'] ?? 0),
                'nombre' => (string)($jugador['name'] ?? '?'),
                'alianza' => $jugador['alliance']['tag'] ?? null,
                'clase' => $jugador['classId'] ?? null,
                'propio' => (int)($jugador['id'] ?? 0) === $idJugador,
                'origen' => $this->coordenadas($flota['spaceObject']['coordinates'] ?? null),
                'origen_nombre' => (string)($flota['spaceObject']['name'] ?? ''),
                'tecnologias' => $tecnologias,     // 109 armas, 110 escudos, 111 blindaje → porcentaje
                'inicio' => $inicio,               // technologyId → cantidad inicial
            ];
        }

        // Rondas: por flota, unidades restantes y destruidas en cada ronda.
        $rondasNorm = [];
        foreach ($rondas as $n => $ronda) {
            $estadisticas = [];
            foreach ($ronda['statistics'] ?? [] as $e) {
                $estadisticas[($e['side'] ?? '') === 'attacker' ? 'atacante' : 'defensor'] = [
                    'fuerza' => (int)($e['strength'] ?? 0),
                    'disparos' => (int)($e['hits'] ?? 0),
                    'absorbido' => (int)($e['absorbedDamage'] ?? 0),
                ];
            }
            $porFlota = [];
            foreach ($ronda['fleets'] ?? [] as $f) {
                $unidades = [];
                foreach ($f['technologies'] ?? [] as $u) {
                    $id = (int)($u['technologyId'] ?? 0);
                    $idsUnidades[$id] = true;
                    $unidades[$id] = [
                        'restantes' => (int)($u['remaining'] ?? 0),
                        'destruidas' => (int)($u['destroyed'] ?? 0),
                        'destruidas_total' => (int)($u['destroyedTotal'] ?? 0),
                    ];
                }
                $porFlota[(int)($f['fleetId'] ?? 0)] = $unidades;
            }
            $rondasNorm[] = ['numero' => $n + 1, 'estadisticas' => $estadisticas, 'flotas' => $porFlota];
        }

        $recursos = fn(array $lista, string $clave) => array_combine(
            array_map(fn($r) => (string)($r['resource'] ?? ''), $lista),
            array_map(fn($r) => (int)($r[$clave] ?? 0), $lista)
        ) ?: [];
        $unidadesLista = fn(array $lista) => array_combine(
            array_map(fn($u) => (int)($u['technologyId'] ?? 0), $lista),
            array_map(fn($u) => (int)($u['amount'] ?? 0), $lista)
        ) ?: [];

        $ids = array_keys($idsUnidades);
        sort($ids);

        return $cabecera + [
            'participantes' => $participantes,
            'rondas' => $rondasNorm,
            'ids_unidades' => $ids,
            'escombros_detalle' => [
                'total' => $recursos($resultado['debris']['resources'] ?? [], 'total'),
                'reciclado' => $recursos($resultado['debris']['resources'] ?? [], 'recycled'),
                'restante' => $recursos($resultado['debris']['resources'] ?? [], 'remaining'),
                'naves_necesarias' => $unidadesLista($resultado['debris']['requiredShips'] ?? []),
                'naves_necesarias_total' => $unidadesLista($resultado['debris']['totalRequiredShips'] ?? []),
                'naves_recicladoras' => $unidadesLista($resultado['debris']['shipsUsedForRecycling'] ?? []),
            ],
            'reparadas' => $unidadesLista($resultado['repairedTechnologies'] ?? []),
            'honor' => [
                'atacante' => (int)(array_values(array_filter($resultado['honor'] ?? [], fn($h) => ($h['side'] ?? '') === 'attacker'))[0]['points'] ?? 0),
                'defensor' => (int)(array_values(array_filter($resultado['honor'] ?? [], fn($h) => ($h['side'] ?? '') === 'defender'))[0]['points'] ?? 0),
            ],
            'supremacia' => isset($resultado['tacticalRetreat']['supremacy']) ? (float)$resultado['tacticalRetreat']['supremacy'] : null,
        ];
    }

    /** Totales de una lista de combates ya normalizados. */
    public function resumen(array $combates): array
    {
        $r = [
            'total' => count($combates),
            'victorias' => 0,
            'derrotas' => 0,
            'empates' => 0,
            'como_atacante' => 0,
            'como_defensor' => 0,
            'botin_ganado' => 0,
            'botin_perdido' => 0,
            'perdidas_propias' => 0,
            'perdidas_ajenas' => 0,
        ];
        foreach ($combates as $c) {
            $r[$c['resultado'] === 'victoria' ? 'victorias' : ($c['resultado'] === 'derrota' ? 'derrotas' : 'empates')]++;
            $r[$c['rol'] === 'atacante' ? 'como_atacante' : 'como_defensor']++;
            $r[$c['rol'] === 'atacante' ? 'botin_ganado' : 'botin_perdido'] += $c['botin_total'];
            $r['perdidas_propias'] += $c['perdidas_propias'];
            $r['perdidas_ajenas'] += $c['perdidas_ajenas'];
        }
        return $r;
    }

    private function normalizar(array $fila, int $idJugador): ?array
    {
        $flotas = json_decode((string)$fila['fleets'], true);
        $resultado = json_decode((string)$fila['result'], true);
        $objetivo = json_decode((string)$fila['defenderSpaceObject'], true);
        if (!is_array($flotas) || !is_array($resultado)) {
            return null;
        }

        $rol = null;
        $atacantes = [];
        $defensores = [];
        foreach ($flotas as $flota) {
            $jugador = $flota['player'] ?? [];
            $nombre = (string)($jugador['name'] ?? '?');
            $etiqueta = $jugador['alliance']['tag'] ?? null;
            $entrada = [
                'id' => (int)($jugador['id'] ?? 0),
                'nombre' => $nombre,
                'alianza' => $etiqueta,
                'coords' => $this->coordenadas($flota['spaceObject']['coordinates'] ?? null),
                'propio' => (int)($jugador['id'] ?? 0) === $idJugador,
            ];
            $lado = $flota['side'] ?? '';
            if ($entrada['propio']) {
                $rol = $lado === 'attacker' ? 'atacante' : 'defensor';
            }
            // En un ataque conjunto el mismo jugador puede aparecer con varias flotas: una sola vez.
            $lista = $lado === 'attacker' ? 'atacantes' : 'defensores';
            $existe = false;
            foreach ($$lista as $e) {
                if ($e['id'] === $entrada['id']) {
                    $existe = true;
                    break;
                }
            }
            if (!$existe) {
                $$lista[] = $entrada;
            }
        }
        if ($rol === null) {
            return null;
        }

        $ganador = match ($resultado['winner'] ?? 'none') {
            'attacker' => 'atacante',
            'defender' => 'defensor',
            default => 'empate',
        };
        $resultadoPropio = $ganador === 'empate' ? 'empate' : ($ganador === $rol ? 'victoria' : 'derrota');

        $botin = ['metal' => 0, 'crystal' => 0, 'deuterium' => 0, 'food' => 0];
        foreach ($resultado['loot']['resources'] ?? [] as $recurso) {
            $botin[$recurso['resource'] ?? ''] = (int)($recurso['amount'] ?? 0);
        }
        $botinTotal = $botin['metal'] + $botin['crystal'] + $botin['deuterium'];

        $perdidas = ['attacker' => 0, 'defender' => 0];
        foreach ($resultado['totalValueOfUnitsLost'] ?? [] as $p) {
            $perdidas[$p['side'] ?? ''] = (int)($p['value'] ?? 0);
        }
        $ladoPropio = $rol === 'atacante' ? 'attacker' : 'defender';
        $ladoAjeno = $rol === 'atacante' ? 'defender' : 'attacker';

        $escombros = 0;
        foreach ($resultado['debris']['resources'] ?? [] as $d) {
            $escombros += (int)($d['total'] ?? 0);
        }

        return [
            'id_mensaje' => (int)$fila['id_mensaje'],
            'hashCode' => $fila['hashCode'] ?: null,
            'timestamp' => (int)$fila['timestamp'],
            'coords' => (string)($fila['coords'] ?: $this->coordenadas($objetivo['coordinates'] ?? null)),
            'objetivo_nombre' => (string)($objetivo['name'] ?? ''),
            'objetivo_tipo' => ($objetivo['type'] ?? 'planet') === 'moon' ? 'luna' : 'planeta',
            'rol' => $rol,
            'atacantes' => $atacantes,
            'defensores' => $defensores,
            'ganador' => $ganador,
            'resultado' => $resultadoPropio,
            'botin' => $botin,
            'botin_total' => $botinTotal,
            'botin_porcentaje' => (int)($resultado['loot']['percentage'] ?? 0),
            'perdidas_propias' => $perdidas[$ladoPropio],
            'perdidas_ajenas' => $perdidas[$ladoAjeno],
            'escombros' => $escombros,
            'retirada' => ($resultado['tacticalRetreat']['by'] ?? 'none') !== 'none' ? (string)$resultado['tacticalRetreat']['by'] : null,
            // OGame solo informa de la probabilidad de luna que generó el combate.
            'probabilidad_luna' => (int)($resultado['moonCreation']['chance'] ?? 0),
            'edlm_destruida' => !empty($resultado['deathStarDestroyed']),
        ];
    }

    private function coordenadas(?array $c): string
    {
        if (!is_array($c)) {
            return '';
        }
        return sprintf('%d:%d:%d', (int)($c['galaxy'] ?? 0), (int)($c['system'] ?? 0), (int)($c['position'] ?? 0));
    }
}
