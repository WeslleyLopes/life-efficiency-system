<?php
require_once __DIR__ . '/../config.php';

$page_title = 'Visao Geral';
$page_sub = 'Diagnostico, evolucao e prioridade da proxima melhoria';
$active = 'dashboard';

salvar_pilar_scores();
diag_gerar();

$scores = score_pilares();
$global = score_global($scores);
$cores = pilar_cores();
$labels = pilar_labels();
$icones = pilar_icones();

// KPIs executivos
$pesos = score_pesos();
$metasStats = metas_stats();
$consistencia = consistencia_global();
$qualidade = qualidade_dados(30);
$tendencia = score_tendencia(7);
$resumoDiag = diag_resumo();
$proximas = metas_proximas(4);

// Delta do dia vs ultimo registro anterior
$historico = pilar_scores_historico(60);
$porPilar = array('financeiro' => array(), 'academico' => array(), 'fisico' => array(), 'social' => array());
$datas = array();
foreach ($historico as $h) {
    $porPilar[$h['pilar']][] = $h;
    if (!in_array($h['data'], $datas, true)) { $datas[] = $h['data']; }
}
sort($datas);

$delta = array();
foreach ($porPilar as $pilar => $serie) {
    $n = count($serie);
    if ($n >= 2) {
        $delta[$pilar] = round($serie[$n - 1]['score'] - $serie[$n - 2]['score'], 1);
    } else {
        $delta[$pilar] = null;
    }
}

// Serie global por dia (media ponderada)
$somaPesos = array_sum($pesos) ?: 1;
$mapa = array();
foreach ($historico as $h) {
    if (!isset($mapa[$h['data']])) { $mapa[$h['data']] = array(); }
    $mapa[$h['data']][$h['pilar']] = (float)$h['score'];
}
$labelsDatas = array();
$serieGlobal = array();
$seriesPilar = array('financeiro' => array(), 'academico' => array(), 'fisico' => array(), 'social' => array());
foreach ($mapa as $data => $vals) {
    $labelsDatas[] = date('d/m', strtotime($data));
    $soma = 0;
    foreach ($scores as $pilar => $null) {
        $v = isset($vals[$pilar]) ? $vals[$pilar] : 0;
        $seriesPilar[$pilar][] = round($v, 1);
        $soma += $v * $pesos[$pilar];
    }
    $serieGlobal[] = round($soma / $somaPesos, 1);
}

// Correlacoes resumidas
$corrSono = corr_sono_vs_execucao();
$corrEnergia = corr_energia_vs_treino();

$checkinHoje = db_one("SELECT * FROM checkin_diario WHERE data = CURDATE()");

require_once __DIR__ . '/../includes/header.php';
?>

