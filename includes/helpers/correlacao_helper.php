<?php
// correlacao_helper.php - cruzamento de eficiencia entre pilares

// Sono medio nos dias com boa execucao no trading vs execucao ruim
function corr_sono_vs_execucao()
{
    $sql = "SELECT
            CASE WHEN o.adesao_plano >= 8 THEN 'boa' ELSE 'baixa' END AS grupo,
            ROUND(AVG(c.sono_horas),2) AS sono_medio,
            ROUND(AVG(o.adesao_plano),1) AS adesao_media,
            COUNT(*) AS total
        FROM financeiro_operacoes o
        LEFT JOIN checkin_diario c ON c.data = o.data
        WHERE c.sono_horas IS NOT NULL
        GROUP BY grupo";
    $rows = db_all($sql);
    $resultado = array('boa' => null, 'baixa' => null);
    foreach ($rows as $r) {
        $resultado[$r['grupo']] = $r;
    }
    return $resultado;
}

// Energia registrada em dias de treino vs dias sem treino
function corr_energia_vs_treino()
{
    $comTreino = db_one("SELECT ROUND(AVG(c.energia),1) AS media, COUNT(*) AS total
        FROM checkin_diario c
        WHERE EXISTS (SELECT 1 FROM treinos t WHERE t.data = c.data)");
    $semTreino = db_one("SELECT ROUND(AVG(c.energia),1) AS media, COUNT(*) AS total
        FROM checkin_diario c
        WHERE NOT EXISTS (SELECT 1 FROM treinos t WHERE t.data = c.data)");
    return array('com_treino' => $comTreino, 'sem_treino' => $semTreino);
}

// Media de operacoes por dia com estudo vs sem estudo (overtrading)
function corr_estudo_vs_overtrading()
{
    $comEstudo = db_one("SELECT ROUND(AVG(qtd),2) AS media, SUM(qtd) AS total_ops, COUNT(*) AS dias
        FROM (SELECT o.data, COUNT(*) AS qtd FROM financeiro_operacoes o
              WHERE EXISTS (SELECT 1 FROM estudos e WHERE e.data = o.data)
              GROUP BY o.data) x");
    $semEstudo = db_one("SELECT ROUND(AVG(qtd),2) AS media, SUM(qtd) AS total_ops, COUNT(*) AS dias
        FROM (SELECT o.data, COUNT(*) AS qtd FROM financeiro_operacoes o
              WHERE NOT EXISTS (SELECT 1 FROM estudos e WHERE e.data = o.data)
              GROUP BY o.data) x");
    return array('com_estudo' => $comEstudo, 'sem_estudo' => $semEstudo);
}

// Media de energia/humor por dia da semana
function corr_humor_por_dia_semana()
{
    return db_all("SELECT DAYOFWEEK(data) AS dow,
            ROUND(AVG(humor),1) AS humor,
            ROUND(AVG(energia),1) AS energia,
            ROUND(AVG(estresse),1) AS estresse
        FROM checkin_diario
        WHERE data >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        GROUP BY dow ORDER BY dow");
}

// Correlacao de Pearson simples entre dois conjuntos
function corr_pearson($xs, $ys)
{
    $n = min(count($xs), count($ys));
    if ($n < 3) { return null; }
    $xs = array_slice(array_values($xs), 0, $n);
    $ys = array_slice(array_values($ys), 0, $n);
    $mx = array_sum($xs) / $n;
    $my = array_sum($ys) / $n;
    $num = 0.0; $dx = 0.0; $dy = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $num += ($xs[$i] - $mx) * ($ys[$i] - $my);
        $dx += pow($xs[$i] - $mx, 2);
        $dy += pow($ys[$i] - $my, 2);
    }
    if ($dx <= 0 || $dy <= 0) { return null; }
    return round($num / sqrt($dx * $dy), 2);
}

// Correlacao sono x variacao diaria da conta de trading (indice)
function corr_sono_variacao_diaria()
{
    $rows = db_all("SELECT o.data, SUM(o.retorno_pct) AS retorno, c.sono_horas
        FROM financeiro_operacoes o
        JOIN checkin_diario c ON c.data = o.data
        GROUP BY o.data, c.sono_horas
        ORDER BY o.data");
    $xs = array(); $ys = array(); $pontos = array();
    foreach ($rows as $r) {
        $xs[] = (float)$r['sono_horas'];
        $ys[] = (float)$r['retorno'];
        $pontos[] = array('x' => (float)$r['sono_horas'], 'y' => (float)$r['retorno'], 'data' => $r['data']);
    }
    return array('pontos' => $pontos, 'r' => corr_pearson($xs, $ys));
}

function corr_planos()
{
    return db_all("SELECT * FROM planos_experimentais ORDER BY status, data_inicio DESC");
}
