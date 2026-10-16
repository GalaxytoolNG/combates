<?php

use App\Base\Vista;

/** @var array $universos */
$e = fn($t) => Vista::e($t);
?>
<p class="explicacion">
    Elija el universo. El visor muestra los combates en los que el jugador asociado a su usuario participa
    como atacante o como defensor.
</p>

<?php if (!$universos) : ?>
    <div class="caja">
        <p>No tiene ningún universo asociado. Pida a un administrador que asocie su usuario a su jugador en cada universo.</p>
    </div>
<?php else : ?>
<div class="listado">
    <div class="titulo"><h3>Mis universos</h3></div>
    <table>
        <thead><tr><th>Universo</th><th>Jugador</th><th>Id jugador</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($universos as $u) : ?>
            <tr>
                <td class="principal"><?= $e($u['id_universo']) ?> <span class="secundario"><?= $e($u['url']) ?></span></td>
                <td><?= $u['nombre_jugador'] !== null ? $e($u['nombre_jugador']) : '<span class="aviso">desconocido (sincronice jugadores)</span>' ?></td>
                <td><?= (int)$u['id_player'] ?></td>
                <td class="accion-fila"><a class="btn-defecto" href="<?= $e($u['enlace']) ?>">Ver combates</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
