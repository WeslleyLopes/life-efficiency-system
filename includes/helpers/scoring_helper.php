<?php
// scoring_helper.php - score global e por pilar (medidor de eficiencia)

function score_pesos()
{
    $row = db_one("SELECT * FROM config_pesos_pilares ORDER BY id LIMIT 1");
    if (!$row) {
        return array('financeiro' => 25, 'academico' => 25, 'fisico' => 25, 'social' => 25);
    }
    return array(
        'financeiro' => (int)$row['peso_financeiro'],
        'academico' => (int)$row['peso_academico'],
        'fisico' => (int)$row['peso_fisico'],
        'social' => (int)$row['peso_social'],
    );
}

// Calcula os 4 scores de pilar (0-100)
function score_pilares()
{
    return array(
        'financeiro' => fin_score(),
        'academico' => ac_score(),
        'fisico' => fis_score(),
        'social' => soc_score(),
    );
}

function score_global($scores = null)
{
    if ($scores === null) { $scores = score_pilares(); }
    $pesos = score_pesos();
    $somaPesos = array_sum($pesos);
    if ($somaPesos <= 0) { return 0.0; }
    $total = 0.0;
    foreach ($scores as $pilar => $valor) {
        $peso = isset($pesos[$pilar]) ? $pesos[$pilar] : 0;
        $total += $valor * $peso;
    }
    return round($total / $somaPesos, 1);
}

// Salva/atualiza o snapshot do dia (para historico e graficos)
function salvar_pilar_scores()
{
    $scores = score_pilares();
    $hoje = date('Y-m-d');
    foreach ($scores as $pilar => $valor) {
        $sql = "INSERT INTO pilar_scores (data, pilar, score) VALUES ('$hoje', '$pilar', $valor)
            ON DUPLICATE KEY UPDATE score = VALUES(score)";
        db_query($sql);
    }
    return $scores;
}

function pilar_scores_historico($dias = 30)
{
    return db_all("SELECT data, pilar, score FROM pilar_scores
        WHERE data >= DATE_SUB(CURDATE(), INTERVAL " . (int)$dias . " DAY)
        ORDER BY data ASC");
}

// Retorna cores por pilar (paleta amigavel sobre tema escuro)
function pilar_cores()
{
    return array(
        'financeiro' => '#22c55e',
        'academico'  => '#a78bfa',
        'fisico'     => '#fb923c',
        'social'     => '#2dd4bf',
    );
}

function pilar_icones()
{
    return array(
        'financeiro' => 'carteira',
        'academico'  => 'livro',
        'fisico'     => 'halter',
        'social'     => 'pessoas',
    );
}

function pilar_labels()
{
    return array(
        'financeiro' => 'Financeiro',
        'academico'  => 'Academico',
        'fisico'     => 'Fisico',
        'social'     => 'Social',
    );
}

// Cor do score: vermelho -> ambar -> verde
function score_cor($pct)
{
    $pct = max(0, min(100, (float)$pct));
    if ($pct < 40) { return '#f87171'; }
    if ($pct < 70) { return '#fbbf24'; }
    return '#22c55e';
}
