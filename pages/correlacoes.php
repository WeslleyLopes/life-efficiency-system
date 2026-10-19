<?php
require_once __DIR__ . '/../config.php';

$page_title = 'Correlacoes';
$page_sub = 'Como cada pilar impacta os outros · medicao do que funciona';
$active = 'correlacoes';

$corrSono = corr_sono_vs_execucao();
$corrEnergia = corr_energia_vs_treino();
$corrEstudo = corr_estudo_vs_overtrading();
$corrHumor = corr_humor_por_dia_semana();
$corrSonoVar = corr_sono_variacao_diaria();
$planos = corr_planos();

$diasSemana = array(1 => 'Dom', 2 => 'Seg', 3 => 'Ter', 4 => 'Qua', 5 => 'Qui', 6 => 'Sex', 7 => 'Sab');

require_once __DIR__ . '/../includes/header.php';
?>

<section class="grid grid-stats">
    <?php
    $r = $corrSonoVar['r'];
    card_stat(array('titulo' => 'Sono x retorno diario', 'valor' => $r === null ? '—' : number_format($r, 2, ',', '.'), 'sub' => 'correlacao de Pearson', 'cor' => '#38bdf8', 'icone' => 'link'));
    $comT = $corrEnergia['com_treino'];
    card_stat(array('titulo' => 'Energia com treino', 'valor' => ($comT && $comT['media'] !== null) ? number_format((float)$comT['media'], 1, ',', '.') : '—', 'sub' => 'media (1-10)', 'cor' => '#fb923c', 'icone' => 'halter'));
    $ce = $corrEstudo['com_estudo'];
    card_stat(array('titulo' => 'Ops/dia com estudo', 'valor' => ($ce && $ce['media'] !== null) ? number_format((float)$ce['media'], 2, ',', '.') : '—', 'sub' => 'media de operacoes', 'cor' => '#a78bfa', 'icone' => 'livro'));
    $se = $corrEstudo['sem_estudo'];
    card_stat(array('titulo' => 'Ops/dia sem estudo', 'valor' => ($se && $se['media'] !== null) ? number_format((float)$se['media'], 2, ',', '.') : '—', 'sub' => 'media de operacoes', 'cor' => '#f87171', 'icone' => 'raio'));
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <canvas id="chartScatter" height="240"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Sono (h) x retorno diario do trading (%)', $conteudo, '#38bdf8', 'link');
    ?>

    <?php
    ob_start();
    ?>
    <canvas id="chartEnergia" height="240"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Energia: dias com e sem treino', $conteudo, '#fb923c', 'halter');
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <canvas id="chartHumor" height="240"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Humor, energia e estresse por dia da semana', $conteudo, '#2dd4bf', 'grafico');
    ?>

    <?php
    ob_start();
    ?>
    <canvas id="chartOvertrading" height="240"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Estudo presente x volume de operacoes', $conteudo, '#a78bfa', 'cerebro');
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/cadastrosController.php" class="form-grid">
        <input type="hidden" name="acao" value="add_plano">
        <?php
        form_campo(array('name' => 'pilar', 'label' => 'Pilar', 'type' => 'select', 'options' => array('financeiro' => 'Financeiro', 'academico' => 'Academico', 'fisico' => 'Fisico', 'social' => 'Social', 'cross' => 'Cross-pilar')));
        form_campo(array('name' => 'nome', 'label' => 'Nome do experimento', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Operar apenas apos 8h de sono'));
        form_campo(array('name' => 'hipotese', 'label' => 'Hipotese', 'type' => 'textarea', 'placeholder' => 'O que voce acredita que vai melhorar e por que'));
        form_campo(array('name' => 'data_inicio', 'label' => 'Inicio', 'type' => 'date', 'value' => date('Y-m-d'), 'required' => true));
        form_campo(array('name' => 'data_fim', 'label' => 'Fim previsto', 'type' => 'date', 'value' => date('Y-m-d', strtotime('+30 day'))));
        ?>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-primario">Criar experimento</button>
        </div>
    </form>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Novo plano experimental', $conteudo, '#f472b6', 'raio');
    ?>

    <?php
    ob_start();
    if (empty($planos)) {
        echo '<p class="muted">Nenhum plano. Crie experimentos para descobrir o que funciona melhor para voce.</p>';
    }
    foreach ($planos as $p) {
        echo '<div class="plano-item status-' . e($p['status']) . '">';
        echo '<div class="plano-head"><strong>' . e($p['nome']) . '</strong>' . badge(ucfirst($p['status']), $p['status'] === 'ativo' ? '#fbbf24' : ($p['status'] === 'concluido' ? '#22c55e' : '#94a3b8')) . '</div>';
        if ($p['hipotese']) { echo '<p class="plano-hipotese">' . e($p['hipotese']) . '</p>'; }
        echo '<div class="plano-meta"><span>' . e(pilar_labels()[$p['pilar'] === 'cross' ? 'financeiro' : $p['pilar']]) . '</span>'
            . '<span>' . date('d/m', strtotime($p['data_inicio'])) . ($p['data_fim'] ? ' a ' . date('d/m', strtotime($p['data_fim'])) : '') . '</span>'
            . ($p['resultado_nota'] !== null ? '<span>Nota ' . (int)$p['resultado_nota'] . '/10</span>' : '') . '</div>';
        echo '<form method="post" action="/ranksistem/controllers/cadastrosController.php" class="plano-form">'
            . '<input type="hidden" name="acao" value="upd_plano"><input type="hidden" name="id" value="' . (int)$p['id'] . '">'
            . '<input type="number" name="resultado_nota" min="0" max="10" value="' . ($p['resultado_nota'] !== null ? (int)$p['resultado_nota'] : 0) . '" title="Resultado 0-10">'
            . '<select name="status"><option value="ativo"' . ($p['status'] === 'ativo' ? ' selected' : '') . '>Ativo</option><option value="concluido"' . ($p['status'] === 'concluido' ? ' selected' : '') . '>Concluido</option><option value="cancelado"' . ($p['status'] === 'cancelado' ? ' selected' : '') . '>Cancelado</option></select>'
            . '<input type="text" name="observacoes" placeholder="Conclusao" value="' . e($p['observacoes']) . '">'
            . '<button type="submit" class="btn btn-mini btn-neutro">Salvar</button></form>';
        echo '</div>';
    }
    $conteudo = ob_get_clean();
    card_bloco('Planos experimentais (o que funciona)', $conteudo, '#2dd4bf', 'alvo');
    ?>
</section>

<script>
window.RANK_CHARTS = [
    {
        id: 'chartScatter',
        config: {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Sono x retorno',
                    data: <?php echo json_encode(array_map(function ($p) { return array('x' => $p['x'], 'y' => $p['y'], 'data' => $p['data']); }, $corrSonoVar['pontos'])); ?>,
                    backgroundColor: '#38bdf8', pointRadius: 6, pointHoverRadius: 8
                }]
            },
            options: { plugins: { legend: { display: false } }, scales: { x: { title: { display: true, text: 'Horas de sono' } }, y: { title: { display: true, text: 'Retorno (%)' } } } }
        }
    },
    {
        id: 'chartEnergia',
        config: {
            type: 'bar',
            data: {
                labels: ['Com treino', 'Sem treino'],
                datasets: [{ label: 'Energia media', data: [<?php echo ($corrEnergia['com_treino'] && $corrEnergia['com_treino']['media'] !== null) ? (float)$corrEnergia['com_treino']['media'] : 0; ?>, <?php echo ($corrEnergia['sem_treino'] && $corrEnergia['sem_treino']['media'] !== null) ? (float)$corrEnergia['sem_treino']['media'] : 0; ?>], backgroundColor: ['#fb923c', '#475569'], borderRadius: 10 }]
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { suggestedMax: 10 } } }
        }
    },
    {
        id: 'chartHumor',
        config: {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function ($h) use ($diasSemana) { return $diasSemana[(int)$h['dow']]; }, $corrHumor)); ?>,
                datasets: [
                    { label: 'Humor', data: <?php echo json_encode(array_map(function ($h) { return (float)$h['humor']; }, $corrHumor)); ?>, borderColor: '#a78bfa', tension: .35, borderWidth: 2 },
                    { label: 'Energia', data: <?php echo json_encode(array_map(function ($h) { return (float)$h['energia']; }, $corrHumor)); ?>, borderColor: '#fb923c', tension: .35, borderWidth: 2 },
                    { label: 'Estresse', data: <?php echo json_encode(array_map(function ($h) { return (float)$h['estresse']; }, $corrHumor)); ?>, borderColor: '#f87171', tension: .35, borderWidth: 2 }
                ]
            },
            options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { suggestedMin: 0, suggestedMax: 10 } } }
        }
    },
    {
        id: 'chartOvertrading',
        config: {
            type: 'bar',
            data: {
                labels: ['Com estudo', 'Sem estudo'],
                datasets: [{ label: 'Ops/dia', data: [<?php echo ($corrEstudo['com_estudo'] && $corrEstudo['com_estudo']['media'] !== null) ? (float)$corrEstudo['com_estudo']['media'] : 0; ?>, <?php echo ($corrEstudo['sem_estudo'] && $corrEstudo['sem_estudo']['media'] !== null) ? (float)$corrEstudo['sem_estudo']['media'] : 0; ?>], backgroundColor: ['#22c55e', '#f87171'], borderRadius: 10 }]
            },
            options: { plugins: { legend: { display: false } } }
        }
    }
];
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
