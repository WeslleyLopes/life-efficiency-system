<?php
require_once __DIR__ . '/../config.php';

$page_title = 'Visao Geral';
$page_sub = 'Seu medidor de eficiencia entre os quatro pilares';
$active = 'dashboard';

salvar_pilar_scores();

$scores = score_pilares();
$global = score_global($scores);
$cores = pilar_cores();
$labels = pilar_labels();
$icones = pilar_icones();

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
$pesos = score_pesos();
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
        <span class="global-titulo">Score Global de Eficiencia</span>
        <?php gauge($global, score_cor($global), 'da meta', 200); ?>
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
            </div>
        <?php endforeach; ?>
    </div>
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
            echo '<div class="insight"><span class="insight-ico" style="--cor:#eab308">' . icone('sol', 18) . '</span>'
                . '<p>Nas operacoes em dias com <strong>sono adequado</strong> sua adesao media ao plano foi <strong>' . number_format($adesBoa, 1, ',', '.') . '</strong> vs <strong>' . number_format($adesBaixa, 1, ',', '.') . '</strong> em dias de sono baixo. Diferenca de <strong>' . ($dif >= 0 ? '+' : '') . number_format($dif, 1, ',', '.') . '</strong>.</p></div>';
        } else {
            echo '<div class="insight"><span class="insight-ico" style="--cor:#f472b6">' . icone('link', 18) . '</span>'
                . '<p>Registre operacoes e check-ins diarios para o sistema cruzar sono e execucao automaticamente.</p></div>';
        }

        $comT = $corrEnergia['com_treino'];
        $semT = $corrEnergia['sem_treino'];
        if ($comT && $semT && $comT['media'] !== null && $semT['media'] !== null) {
            echo '<div class="insight"><span class="insight-ico" style="--cor:#fb923c">' . icone('halter', 18) . '</span>'
                . '<p>Energia media em dias <strong>com treino</strong>: <strong>' . number_format((float)$comT['media'], 1, ',', '.') . '</strong> vs <strong>' . number_format((float)$semT['media'], 1, ',', '.') . '</strong> sem treino.</p></div>';
        } else {
            echo '<div class="insight"><span class="insight-ico" style="--cor:#fb923c">' . icone('halter', 18) . '</span>'
                . '<p>Registre treinos e check-ins para medir o impacto da calistenia na sua energia.</p></div>';
        }
        ?>
    </div>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Leituras automaticas', $conteudo, '#a78bfa', 'cerebro');
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