<section class="grid grid-hero">
    <div class="card card-global" style="--cor:<?php echo score_cor($global); ?>">
        <span class="global-titulo">Eficiencia global</span>
        <?php gauge($global, score_cor($global), 'da meta', 200); ?>
        <div class="tendencia tendencia-<?php echo e($tendencia['classe']); ?>">
            <?php echo $tendencia['icone']; ?> <?php echo e($tendencia['rotulo']); ?>
            <?php if ($tendencia['delta'] !== null): ?>
                <span>(<?php echo ($tendencia['delta'] >= 0 ? '+' : '') . number_format($tendencia['delta'], 1, ',', '.'); ?> pts vs 7 dias anteriores)</span>
            <?php endif; ?>
        </div>
        <div class="global-pesos">
            <?php foreach ($scores as $pilar => $v): ?>
                <span class="peso-chip" style="--cor:<?php echo $cores[$pilar]; ?>">
                    <i></i><?php echo e($labels[$pilar]); ?> <?php echo (int)$pesos[$pilar]; ?>%
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="grid grid-pilares">
        <?php foreach ($scores as $pilar => $v): ?>
            <div class="pilar-coluna">
                <?php
                card_stat(array(
                    'titulo' => $labels[$pilar],
                    'valor' => number_format($v, 1, ',', '.') . '%',
                    'sub' => 'eficiencia do pilar',
                    'cor' => $cores[$pilar],
                    'icone' => $icones[$pilar],
                    'delta' => $delta[$pilar],
                ));
                barra_progresso($v, $cores[$pilar]);
                ?>
                <a class="link-mini" href="/ranksistem/pages/<?php echo $pilar; ?>.php">investigar registros &rarr;</a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="grid grid-stats">
    <?php
    card_stat(array(
        'titulo' => 'Eficiencia global',
        'valor' => number_format($global, 1, ',', '.') . '/100',
        'sub' => 'media ponderada dos 4 pilares',
        'cor' => score_cor($global),
        'icone' => 'grafico',
        'delta' => $tendencia['delta'],
    ));
    card_stat(array(
        'titulo' => 'Metas concluidas',
        'valor' => number_format($metasStats['pct'], 0) . '%',
        'sub' => (int)$metasStats['concluida'] . ' de ' . (int)($metasStats['ativa'] + $metasStats['concluida']) . ' planejadas',
        'cor' => '#22c55e',
        'icone' => 'check',
    ));
    card_stat(array(
        'titulo' => 'Consistencia',
        'valor' => number_format($consistencia, 0) . '%',
        'sub' => 'execucao dos compromissos (7 dias)',
        'cor' => $consistencia >= 70 ? '#38bdf8' : '#fbbf24',
        'icone' => 'raio',
    ));
    card_stat(array(
        'titulo' => 'Qualidade dos dados',
        'valor' => number_format($qualidade['pct'], 0) . '%',
        'sub' => 'registros completos e na cadencia',
        'cor' => $qualidade['pct'] >= 70 ? '#2dd4bf' : '#fb923c',
        'icone' => 'cerebro',
    ));
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    echo '<div class="diag-lista">';
    foreach ($resumoDiag as $item) {
        if ($item === null) { continue; }
        echo '<div class="diag-item" style="--cor:' . $item['cor'] . '">'
            . '<span class="diag-ico">' . icone($item['icone'], 18) . '</span>'
            . '<div><span class="diag-titulo">' . e($item['titulo']) . '</span>'
            . '<p>' . e($item['texto']) . '</p>'
            . '<div class="diag-acoes"><a class="link-mini" href="/ranksistem/' . e(ltrim($item['link'], '/')) . '">abrir detalhe &rarr;</a>'
            . '<a class="link-mini" href="/ranksistem/pages/metas.php">ver todos os diagnosticos</a></div>'
            . '</div></div>';
    }
    echo '</div>';
    $conteudo = ob_get_clean();
    card_bloco('Diagnostico automatico', $conteudo, '#fbbf24', 'cerebro', 'gerado a partir dos dados atuais');
    ?>

    <?php
    ob_start();
    if (empty($proximas)) {
        echo '<p class="muted">Nenhuma meta ativa. Defina uma melhoria em Metas e Ciclo para o sistema acompanhar o efeito no proximo ciclo.</p>';
        echo '<a class="btn btn-primario btn-mini" href="/ranksistem/pages/metas.php">Criar primeira meta</a>';
    } else {
        echo '<div class="lista-simples">';
        foreach ($proximas as $m) {
            $cor = metas_cor($m['pilar']);
            $vencida = !empty($m['prazo']) && $m['prazo'] < date('Y-m-d');
            echo '<div class="item-simples">'
                . '<span style="border-left:3px solid ' . $cor . '; padding-left:9px;">' . e($m['titulo']) . '</span>'
                . '<span class="' . ($vencida ? 'meta-vencida' : 'muted') . '" style="white-space:nowrap">'
                . e(ucfirst($m['etapa'])) . ($m['prazo'] ? ' · ' . date('d/m', strtotime($m['prazo'])) : '')
                . '</span></div>';
        }
        echo '</div>';
        echo '<p class="muted" style="margin-top:12px">Prioridade e ordenada por urgencia, prazo e etapa do ciclo.</p>';
        echo '<a class="link-mini" href="/ranksistem/pages/metas.php">abrir metas e ciclo &rarr;</a>';
    }
    $conteudo = ob_get_clean();
    card_bloco('Proximas prioridades', $conteudo, '#e879f9', 'alvo', count($proximas) . ' na fila');
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <canvas id="chartEvolucao" height="240"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Evolucao dos pilares (score %)', $conteudo, '#38bdf8', 'grafico');
    ?>

    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/checkinController.php" class="form-grid">
        <input type="hidden" name="data" value="<?php echo date('Y-m-d'); ?>">
        <?php
        form_campo(array('name' => 'sono_horas', 'label' => 'Horas de sono', 'type' => 'number', 'step' => '0.5', 'min' => 0, 'max' => 24, 'value' => $checkinHoje ? $checkinHoje['sono_horas'] : '7', 'required' => true));
        form_campo(array('name' => 'energia', 'label' => 'Energia (1-10)', 'type' => 'number', 'min' => 1, 'max' => 10, 'value' => $checkinHoje ? $checkinHoje['energia'] : '7', 'required' => true));
        form_campo(array('name' => 'humor', 'label' => 'Humor (1-10)', 'type' => 'number', 'min' => 1, 'max' => 10, 'value' => $checkinHoje ? $checkinHoje['humor'] : '7', 'required' => true));
        form_campo(array('name' => 'estresse', 'label' => 'Estresse (1-10)', 'type' => 'number', 'min' => 1, 'max' => 10, 'value' => $checkinHoje ? $checkinHoje['estresse'] : '4', 'required' => true));
        form_campo(array('name' => 'notas', 'label' => 'Observacoes', 'type' => 'text', 'placeholder' => 'Opcional', 'value' => $checkinHoje ? $checkinHoje['notas'] : ''));
        ?>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-primario">Salvar check-in de hoje</button>
        </div>
    </form>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Check-in diario', $conteudo, '#2dd4bf', 'sol', $checkinHoje ? '<span class="tag-ok">registrado hoje</span>' : '<span class="tag-pend">pendente</span>');
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <canvas id="chartCorrelacao" height="220"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Sono x adesao ao plano (trading)', $conteudo, '#f472b6', 'link');
    ?>

    <?php
    ob_start();
    ?>
    <div class="insight-lista">
        <?php
        $boa = isset($corrSono['boa']) ? $corrSono['boa'] : null;
        $baixa = isset($corrSono['baixa']) ? $corrSono['baixa'] : null;
        if ($boa && $baixa && $boa['sono_medio'] !== null && $baixa['sono_medio'] !== null) {
            $adesBoa = (float)$boa['adesao_media'];
            $adesBaixa = (float)$baixa['adesao_media'];
            $dif = round($adesBoa - $adesBaixa, 1);
            $nTotal = (int)$boa['total'] + (int)$baixa['total'];
            echo '<div class="insight"><span class="insight-ico" style="--cor:#eab308">' . icone('sol', 18) . '</span>'
                . '<p>Nas operacoes em dias com <strong>sono adequado</strong> sua adesao media ao plano foi <strong>' . number_format($adesBoa, 1, ',', '.') . '</strong> vs <strong>' . number_format($adesBaixa, 1, ',', '.') . '</strong> em dias de sono baixo. Diferenca de <strong>' . ($dif >= 0 ? '+' : '') . number_format($dif, 1, ',', '.') . '</strong> (N=' . $nTotal . ' operacoes). <a class="link-mini" href="/ranksistem/pages/correlacoes.php">investigar</a></p></div>';
        } else {
            echo '<div class="insight"><span class="insight-ico" style="--cor:#f472b6">' . icone('link', 18) . '</span>'
                . '<p>Registre operacoes e check-ins diarios para o sistema cruzar sono e execucao automaticamente.</p></div>';
        }

        $comT = $corrEnergia['com_treino'];
        $semT = $corrEnergia['sem_treino'];
        if ($comT && $semT && $comT['media'] !== null && $semT['media'] !== null) {
            $nT = (int)$comT['total'] + (int)$semT['total'];
            echo '<div class="insight"><span class="insight-ico" style="--cor:#fb923c">' . icone('halter', 18) . '</span>'
                . '<p>Energia media em dias <strong>com treino</strong>: <strong>' . number_format((float)$comT['media'], 1, ',', '.') . '</strong> vs <strong>' . number_format((float)$semT['media'], 1, ',', '.') . '</strong> sem treino (N=' . $nT . ' check-ins). <a class="link-mini" href="/ranksistem/pages/correlacoes.php">investigar</a></p></div>';
        } else {
            echo '<div class="insight"><span class="insight-ico" style="--cor:#fb923c">' . icone('halter', 18) . '</span>'
                . '<p>Registre treinos e check-ins para medir o impacto da calistenia na sua energia.</p></div>';
        }

        if ($qualidade['pct'] < 70) {
            echo '<div class="insight"><span class="insight-ico" style="--cor:#fb923c">' . icone('cerebro', 18) . '</span>'
                . '<p>Qualidade dos dados em <strong>' . number_format($qualidade['pct'], 0) . '%</strong>: alguns indicadores tem pouca confianca. Confira quais serie estao incompletas em Metas e Ciclo &rarr; Motor de diagnostico.</p></div>';
        }
        ?>
    </div>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Leituras automaticas', $conteudo, '#a78bfa', 'cerebro');
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    echo '<div class="formula-lista">';
    ?>
    <div class="formula-item">
        <strong>Eficiencia global</strong>
        <span>Soma dos scores dos pilares ponderada pelos pesos configurados. Nao e media simples das metricas individuais.</span>
        <code>E_global = SUM(score_pilar_i * peso_i) / SUM(peso_i)</code>
    </div>
    <div class="formula-item">
        <strong>Financeiro</strong>
        <span>Taxa de acerto, adesao ao plano, profit factor e execucao perfeita pesam mais quando ha operacoes; a variacao das contas entra como complemento.</span>
        <code>trading = WR*0,30 + (adesao*10)*0,25 + min(PF/2,1)*100*0,25 + execucao_perfeita*0,20
