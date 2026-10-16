<?php

use App\Base\Vista;

/** @var string $id_universo */
/** @var string $url */
/** @var int $id_jugador */
/** @var string $nombre_jugador */
/** @var string $desde */
/** @var string $hasta */
/** @var string $error */
/** @var array $combates */
/** @var array $resumen */
$e = fn($t) => Vista::e($t);
/** @var bool $puede_otros */
$n = fn(int $v) => number_format($v, 0, ',', '.');
$base = '/combates/' . rawurlencode($url) . '/' . $id_jugador;
$hoy = date('Y-m-d');
$ayer = date('Y-m-d', strtotime('-1 day'));
$semana = date('Y-m-d', strtotime('-6 days'));
$mes = date('Y-m-d', strtotime('-29 days'));
?>
<link rel="stylesheet" href="/complementos/combates/combates.css">
<?php
$jugadores = function (array $lista) use ($e): string {
    $partes = [];
    foreach ($lista as $j) {
        $texto = $e($j['nombre']) . ($j['alianza'] !== null ? ' <span class="secundario">[' . $e($j['alianza']) . ']</span>' : '');
        $partes[] = $j['propio'] ? '<strong class="propio">' . $texto . '</strong>' : $texto;
    }
    return implode('<br>', $partes);
};
?>
<p class="explicacion">
    Combates de <strong><?= $e($nombre_jugador) ?></strong> en <?= $e($id_universo) ?>.
    <a href="/combates">Cambiar de universo</a>
</p>

<div class="caja formulario filtro-fechas">
    <form method="get" action="<?= $e($base) ?>" class="en-linea">
        <div>
            <label for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="<?= $e($desde) ?>" required>
        </div>
        <div>
            <label for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?= $e($hasta) ?>" required>
        </div>
        <div><button type="submit" class="btn-defecto">Buscar</button></div>
        <div class="atajos">
            <a class="btn" href="<?= $e($base) ?>">Hoy</a>
            <a class="btn" href="<?= $e("$base?desde=$ayer&hasta=$ayer") ?>">Ayer</a>
            <a class="btn" href="<?= $e("$base?desde=$semana&hasta=$hoy") ?>">7 días</a>
            <a class="btn" href="<?= $e("$base?desde=$mes&hasta=$hoy") ?>">30 días</a>
        </div>
    </form>
    <?php if ($error !== '') : ?><p class="mensaje error"><?= $e($error) ?></p><?php endif; ?>
</div>

<div class="listado">
    <div class="titulo">
        <h3>Combates del <?= $e(date('d/m/Y', strtotime($desde))) ?><?= $desde !== $hasta ? ' al ' . $e(date('d/m/Y', strtotime($hasta))) : '' ?></h3>
        <span class="resumen-combates">
            <?= $resumen['total'] ?> combates ·
            <span class="victoria"><?= $resumen['victorias'] ?> victorias</span> ·
            <span class="derrota"><?= $resumen['derrotas'] ?> derrotas</span> ·
            <span class="empate"><?= $resumen['empates'] ?> empates</span>
        </span>
    </div>
    <table class="tabla-combates">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Coordenadas</th>
                <th>Atacante</th>
                <th>Defensor</th>
                <th>Gana</th>
                <th class="num">Botín</th>
                <th class="num">Pérdidas propias</th>
                <th class="num">Pérdidas ajenas</th>
                <th class="num">Escombros</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$combates) : ?>
            <tr><td colspan="9" class="secundario">No hay combates en ese periodo.</td></tr>
        <?php endif; ?>
        <?php foreach ($combates as $c) : ?>
            <tr class="fila-<?= $e($c['resultado']) ?>">
                <td><a class="enlace-detalle" href="<?= $e("$base/{$c['id_mensaje']}?desde=$desde&hasta=$hasta") ?>" title="Ver el detalle de la batalla"><?= $e(date('d/m/Y H:i', $c['timestamp'])) ?></a></td>
                <td class="principal">
                    <?= $e($c['coords']) ?>
                    <span class="secundario"><?= $e($c['objetivo_nombre']) ?><?= $c['objetivo_tipo'] === 'luna' ? ' (luna)' : '' ?></span>
                </td>
                <td><?= $jugadores($c['atacantes']) ?></td>
                <td><?= $jugadores($c['defensores']) ?></td>
                <td class="ganador <?= $e($c['resultado']) ?>">
                    <?php if ($c['ganador'] === 'empate') : ?>
                        Empate
                    <?php else : ?>
                        <?= $c['ganador'] === 'atacante' ? 'Atacante' : 'Defensor' ?>
                    <?php endif; ?>
                    <?php if ($c['retirada'] !== null) : ?><span class="secundario">retirada</span><?php endif; ?>
                    <?php if ($c['probabilidad_luna'] > 0) : ?><span class="secundario">luna <?= (int)$c['probabilidad_luna'] ?> %</span><?php endif; ?>
                </td>
                <td class="num <?= $c['botin_total'] > 0 ? ($c['rol'] === 'atacante' ? 'victoria' : 'derrota') : '' ?>" title="Metal <?= $n($c['botin']['metal']) ?> · Cristal <?= $n($c['botin']['crystal']) ?> · Deuterio <?= $n($c['botin']['deuterium']) ?> (<?= (int)$c['botin_porcentaje'] ?> %)">
                    <?= $c['botin_total'] > 0 ? ($c['rol'] === 'atacante' ? '+' : '−') . $n($c['botin_total']) : '—' ?>
                </td>
                <td class="num <?= $c['perdidas_propias'] > 0 ? 'derrota' : '' ?>"><?= $c['perdidas_propias'] > 0 ? '−' . $n($c['perdidas_propias']) : '—' ?></td>
                <td class="num <?= $c['perdidas_ajenas'] > 0 ? 'victoria' : '' ?>"><?= $c['perdidas_ajenas'] > 0 ? $n($c['perdidas_ajenas']) : '—' ?></td>
                <td class="num"><?= $c['escombros'] > 0 ? $n($c['escombros']) : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($combates) : ?>
        <tfoot>
            <tr>
                <td colspan="5">Totales: <?= $resumen['como_atacante'] ?> como atacante, <?= $resumen['como_defensor'] ?> como defensor</td>
                <td class="num" title="Ganado como atacante <?= $n($resumen['botin_ganado']) ?> · Perdido como defensor <?= $n($resumen['botin_perdido']) ?>">
                    <?= ($resumen['botin_ganado'] - $resumen['botin_perdido'] >= 0 ? '+' : '−') . $n(abs($resumen['botin_ganado'] - $resumen['botin_perdido'])) ?>
                </td>
                <td class="num derrota"><?= $resumen['perdidas_propias'] > 0 ? '−' . $n($resumen['perdidas_propias']) : '—' ?></td>
                <td class="num victoria"><?= $resumen['perdidas_ajenas'] > 0 ? $n($resumen['perdidas_ajenas']) : '—' ?></td>
                <td></td>
            </tr>
        </tfoot>
        <?php endif; ?>
    </table>
    <p class="nota">Gana: en verde cuando el ganador es tu bando (atacante o defensor), en rojo cuando gana el rival, gris en empate. Botín: positivo si lo obtienes como atacante, negativo si lo pierdes como defensor. Pérdidas en valor de unidades. Pulse en la fecha para ver el detalle de la batalla.</p>
</div>
