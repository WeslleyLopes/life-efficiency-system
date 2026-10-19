<?php
// fisico_helper.php - metricas do pilar fisico (calistenia com progressao)

function fis_exercicios()
{
    return db_all("SELECT e.*, (SELECT COUNT(*) FROM exercicios_niveis n WHERE n.exercicio_id = e.id) AS total_niveis
        FROM exercicios e WHERE e.ativo = 1 ORDER BY e.grupo, e.nome");
}

function fis_niveis($exercicio_id)
{
    return db_all("SELECT * FROM exercicios_niveis WHERE exercicio_id = " . (int)$exercicio_id . " ORDER BY nivel");
}

function fis_fator_dificuldade($exercicio_id, $nivel)
{
    $row = db_one("SELECT fator_dificuldade FROM exercicios_niveis
        WHERE exercicio_id = " . (int)$exercicio_id . " AND nivel = " . (int)$nivel);
    return $row ? (float)$row['fator_dificuldade'] : 1.0;
}

function fis_treinos($limite = 100)
{
    return db_all("SELECT * FROM treinos ORDER BY data DESC LIMIT " . (int)$limite);
}

function fis_series_treino($treino_id)
{
    return db_all("SELECT ts.*, e.nome AS exercicio_nome, n.nome AS nivel_nome
        FROM treino_series ts
        JOIN exercicios e ON e.id = ts.exercicio_id
        LEFT JOIN exercicios_niveis n ON n.exercicio_id = ts.exercicio_id AND n.nivel = ts.nivel_usado
        WHERE ts.treino_id = " . (int)$treino_id);
}

// Volume efetivo = series x reps x fator de dificuldade
function fis_volume_treino($treino_id)
{
    $series = fis_series_treino($treino_id);
    $volume = 0.0;
    foreach ($series as $s) {
        $fator = fis_fator_dificuldade($s['exercicio_id'], $s['nivel_usado']);
        $volume += (int)$s['series'] * (int)$s['reps'] * $fator;
    }
    return round($volume, 1);
}

// Volume da semana atual vs anterior (segunda a domingo)
function fis_volume_semana($offset = 0)
{
    $inicio = date('Y-m-d', strtotime('monday this week ' . ($offset < 0 ? $offset . ' week' : ($offset > 0 ? '+' . $offset . ' week' : ''))));
    $fim = date('Y-m-d', strtotime($inicio . ' +6 day'));
    $treinos = db_all("SELECT id FROM treinos WHERE data BETWEEN '$inicio' AND '$fim'");
    $total = 0.0;
    foreach ($treinos as $t) { $total += fis_volume_treino($t['id']); }
    return round($total, 1);
}

function fis_sobrecarga_pct()
{
    $anterior = fis_volume_semana(-1);
    $atual = fis_volume_semana(0);
    if ($anterior <= 0) { return $atual > 0 ? 100.0 : 0.0; }
    return round(($atual - $anterior) / $anterior * 100, 1);
}

// Consistencia: treinos nos ultimos 7 dias / meta de 4
function fis_consistencia()
{
    $row = db_one("SELECT COUNT(*) AS q FROM treinos WHERE data >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $q = $row ? (int)$row['q'] : 0;
    return round(min(100, $q / 4 * 100), 1);
}

function fis_prontidao_media()
{
    $row = db_one("SELECT AVG(energia_pre) AS energia, AVG(10 - COALESCE(dor_muscular,0)) AS recuperacao
        FROM treinos WHERE energia_pre IS NOT NULL");
    if (!$row || $row['energia'] === null) { return 0.0; }
    $media = ((float)$row['energia'] * 0.6) + ((float)$row['recuperacao'] * 0.4);
    return round($media * 10, 1);
}

function fis_volume_por_semana($semanas = 8)
{
    $serie = array();
    for ($i = $semanas - 1; $i >= 0; $i--) {
        $inicio = date('Y-m-d', strtotime('monday this week -' . $i . ' week'));
        $serie[] = array('rotulo' => date('d/m', strtotime($inicio)), 'volume' => fis_volume_semana(-$i));
    }
    return $serie;
}

// Maior nivel alcancado por exercicio e total de niveis
function fis_progressao()
{
    $rows = db_all("SELECT e.id, e.nome, e.grupo,
            (SELECT COUNT(*) FROM exercicios_niveis n WHERE n.exercicio_id = e.id) AS total_niveis,
            COALESCE(MAX(ts.nivel_usado),0) AS nivel_max,
            (SELECT n2.nome FROM exercicios_niveis n2 WHERE n2.exercicio_id = e.id AND n2.nivel = COALESCE(MAX(ts.nivel_usado),0) LIMIT 1) AS nivel_nome
        FROM exercicios e
        LEFT JOIN treino_series ts ON ts.exercicio_id = e.id
        WHERE e.ativo = 1
        GROUP BY e.id ORDER BY e.grupo, e.nome");
    foreach ($rows as &$r) {
        $r['pct'] = (int)$r['total_niveis'] > 0 ? round((int)$r['nivel_max'] / (int)$r['total_niveis'] * 100, 0) : 0;
    }
    return $rows;
}

function fis_score()
{
    $temDados = db_one("SELECT COUNT(*) AS q FROM treinos");
    if (!$temDados || (int)$temDados['q'] === 0) { return 0.0; }
    $sobrecarga = max(0, min(100, fis_sobrecarga_pct()));
    $score = (fis_consistencia() * 0.30) + ($sobrecarga * 0.25) + (fis_prontidao_media() * 0.25);
    // progressao media (peso 20)
    $progressao = fis_progressao();
    $somaPct = 0;
    foreach ($progressao as $p) { $somaPct += $p['pct']; }
    $prog_media = !empty($progressao) ? $somaPct / count($progressao) : 0;
    $score += $prog_media * 0.20;
    return round(max(0, min(100, $score)), 1);
}