score = trading*0,7 + variacao_consolidada_normalizada*0,3</code>
    </div>
    <div class="formula-item">
        <strong>Academico</strong>
        <span>Horas sozias nao contam: progresso de modulos, consistencia de sessoes e retencao em revisoes.</span>
        <code>score = conclusao*0,40 + consistencia*0,30 + retencao*0,30</code>
    </div>
    <div class="formula-item">
        <strong>Fisico</strong>
        <span>Volume, dificuldade e esforco sao registrados separados; a progressao tecnica entra com peso proprio.</span>
        <code>score = consistencia*0,30 + sobrecarga*0,25 + prontidao*0,25 + progressao*0,20</code>
    </div>
    <div class="formula-item">
        <strong>Social</strong>
        <span>Frequencia de conexoes, recarga percebida e tempo de convivencia. Nao premia quantidade sem qualidade.</span>
        <code>score = conexoes*0,40 + recarga*0,30 + tempo*0,30</code>
    </div>
    <div class="formula-item">
        <strong>Qualidade dos dados</strong>
        <span>Compara o que foi registrado na cadencia esperada dos ultimos 30 dias e a completude dos campos que alimentam metricas. Ausencia de registro vira alerta de dados insuficientes, nao nota baixa.</span>
        <code>qualidade = media(checkin, estudos, treinos, social, completude)</code>
    </div>
    <div class="formula-item">
        <strong>Confianca do diagnostico</strong>
        <span>Cada alerta declara N, periodo e confianca. Amostra pequena e rotulada como confianca baixa: resultado observado nao garante resultado futuro.</span>
    </div>
    <?php
    echo '</div>';
    $conteudo = ob_get_clean();
    card_bloco('Como os numeros foram calculados', $conteudo, '#38bdf8', 'livro', 'explicabilidade');
    ?>
