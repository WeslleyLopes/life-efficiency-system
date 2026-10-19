<?php
require_once __DIR__ . '/../config.php';

$page_title = 'Social';
$page_sub = 'Conexoes de qualidade, lazer e recarga mental';
$active = 'social';

$conexoesSemana = soc_conexoes_semana();
$conexoesPct = soc_conexoes_pct();
$recarga = soc_recarga_media();
$qualidade = soc_qualidade_media();
$tempoSemana = soc_tempo_semana();
$tempoPct = soc_tempo_pct();
$score = soc_score();
$porTipo = soc_por_tipo();
$registros = soc_registros(50);
$checkins = db_all("SELECT * FROM social_checkins ORDER BY semana_inicio DESC LIMIT 8");

$rotuloTipo = array('conexao' => 'Conexao', 'lazer' => 'Lazer', 'networking' => 'Networking', 'desconexao' => 'Desconexao');
$corTipo = array('conexao' => '#2dd4bf', 'lazer' => '#a78bfa', 'networking' => '#38bdf8', 'desconexao' => '#fbbf24');

require_once __DIR__ . '/../includes/header.php';
?>

<section class="grid grid-stats">
    <?php
    card_stat(array('titulo' => 'Indice de conexao', 'valor' => number_format($conexoesPct, 1, ',', '.') . '%', 'sub' => $conexoesSemana . ' momentos de qualidade / 3', 'cor' => '#2dd4bf', 'icone' => 'pessoas'));
    card_stat(array('titulo' => 'Recarga mental', 'valor' => number_format($recarga, 1, ',', '.') . '%', 'sub' => 'media das ultimas semanas', 'cor' => '#a78bfa', 'icone' => 'cerebro'));
    card_stat(array('titulo' => 'Qualidade media', 'valor' => number_format($qualidade, 1, ',', '.') . '/5', 'sub' => 'dos momentos', 'cor' => '#38bdf8', 'icone' => 'check'));
    card_stat(array('titulo' => 'Tempo social (7d)', 'valor' => number_format($tempoSemana / 60, 1, ',', '.') . 'h', 'sub' => number_format($tempoPct, 0) . '% da meta (5h)', 'cor' => '#fbbf24', 'icone' => 'sol'));
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <canvas id="chartTipos" height="220"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Distribuicao por tipo', $conteudo, '#2dd4bf', 'pessoas');
    ?>

    <?php
    ob_start();
    ?>
    <canvas id="chartRecarga" height="220"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Recarga mental por semana', $conteudo, '#a78bfa', 'cerebro');
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/socialController.php" class="form-grid">
        <input type="hidden" name="acao" value="add_registro">
        <?php
        form_campo(array('name' => 'data', 'label' => 'Data', 'type' => 'date', 'value' => date('Y-m-d'), 'required' => true));
        form_campo(array('name' => 'tipo', 'label' => 'Tipo', 'type' => 'select', 'options' => array('conexao' => 'Conexao de qualidade', 'lazer' => 'Lazer', 'networking' => 'Networking', 'desconexao' => 'Desconexao / hobby')));
        form_campo(array('name' => 'descricao', 'label' => 'Descricao', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: jantar com amigos sem celular'));
        form_campo(array('name' => 'duracao_min', 'label' => 'Duracao (min)', 'type' => 'number', 'min' => 1, 'value' => '90', 'required' => true));
        form_campo(array('name' => 'qualidade', 'label' => 'Qualidade (1-5)', 'type' => 'number', 'min' => 1, 'max' => 5, 'value' => '4'));
        ?>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-primario">Registrar momento</button>
        </div>
    </form>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Registrar momento social', $conteudo, '#2dd4bf', 'pessoas');
    ?>

    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/socialController.php" class="form-grid">
        <input type="hidden" name="acao" value="add_checkin">
        <?php
        form_campo(array('name' => 'semana_inicio', 'label' => 'Semana (segunda)', 'type' => 'date', 'value' => soc_semana_inicio(), 'required' => true));
        form_campo(array('name' => 'recarga_mental', 'label' => 'Recarga mental (1-10)', 'type' => 'number', 'min' => 1, 'max' => 10, 'value' => '7', 'required' => true));
        form_campo(array('name' => 'observacoes', 'label' => 'Observacoes', 'type' => 'text', 'placeholder' => 'Como foi sua semana emocional?'));
        ?>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-neutro">Salvar check-in semanal</button>
        </div>
    </form>
    <div class="checkin-social-lista">
        <?php
        if (empty($checkins)) {
            echo '<p class="muted">Nenhum check-in semanal ainda.</p>';
        }
        foreach ($checkins as $c) {
            echo '<div class="checkin-social"><span>' . date('d/m/Y', strtotime($c['semana_inicio'])) . '</span>'
                . '<strong style="color:' . score_cor($c['recarga_mental'] * 10) . '">' . (int)$c['recarga_mental'] . '/10</strong></div>';
        }
        ?>
    </div>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Check-in de recarga mental', $conteudo, '#a78bfa', 'cerebro');
    ?>
</section>

<section class="grid">
    <?php
    ob_start();
    $rows = array();
    foreach ($registros as $r) {
        $rows[] = array(
            date('d/m/Y', strtotime($r['data'])),
            badge(isset($rotuloTipo[$r['tipo']]) ? $rotuloTipo[$r['tipo']] : $r['tipo'], isset($corTipo[$r['tipo']]) ? $corTipo[$r['tipo']] : '#38bdf8'),
            e($r['descricao']),
            (int)$r['duracao_min'] . ' min',
            $r['qualidade'] !== null ? $r['qualidade'] . '/5' : '—',
            '<a class="link-del" href="/ranksistem/controllers/socialController.php?acao=del_registro&id=' . (int)$r['id'] . '" onclick="return confirm(\'Remover registro?\')">' . icone('lixo', 16) . '</a>'
        );
    }
    tabela(array('Data', 'Tipo', 'Descricao', 'Duracao', 'Qualidade', ''), $rows);
    $conteudo = ob_get_clean();
    card_bloco('Registros sociais', $conteudo, '#2dd4bf', 'pessoas');
    ?>
</section>

<script>
window.RANK_CHARTS = [
    {
        id: 'chartTipos',
        config: {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_map(function ($t) use ($rotuloTipo) { return isset($rotuloTipo[$t['tipo']]) ? $rotuloTipo[$t['tipo']] : $t['tipo']; }, $porTipo)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_map(function ($t) { return (int)$t['total']; }, $porTipo)); ?>,
                    backgroundColor: <?php echo json_encode(array_map(function ($t) use ($corTipo) { return isset($corTipo[$t['tipo']]) ? $corTipo[$t['tipo']] : '#38bdf8'; }, $porTipo)); ?>,
                    borderWidth: 0
                }]
            },
            options: { plugins: { legend: { position: 'bottom' } }, cutout: '62%' }
        }
    },
    {
        id: 'chartRecarga',
        config: {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function ($c) { return date('d/m', strtotime($c['semana_inicio'])); }, array_reverse($checkins))); ?>,
                datasets: [{ label: 'Recarga', data: <?php echo json_encode(array_map(function ($c) { return (int)$c['recarga_mental']; }, array_reverse($checkins))); ?>, borderColor: '#a78bfa', backgroundColor: 'rgba(167,139,250,.18)', fill: true, tension: .35, borderWidth: 3, pointRadius: 4 }]
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { suggestedMin: 0, suggestedMax: 10 } } }
        }
    }
];
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
