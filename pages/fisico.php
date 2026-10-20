<?php
require_once __DIR__ . '/../config.php';

$page_title = 'Fisico · Calistenia';
$page_sub = 'Progressao de dificuldade, volume efetivo e sobrecarga progressiva';
$active = 'fisico';

$exercicios = fis_exercicios();
$consistencia = fis_consistencia();
$sobrecarga = fis_sobrecarga_pct();
$prontidao = fis_prontidao_media();
$volAtual = fis_volume_semana(0);
$volSemana = fis_volume_por_semana(8);
$progressao = fis_progressao();
$treinos = fis_treinos(30);
$score = fis_score();

// Dados de exercicios + niveis para o formulario dinamico
$exData = array();
foreach ($exercicios as $ex) {
    $niveis = db_all("SELECT nivel, nome, fator_dificuldade FROM exercicios_niveis WHERE exercicio_id = " . (int)$ex['id'] . " ORDER BY nivel");
    $exData[] = array('id' => (int)$ex['id'], 'nome' => $ex['nome'], 'niveis' => $niveis);
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="grid grid-stats">
    <?php
    card_stat(array('titulo' => 'Consistencia', 'valor' => number_format($consistencia, 1, ',', '.') . '%', 'sub' => 'treinos / 4 na semana', 'cor' => '#fb923c', 'icone' => 'halter'));
    card_stat(array('titulo' => 'Sobrecarga progressiva', 'valor' => ($sobrecarga >= 0 ? '+' : '') . number_format($sobrecarga, 1, ',', '.') . '%', 'sub' => 'vs semana anterior', 'cor' => '#fbbf24', 'icone' => 'raio', 'delta' => $sobrecarga));
    card_stat(array('titulo' => 'Prontidao', 'valor' => number_format($prontidao, 1, ',', '.') . '%', 'sub' => 'energia + recuperacao', 'cor' => '#2dd4bf', 'icone' => 'check'));
    card_stat(array('titulo' => 'Volume da semana', 'valor' => number_format($volAtual, 0, ',', '.'), 'sub' => 'series x reps x dificuldade', 'cor' => '#a78bfa', 'icone' => 'grafico'));
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <canvas id="chartVolume" height="220"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Volume efetivo por semana', $conteudo, '#fb923c', 'grafico');
    ?>

    <?php
    ob_start();
    ?>
    <canvas id="chartProgressao" height="220"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Progressao por exercicio (%)', $conteudo, '#a78bfa', 'alvo');
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/fisicoController.php" id="formTreino" class="form-grid">
        <input type="hidden" name="acao" value="add_treino">
        <?php
        form_campo(array('name' => 'data', 'label' => 'Data', 'type' => 'date', 'value' => date('Y-m-d'), 'required' => true));
        form_campo(array('name' => 'energia_pre', 'label' => 'Energia pre (1-10)', 'type' => 'number', 'min' => 1, 'max' => 10, 'value' => '7'));
        form_campo(array('name' => 'sono_horas', 'label' => 'Sono (h)', 'type' => 'number', 'step' => '0.5', 'min' => 0, 'max' => 24, 'value' => '7'));
        form_campo(array('name' => 'dor_muscular', 'label' => 'Dor muscular (0-10)', 'type' => 'number', 'min' => 0, 'max' => 10, 'value' => '2'));
        form_campo(array('name' => 'observacoes', 'label' => 'Observacoes', 'type' => 'text', 'placeholder' => 'Opcional'));
        ?>
        <div class="campo campo-full">
            <label>Series do treino</label>
            <div id="linhasTreino" class="linhas-treino"></div>
            <button type="button" class="btn btn-neutro btn-mini" id="addLinhaTreino"><?php echo icone('mais', 16); ?> Adicionar exercicio</button>
        </div>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-primario">Registrar treino</button>
        </div>
    </form>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Registrar treino', $conteudo, '#fb923c', 'halter');
    ?>

    <?php
    ob_start();
    ?>
    <div class="progressao-lista">
        <?php foreach ($progressao as $p): ?>
            <div class="progressao-item">
                <div class="progressao-head">
                    <strong><?php echo e($p['nome']); ?></strong>
                    <span class="muted"><?php echo e($p['grupo']); ?></span>
                </div>
                <div class="progressao-niveis">
                    <?php
                    for ($i = 1; $i <= (int)$p['total_niveis']; $i++) {
                        $classe = $i <= (int)$p['nivel_max'] ? 'nivel-on' : 'nivel-off';
                        echo '<span class="nivel-pip ' . $classe . '"></span>';
                    }
                    ?>
                    <span class="progressao-label">Nivel <?php echo (int)$p['nivel_max']; ?>/<?php echo (int)$p['total_niveis']; ?></span>
                </div>
                <span class="progressao-nome"><?php echo e($p['nivel_nome'] ? $p['nivel_nome'] : 'Sem registro'); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Escada de progressao (calistenia)', $conteudo, '#fbbf24', 'chama');
    ?>
</section>

<section class="grid">
    <?php
    ob_start();
    $rows = array();
    foreach ($treinos as $t) {
        $vol = fis_volume_treino($t['id']);
        $rows[] = array(
            date('d/m/Y', strtotime($t['data'])),
            $t['energia_pre'] !== null ? (int)$t['energia_pre'] . '/10' : '—',
            $t['sono_horas'] !== null ? number_format((float)$t['sono_horas'], 1, ',', '.') . 'h' : '—',
            $t['dor_muscular'] !== null ? (int)$t['dor_muscular'] . '/10' : '—',
            '<strong>' . number_format($vol, 0, ',', '.') . '</strong>',
            e($t['observacoes']),
            '<a class="link-del" href="/ranksistem/controllers/fisicoController.php?acao=del_treino&id=' . (int)$t['id'] . '" onclick="return confirm(\'Remover treino?\')">' . icone('lixo', 16) . '</a>'
        );
    }
    tabela(array('Data', 'Energia', 'Sono', 'Dor', 'Volume', 'Obs.', ''), $rows, 'Nenhum treino registrado.', array('data_table' => true, 'id' => 'tabelaTreinos', 'placeholder' => 'Pesquisar por data, energia, observacao...', 'busca' => isset($_GET['q']) ? trim((string)$_GET['q']) : ''));
    $conteudo = ob_get_clean();
    card_bloco('Treinos recentes', $conteudo, '#fb923c', 'halter');
    ?>
</section>

<script>
window.RANK_EXERCICIOS = <?php echo json_encode($exData); ?>;
window.RANK_CHARTS = [
    {
        id: 'chartVolume',
        config: {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function ($v) { return $v['rotulo']; }, $volSemana)); ?>,
                datasets: [{ label: 'Volume', data: <?php echo json_encode(array_map(function ($v) { return $v['volume']; }, $volSemana)); ?>, borderColor: '#fb923c', backgroundColor: 'rgba(251,146,60,.18)', fill: true, tension: .35, borderWidth: 3, pointRadius: 3 }]
            },
            options: { plugins: { legend: { display: false } } }
        }
    },
    {
        id: 'chartProgressao',
        config: {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function ($p) { return $p['nome']; }, $progressao)); ?>,
                datasets: [{ label: 'Progresso (%)', data: <?php echo json_encode(array_map(function ($p) { return $p['pct']; }, $progressao)); ?>, backgroundColor: 'rgba(167,139,250,.75)', borderRadius: 8 }]
            },
            options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { suggestedMax: 100 } } }
        }
    }
];
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
