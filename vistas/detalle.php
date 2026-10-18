<?php

use App\Base\Vista;

/** @var array $c */
/** @var string $id_universo */
/** @var string $volver */
/** @var callable $nombre */
/** @var bool $con_nombres */
$e = fn($t) => Vista::e($t);
$unidad = fn($id) => $con_nombres ? $e($nombre($id)) : '#' . (int)$id;
$n = fn(int $v) => number_format($v, 0, ',', '.');
$nombreLado = ['atacante' => 'Atacante', 'defensor' => 'Defensor'];
$recursosNombre = ['metal' => 'Metal', 'crystal' => 'Cristal', 'deuterium' => 'Deuterio', 'food' => 'Comida'];

/* Orden de presentación de OGame; cualquier id no listado va a "Otros". */
$categorias = [
    'Naves de batalla' => [204, 205, 206, 207, 215, 211, 213, 214, 218, 219],
    'Naves civiles' => [202, 203, 208, 209, 210, 212, 217],
    'Defensa' => [401, 402, 403, 404, 405, 406, 407, 408, 502, 503],
];
$conocidos = array_merge(...array_values($categorias));
$otros = array_values(array_diff($c['ids_unidades'], $conocidos));
if ($otros) {
    $categorias['Otros'] = $otros;
}

$listaUnidades = fn(array $lista) => $lista
    ? implode(', ', array_map(fn($id, $cant) => $n($cant) . ' × ' . $unidad($id), array_keys($lista), $lista))
    : '—';

$atacantes = array_values(array_filter($c['participantes'], fn($p) => $p['lado'] === 'atacante'));
$defensores = array_values(array_filter($c['participantes'], fn($p) => $p['lado'] === 'defensor'));
$numRondas = count($c['rondas']);

/**
 * Unidades de un participante en una ronda: [id => [inicial, destruidas, restantes]].
 * Ronda 0 = inicio (cantidad inicial, nada destruido).
 */