</section>

<script>
window.RANK_CHARTS = [
    {
        id: 'chartEvolucao',
        config: {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labelsDatas); ?>,
                datasets: [
                    { label: 'Global', data: <?php echo json_encode($serieGlobal); ?>, borderColor: '#38bdf8', backgroundColor: 'rgba(56,189,248,.15)', tension: .35, fill: true, borderWidth: 3 },
                    { label: 'Financeiro', data: <?php echo json_encode($seriesPilar['financeiro']); ?>, borderColor: '#22c55e', tension: .35, borderWidth: 2, pointRadius: 2 },
                    { label: 'Academico', data: <?php echo json_encode($seriesPilar['academico']); ?>, borderColor: '#a78bfa', tension: .35, borderWidth: 2, pointRadius: 2 },
                    { label: 'Fisico', data: <?php echo json_encode($seriesPilar['fisico']); ?>, borderColor: '#fb923c', tension: .35, borderWidth: 2, pointRadius: 2 },
                    { label: 'Social', data: <?php echo json_encode($seriesPilar['social']); ?>, borderColor: '#2dd4bf', tension: .35, borderWidth: 2, pointRadius: 2 }
                ]
            },
            options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { suggestedMin: 0, suggestedMax: 100 } } }
        }
    },
    {
        id: 'chartCorrelacao',
        config: {
            type: 'bar',
            data: {
                labels: ['Sono bom', 'Sono baixo'],
                datasets: [
                    { label: 'Sono medio (h)', data: [<?php echo $corrSono['boa'] ? (float)$corrSono['boa']['sono_medio'] : 0; ?>, <?php echo $corrSono['baixa'] ? (float)$corrSono['baixa']['sono_medio'] : 0; ?>], backgroundColor: '#fb923c', borderRadius: 8 },
                    { label: 'Adesao media (0-10)', data: [<?php echo $corrSono['boa'] ? (float)$corrSono['boa']['adesao_media'] : 0; ?>, <?php echo $corrSono['baixa'] ? (float)$corrSono['baixa']['adesao_media'] : 0; ?>], backgroundColor: '#a78bfa', borderRadius: 8 }
                ]
            },
            options: { plugins: { legend: { position: 'bottom' } } }
        }
    }
];
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
