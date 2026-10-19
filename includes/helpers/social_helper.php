<?php
// social_helper.php - metricas do pilar social

function soc_registros($limite = 100)
{
    return db_all("SELECT * FROM social_registros ORDER BY data DESC, id DESC LIMIT " . (int)$limite);
}

function soc_conexoes_semana()
{
    $row = db_one("SELECT COUNT(*) AS q FROM social_registros
        WHERE tipo IN ('conexao','lazer','networking')
        AND data >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND (qualidade IS NULL OR qualidade >= 3)");
    return $row ? (int)$row['q'] : 0;
}

function soc_conexoes_pct()
{
    return round(min(100, soc_conexoes_semana() / 3 * 100), 1);
}

function soc_tempo_semana()
{
    $row = db_one("SELECT COALESCE(SUM(duracao_min),0) AS total FROM social_registros
        WHERE data >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    return $row ? (int)$row['total'] : 0;
}

function soc_tempo_pct()
{
    return round(min(100, soc_tempo_semana() / 300 * 100), 1);
}

function soc_recarga_media()
{
    // media das ultimas 4 semanas
    $rows = db_all("SELECT recarga_mental FROM social_checkins ORDER BY semana_inicio DESC LIMIT 4");
    if (empty($rows)) { return 0.0; }
    $soma = 0;
    foreach ($rows as $r) { $soma += (int)$r['recarga_mental']; }
    return round($soma / count($rows) * 10, 1);
}

function soc_qualidade_media()
{
    $row = db_one("SELECT AVG(qualidade) AS media FROM social_registros WHERE qualidade IS NOT NULL");
    return $row && $row['media'] !== null ? round((float)$row['media'], 1) : 0.0;
}

function soc_por_tipo()
{
    return db_all("SELECT tipo, COUNT(*) AS total, SUM(duracao_min) AS minutos
        FROM social_registros GROUP BY tipo");
}

function soc_score()
{
    $temDados = db_one("SELECT COUNT(*) AS q FROM social_registros");
    if (!$temDados || (int)$temDados['q'] === 0) { return 0.0; }
    $score = (soc_conexoes_pct() * 0.40) + (soc_recarga_media() * 0.30) + (soc_tempo_pct() * 0.30);
    return round(max(0, min(100, $score)), 1);
}

function soc_semana_inicio($data = null)
{
    $data = $data ?: date('Y-m-d');
    return date('Y-m-d', strtotime('monday this week', strtotime($data)));
}
