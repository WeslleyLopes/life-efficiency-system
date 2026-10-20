<?php
require_once __DIR__ . '/../config.php';

$page_title = 'Academico';
$page_sub = 'Cronogramas por materia, modulos e metrica de evolucao';
$active = 'academico';

$materias = ac_materias();
$modulosTodos = ac_modulos();
$conclusao = ac_conclusao_pct();
$consistencia = ac_consistencia();
$retencao = ac_retencao_media();
$minutos7 = ac_minutos_7dias();
$horasSemana = ac_horas_por_semana(8);
$estudos = db_all("SELECT e.*, m.titulo AS modulo_titulo, mt.nome AS materia_nome
    FROM estudos e
    LEFT JOIN modulos m ON m.id = e.modulo_id
    LEFT JOIN materias mt ON mt.id = m.materia_id
    ORDER BY e.data DESC, e.id DESC LIMIT 30");

$modulosPorMateria = array();
foreach ($modulosTodos as $m) {
    $modulosPorMateria[$m['materia_id']][] = $m;
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="grid grid-stats">
    <?php
    card_stat(array('titulo' => 'Conclusao geral', 'valor' => number_format($conclusao, 1, ',', '.') . '%', 'sub' => 'media dos modulos', 'cor' => '#a78bfa', 'icone' => 'livro'));
    card_stat(array('titulo' => 'Consistencia semanal', 'valor' => number_format($consistencia, 1, ',', '.') . '%', 'sub' => 'dias com estudo / 5', 'cor' => '#38bdf8', 'icone' => 'grafico'));
    card_stat(array('titulo' => 'Retencao media', 'valor' => number_format($retencao, 1, ',', '.') . '%', 'sub' => 'testes de absorcao', 'cor' => '#2dd4bf', 'icone' => 'cerebro'));
    card_stat(array('titulo' => 'Horas (7 dias)', 'valor' => number_format($minutos7 / 60, 1, ',', '.') . 'h', 'sub' => 'tempo focado', 'cor' => '#fbbf24', 'icone' => 'raio'));
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <canvas id="chartHoras" height="220"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Horas de estudo por semana', $conteudo, '#a78bfa', 'grafico');
    ?>

    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/academicoController.php" class="form-grid">
        <input type="hidden" name="acao" value="add_modulo">
        <?php
        $optsMat = array();
        foreach ($materias as $m) { $optsMat[$m['id']] = $m['nome']; }
        form_campo(array('name' => 'materia_id', 'label' => 'Materia', 'type' => 'select', 'options' => $optsMat, 'required' => true));
        form_campo(array('name' => 'titulo', 'label' => 'Titulo do modulo', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Fundamentos de Psicologia'));
        form_campo(array('name' => 'ordem', 'label' => 'Ordem', 'type' => 'number', 'min' => 1, 'value' => '1'));
        form_campo(array('name' => 'data_inicio', 'label' => 'Inicio', 'type' => 'date', 'value' => date('Y-m-d')));
        form_campo(array('name' => 'data_fim_prevista', 'label' => 'Fim previsto', 'type' => 'date', 'value' => date('Y-m-d', strtotime('+6 week'))));
        form_campo(array('name' => 'horas_meta', 'label' => 'Horas meta', 'type' => 'number', 'step' => '0.5', 'min' => 1, 'value' => '20', 'required' => true));
        ?>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-primario">Criar modulo</button>
        </div>
    </form>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Novo modulo (ciclo de 4-8 semanas)', $conteudo, '#a78bfa', 'livro');
    ?>
</section>

<section class="grid">
    <?php
    ob_start();
    if (empty($materias)) {
        echo '<p class="muted">Nenhuma materia. Cadastre em Cadastros.</p>';
    }
    foreach ($materias as $mat) {
        $mods = isset($modulosPorMateria[$mat['id']]) ? $modulosPorMateria[$mat['id']] : array();
        echo '<div class="materia-bloco" style="--cor:' . e($mat['cor']) . '">';
        echo '<div class="materia-head"><span class="materia-icone">' . icone('livro', 20) . '</span><h4>' . e($mat['nome']) . '</h4>'
            . '<span class="materia-qtd">' . count($mods) . ' modulos</span>'
            . '<a class="link-mini" style="margin-left:10px" href="/ranksistem/pages/academico.php?q=' . urlencode($mat['nome']) . '">ver sessoes &rarr;</a></div>';
        if (empty($mods)) {
            echo '<p class="muted">Nenhum modulo cadastrado para esta materia.</p>';
        }
        foreach ($mods as $mod) {
            $prog = ac_progresso_modulo($mod);
            $pacing = ac_pacing_index($mod);
            echo '<div class="modulo-item">';
            echo '<div class="modulo-topo"><strong>' . e($mod['titulo']) . '</strong>'
                . '<span class="modulo-horas">' . number_format($mod['minutos_estudados'] / 60, 1, ',', '.') . 'h / ' . number_format((float)$mod['horas_meta'], 1, ',', '.') . 'h</span></div>';
            barra_progresso($prog, $mat['cor'], $pacing !== null ? 'pacing ' . $pacing . '%' : '');
            echo '<div class="modulo-acoes">';
            if ((int)$mod['concluido'] === 1) {
                echo badge('Concluido', '#22c55e');
            }
            echo '<a class="link-mini" href="/ranksistem/controllers/academicoController.php?acao=toggle_modulo&id=' . (int)$mod['id'] . '">' . ((int)$mod['concluido'] === 1 ? 'Reabrir' : 'Marcar concluido') . '</a>';
            echo '<a class="link-mini danger" href="/ranksistem/controllers/academicoController.php?acao=del_modulo&id=' . (int)$mod['id'] . '" onclick="return confirm(\'Remover modulo?\')">Remover</a>';
            echo '</div></div>';
        }
        echo '</div>';
    }
    $conteudo = ob_get_clean();
    card_bloco('Cronograma por materia', $conteudo, '#a78bfa', 'livro');
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/academicoController.php" class="form-grid">
        <input type="hidden" name="acao" value="add_estudo">
        <?php
        $optsMod = array();
        foreach ($modulosTodos as $m) { $optsMod[$m['id']] = $m['materia_nome'] . ' · ' . $m['titulo']; }
        form_campo(array('name' => 'modulo_id', 'label' => 'Modulo', 'type' => 'select', 'options' => $optsMod, 'required' => true));
        form_campo(array('name' => 'data', 'label' => 'Data', 'type' => 'date', 'value' => date('Y-m-d'), 'required' => true));
        form_campo(array('name' => 'minutos', 'label' => 'Minutos focados', 'type' => 'number', 'min' => 1, 'value' => '50', 'required' => true));
        form_campo(array('name' => 'retencao', 'label' => 'Retencao (0-100)', 'type' => 'number', 'min' => 0, 'max' => 100, 'placeholder' => 'Opcional'));
        form_campo(array('name' => 'resumo', 'label' => 'Resumo / fichamento', 'type' => 'textarea', 'placeholder' => 'O que aprendeu nesta sessao'));
        ?>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-primario">Registrar sessao (Pomodoro)</button>
        </div>
    </form>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Registrar estudo', $conteudo, '#38bdf8', 'cerebro');
    ?>

    <?php
    ob_start();
    $rows = array();
    foreach ($estudos as $es) {
        $rows[] = array(
            date('d/m/Y', strtotime($es['data'])),
            e($es['materia_nome'] ? $es['materia_nome'] : '—'),
            e($es['modulo_titulo'] ? $es['modulo_titulo'] : '—'),
            (int)$es['minutos'] . ' min',
            $es['retencao'] !== null ? badge($es['retencao'] . '%', score_cor($es['retencao'])) : '—',
            e($es['resumo'] ? mb_strimwidth($es['resumo'], 0, 60, '...') : '')
        );
    }
    tabela(array('Data', 'Materia', 'Modulo', 'Tempo', 'Retencao', 'Resumo'), $rows, 'Nenhuma sessao registrada.', array('data_table' => true, 'id' => 'tabelaSessoes', 'placeholder' => 'Pesquisar por materia, modulo, resumo...', 'busca' => isset($_GET['q']) ? trim((string)$_GET['q']) : ''));
    $conteudo = ob_get_clean();
    card_bloco('Ultimas sessoes', $conteudo, '#2dd4bf', 'grafico');
    ?>
</section>

<script>
window.RANK_CHARTS = [
    {
        id: 'chartHoras',
        config: {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function ($h) { return $h['rotulo']; }, $horasSemana)); ?>,
                datasets: [{ label: 'Horas', data: <?php echo json_encode(array_map(function ($h) { return (float)$h['horas']; }, $horasSemana)); ?>, backgroundColor: 'rgba(167,139,250,.75)', borderRadius: 8 }]
            },
            options: { plugins: { legend: { display: false } } }
        }
    }
];
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