$unidadesEn = function (array $p, int $ronda) use ($c): array {
    $res = [];
    if ($ronda === 0) {
        foreach ($p['inicio'] as $id => $cant) {
            $res[$id] = ['destruidas' => 0, 'restantes' => $cant];
        }
        return $res;
    }
    $datos = $c['rondas'][$ronda - 1]['flotas'][$p['fleetId']] ?? [];
    foreach ($datos as $id => $u) {
        $res[$id] = ['destruidas' => $u['destruidas'], 'restantes' => $u['restantes']];
    }
    return $res;
};
?>
<link rel="stylesheet" href="/complementos/combates/combates.css">
<div class="informe-batalla">
    <div class="ib-cabecera">
        <div>
            <span class="ib-titulo">Informe de batalla <?= $e($c['objetivo_nombre']) ?></span>
            <span class="ib-coords">[<?= $e($c['coords']) ?>]</span><?= $c['objetivo_tipo'] === 'luna' ? ' <span class="secundario">(luna)</span>' : '' ?>
            <div class="secundario">De: Ordenes de la flota · <a href="<?= $e($volver) ?>">◄ volver al listado</a></div>
        </div>
        <div class="ib-fecha"><?= $e(date('d.m.Y H:i:s', $c['timestamp'])) ?></div>
    </div>

    <div class="ib-linea">
        <span>
            Resultado: <strong class="<?= $e($c['resultado']) ?>"><?= $c['ganador'] === 'empate' ? 'Empate' : 'Gana el ' . strtolower($nombreLado[$c['ganador']]) ?></strong>
            <span class="secundario">(tu bando: <?= strtolower($nombreLado[$c['rol']]) ?>)</span>
            <?php if ($c['supremacia'] !== null) : ?>
                · Retirada táctica: <span class="ib-valor"><?= $e(number_format($c['supremacia'], 2, ',', '.')) ?></span>
                <?= $c['retirada'] !== null ? '<span class="ib-aviso">La flota ' . ($c['retirada'] === 'attacker' ? 'atacante' : 'defensora') . ' se ha retirado.</span>' : '<span class="secundario">La flota defensora no ha huido.</span>' ?>
            <?php endif; ?>
            <?php if ($c['probabilidad_luna'] > 0) : ?> · Probabilidad de luna: <span class="ib-valor"><?= (int)$c['probabilidad_luna'] ?> %</span><?php endif; ?>
            <?php if ($c['edlm_destruida']) : ?> · <span class="ib-aviso">Estrella de la muerte destruida</span><?php endif; ?>
        </span>
        <span class="ib-rondas" id="selector-rondas">
            Rondas:
            <a href="#" class="seleccionada" data-ronda="0">Inicio</a>
            <?php for ($i = 1; $i <= $numRondas; $i++) : ?><a href="#" data-ronda="<?= $i ?>"><?= $i ?></a><?php endfor; ?>
        </span>
    </div>

    <div class="ib-columnas">
        <div class="ib-caja">
            <div class="ib-caja-titulo">Botín total<?= $c['botin_porcentaje'] ? ' (' . (int)$c['botin_porcentaje'] . ' %)' : '' ?></div>
            <div class="ib-recursos">
                <?php foreach (['metal', 'crystal', 'deuterium', 'food'] as $r) : if ($r === 'food' && empty($c['botin']['food'])) continue; ?>
                    <span><em><?= $recursosNombre[$r] ?></em> <?= $n($c['botin'][$r] ?? 0) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="ib-caja-titulo">Pérdidas (valor de unidades)</div>
            <div class="ib-recursos">
                <span><em class="lado-atacante">Atacante</em> <?= $n($c['rol'] === 'atacante' ? $c['perdidas_propias'] : $c['perdidas_ajenas']) ?></span>
                <span><em class="lado-defensor">Defensor</em> <?= $n($c['rol'] === 'defensor' ? $c['perdidas_propias'] : $c['perdidas_ajenas']) ?></span>
            </div>
        </div>
        <div class="ib-caja">
            <?php
            $d = $c['escombros_detalle'];
            $suma = fn(array $f) => ($f['metal'] ?? 0) + ($f['crystal'] ?? 0) + ($f['deuterium'] ?? 0);
            $bloques = [
                ['Escombros (nuevos)', $d['total'], $d['naves_necesarias_total']],
                ['Recicl. tras batalla', $d['reciclado'], $d['naves_recicladoras']],
                ['Escombros restantes', $d['restante'], $d['naves_necesarias']],
            ];
            foreach ($bloques as [$titulo, $fila, $naves]) : ?>
                <div class="ib-caja-titulo"><?= $titulo ?>: <?= $n($suma($fila)) ?><?= $naves ? ' ⇒ ' . $e($listaUnidades($naves)) : '' ?></div>
                <div class="ib-recursos">
                    <?php foreach (['metal', 'crystal', 'deuterium'] as $r) : ?><span><em><?= $recursosNombre[$r] ?></em> <?= $n($fila[$r] ?? 0) ?></span><?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="ib-honor">
        Puntos de honor:
        <span class="lado-atacante">(Atacante: <?= $c['honor']['atacante'] >= 0 ? '+' : '' ?><?= $n($c['honor']['atacante']) ?>)</span>
        vs.
        <span class="lado-defensor">(Defensor: <?= $c['honor']['defensor'] >= 0 ? '+' : '' ?><?= $n($c['honor']['defensor']) ?>)</span>
    </div>

    <div class="ib-columnas ib-bandos">
        <?php foreach ([['atacante', $atacantes], ['defensor', $defensores]] as [$lado, $lista]) : ?>
        <div class="ib-bando ib-bando-<?= $lado ?>">
            <?php foreach ($lista as $p) : ?>
            <div class="ib-participante<?= $p['propio'] ? ' propio' : '' ?>">
                <div class="ib-participante-titulo">
                    <span class="lado-<?= $lado ?>"><?= $nombreLado[$lado] ?>:</span>
                    <?= $e($p['nombre']) ?><?= $p['alianza'] !== null ? ' <span class="secundario">[' . $e($p['alianza']) . ']</span>' : '' ?>
                    de <?= $e($p['origen_nombre']) ?> <?= $e($p['origen']) ?>
                    <?php if ($p['propio']) : ?><span class="ib-propio">tú</span><?php endif; ?>
                </div>
                <div class="ib-tecnologias">
                    <span>Clase: <?= $p['clase'] !== null ? '#' . (int)$p['clase'] : '-' ?></span>
                    <span>Armas: <?= isset($p['tecnologias'][109]) ? $p['tecnologias'][109] . ' %' : '-' ?></span>
                    <span>Escudos: <?= isset($p['tecnologias'][110]) ? $p['tecnologias'][110] . ' %' : '-' ?></span>
                    <span>Casco: <?= isset($p['tecnologias'][111]) ? $p['tecnologias'][111] . ' %' : '-' ?></span>
                </div>
                <?php for ($ronda = 0; $ronda <= $numRondas; $ronda++) : $u = $unidadesEn($p, $ronda); ?>
                <div class="ib-ronda" data-ronda="<?= $ronda ?>"<?= $ronda === 0 ? '' : ' hidden' ?>>
                    <?php foreach ($categorias as $titulo => $ids) :
                        $filas = array_filter($ids, fn($id) => isset($u[$id]) && ($u[$id]['restantes'] > 0 || $u[$id]['destruidas'] > 0 || ($p['inicio'][$id] ?? 0) > 0));
                        if (!$filas) continue; ?>
                        <div class="ib-caja-titulo"><?= $titulo ?></div>
                        <table class="ib-unidades">
                        <?php foreach ($filas as $id) : ?>
                            <tr>
                                <td class="ib-unidad"><?= $unidad($id) ?></td>
                                <td class="ib-destruidas"><?= $u[$id]['destruidas'] > 0 ? '−' . $n($u[$id]['destruidas']) : '-' ?></td>
                                <td class="ib-restantes"><?= $n($u[$id]['restantes']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </table>
                    <?php endforeach; ?>
                </div>
                <?php endfor; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!$lista) : ?><div class="ib-participante secundario">Sin datos.</div><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($c['reparadas']) : ?>
    <div class="ib-caja-titulo ib-ancho">Instalaciones defensivas reparadas</div>
    <div class="ib-reparadas">
        <?php foreach ($c['reparadas'] as $id => $cant) : ?><span><?= $unidad($id) ?> <strong><?= $n($cant) ?></strong></span><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php foreach ($c['rondas'] as $r) : ?>
    <div class="ib-estadisticas ib-ronda" data-ronda="<?= (int)$r['numero'] ?>" hidden>
        <?php foreach (['atacante', 'defensor'] as $lado) : $s = $r['estadisticas'][$lado] ?? null; if ($s === null) continue; $otro = $lado === 'atacante' ? 'defensor' : 'atacante'; ?>
            <p>El <span class="lado-<?= $lado ?>"><?= $nombreLado[$lado] ?></span> dispara un total de <?= $n($s['disparos']) ?> veces sobre el <span class="lado-<?= $otro ?>"><?= $nombreLado[$otro] ?></span> con una fuerza conjunta de <?= $n($s['fuerza']) ?>. Los escudos del <span class="lado-<?= $otro ?>"><?= $otro ?></span> absorben <?= $n($s['absorbido']) ?> puntos de daño.</p>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <div class="ib-estadisticas ib-ronda" data-ronda="0">
        <p class="secundario">Seleccione una ronda para ver los disparos y el daño absorbido.<?= $con_nombres ? '' : ' Las unidades se identifican por su id de OGame: sincronice la parte «Localización» del universo para ver sus nombres.' ?></p>
    </div>
    <?php if ($c['hashCode'] !== null) : ?><p class="secundario ib-hash">Hash: <code><?= $e($c['hashCode']) ?></code></p><?php endif; ?>
</div>

<script>
(function () {
    var selector = document.getElementById('selector-rondas');
    if (!selector) { return; }
    selector.querySelectorAll('a[data-ronda]').forEach(function (enlace) {
        enlace.addEventListener('click', function (ev) {
            ev.preventDefault();
            var ronda = enlace.dataset.ronda;
            selector.querySelectorAll('a[data-ronda]').forEach(function (a) { a.classList.toggle('seleccionada', a === enlace); });
            document.querySelectorAll('.ib-ronda').forEach(function (b) { b.hidden = b.dataset.ronda !== ronda; });
        });
    });
})();
</script>
