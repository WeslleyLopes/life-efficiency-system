<?php
// academico_helper.php - metricas do pilar academico

function ac_materias($apenas_ativas = true)
{
    $where = $apenas_ativas ? 'WHERE ativo = 1' : '';
    return db_all("SELECT * FROM materias $where ORDER BY nome");
}

function ac_modulos($materia_id = null)
{
    $where = $materia_id ? 'WHERE m.materia_id = ' . (int)$materia_id : '';
    return db_all("SELECT m.*, mt.nome AS materia_nome, mt.cor AS materia_cor,
            COALESCE(SUM(e.minutos),0) AS minutos_estudados
        FROM modulos m
        JOIN materias mt ON mt.id = m.materia_id
        LEFT JOIN estudos e ON e.modulo_id = m.id
        $where
        GROUP BY m.id
        ORDER BY mt.nome, m.ordem");
}

function ac_progresso_modulo($modulo)
{
    if ((int)$modulo['concluido'] === 1) { return 100.0; }
    $metaMin = ((float)$modulo['horas_meta']) * 60;
    if ($metaMin <= 0) { return 0.0; }
    return round(min(100, ((float)$modulo['minutos_estudados'] / $metaMin) * 100), 1);
}

function ac_conclusao_pct()
{
    $modulos = ac_modulos();
    if (empty($modulos)) { return 0.0; }
    $soma = 0;
    foreach ($modulos as $m) { $soma += ac_progresso_modulo($m); }
    return round($soma / count($modulos), 1);
}

// Consistencia: dias com estudo nos ultimos 7 dias / meta de 5 dias
function ac_consistencia()
{
    $row = db_one("SELECT COUNT(DISTINCT data) AS dias FROM estudos WHERE data >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $dias = $row ? (int)$row['dias'] : 0;
    return round(min(100, $dias / 5 * 100), 1);
}

function ac_retencao_media()
{
    $row = db_one("SELECT AVG(retencao) AS media FROM estudos WHERE retencao IS NOT NULL");
    return $row && $row['media'] !== null ? round((float)$row['media'], 1) : 0.0;
}

function ac_pacing_index($modulo)
{
    if (empty($modulo['data_inicio']) || empty($modulo['data_fim_prevista'])) { return null; }
    $inicio = strtotime($modulo['data_inicio']);
    $fim = strtotime($modulo['data_fim_prevista']);
    $hoje = strtotime(date('Y-m-d'));
    if ($fim <= $inicio) { return null; }
    $previsto = min(100, max(0, ($hoje - $inicio) / ($fim - $inicio) * 100));
    $real = ac_progresso_modulo($modulo);
    if ($previsto <= 0) { return null; }
    return round($real / $previsto * 100, 0);
}

function ac_horas_por_semana($semanas = 8)
{
    return db_all("SELECT YEARWEEK(data,1) AS sem,
            DATE_FORMAT(MIN(data),'%d/%m') AS rotulo,
            ROUND(SUM(minutos)/60,1) AS horas
        FROM estudos
        WHERE data >= DATE_SUB(CURDATE(), INTERVAL " . (int)$semanas . " WEEK)
        GROUP BY sem ORDER BY sem");
}

function ac_minutos_7dias()
{
    $row = db_one("SELECT COALESCE(SUM(minutos),0) AS total FROM estudos WHERE data >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    return $row ? (int)$row['total'] : 0;
}

function ac_score()
{
    $conclusao = ac_conclusao_pct();
    $consistencia = ac_consistencia();
    $retencao = ac_retencao_media();
    $temDados = db_one("SELECT COUNT(*) AS q FROM estudos");
    if (!$temDados || (int)$temDados['q'] === 0) { return 0.0; }
    $score = ($conclusao * 0.40) + ($consistencia * 0.30) + ($retencao * 0.30);
    return round(max(0, min(100, $score)), 1);
}
