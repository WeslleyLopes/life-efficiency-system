<?php
// meta_helper.php - ciclo operacional de metas (diagnosticar -> aprender)

if (!function_exists('metas_etapas')) {
    function metas_etapas()
    {
        return array(
            'diagnosticar' => 'Diagnosticar',
            'priorizar'    => 'Priorizar',
            'planejar'     => 'Planejar',
            'executar'     => 'Executar',
            'avaliar'      => 'Avaliar',
            'aprender'     => 'Aprender',
        );
    }
}

if (!function_exists('metas_listar')) {
    function metas_listar($status = null, $pilar = null, $limite = 200)
    {
        $where = array();
        if ($status !== null && $status !== '') { $where[] = "status = '" . db_escape($status) . "'"; }
        if ($pilar !== null && $pilar !== '') { $where[] = "pilar = '" . db_escape($pilar) . "'"; }
        $sql = "SELECT * FROM metas";
        if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
        $sql .= " ORDER BY FIELD(status, 'ativa','concluida','abandonada'),
            prioridade ASC,
            FIELD(etapa, 'diagnosticar','priorizar','planejar','executar','avaliar','aprender'),
            COALESCE(prazo, '9999-12-31') ASC
            LIMIT " . (int)$limite;
        return db_all($sql);
    }
}

if (!function_exists('metas_por_id')) {
    function metas_por_id($id)
    {
        return db_one("SELECT * FROM metas WHERE id = " . (int)$id);
    }
}

if (!function_exists('metas_add')) {
    function metas_add($d)
    {
        $titulo = trim(isset($d['titulo']) ? $d['titulo'] : '');
        if ($titulo === '') { return false; }
        $pilar = isset($d['pilar']) ? $d['pilar'] : 'cross';
        $descricao = trim(isset($d['descricao']) ? $d['descricao'] : '');
        $etapa = isset($d['etapa']) ? $d['etapa'] : 'planejar';
        $prioridade = max(1, min(3, (int)(isset($d['prioridade']) ? $d['prioridade'] : 2)));
        $esforco = isset($d['esforco']) ? $d['esforco'] : 'medio';
        $prazo = !empty($d['prazo']) ? $d['prazo'] : null;
        $indicador = trim(isset($d['indicador']) ? $d['indicador'] : '');
        $baseline = (isset($d['baseline']) && $d['baseline'] !== '') ? (float)$d['baseline'] : null;
        $alvo = (isset($d['alvo']) && $d['alvo'] !== '') ? (float)$d['alvo'] : null;
        $unidade = trim(isset($d['unidade']) ? $d['unidade'] : '');

        $stmt = db()->prepare("INSERT INTO metas
            (pilar, titulo, descricao, etapa, prioridade, esforco, prazo, indicador, baseline, alvo, unidade)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssisssdds', $pilar, $titulo, $descricao, $etapa, $prioridade, $esforco, $prazo, $indicador, $baseline, $alvo, $unidade);
        if (!$stmt->execute()) { $stmt->close(); return false; }
        $id = db_insert_id();
        $stmt->close();
        return $id;
    }
}

if (!function_exists('metas_atualizar')) {
    function metas_atualizar($id, $d)
    {
        $id = (int)$id;
        $existentes = metas_por_id($id);
        if (!$existentes) { return false; }

        $campos = array();
        $tipos = '';
        $vals = array();
        foreach (array('pilar', 'titulo', 'descricao', 'etapa', 'esforco', 'prazo', 'indicador', 'unidade', 'resultado') as $campo) {
            if (isset($d[$campo])) {
                $campos[] = $campo . ' = ?';
                $tipos .= 's';
                $vals[] = $d[$campo] === '' ? null : $d[$campo];
            }
        }
        if (isset($d['prioridade'])) {
            $campos[] = 'prioridade = ?';
            $tipos .= 'i';
            $vals[] = max(1, min(3, (int)$d['prioridade']));
        }
        foreach (array('baseline', 'alvo') as $campo) {
            if (array_key_exists($campo, $d)) {
                $campos[] = $campo . ' = ?';
                $tipos .= ($d[$campo] === null || $d[$campo] === '') ? 'd' : 'd';
                $vals[] = ($d[$campo] === null || $d[$campo] === '') ? null : (float)$d[$campo];
            }
        }
        if (empty($campos)) { return false; }

        $sql = 'UPDATE metas SET ' . implode(', ', $campos) . ' WHERE id = ?';
        $tipos .= 'i';
        $vals[] = $id;
        $stmt = db()->prepare($sql);
        $stmt->bind_param($tipos, ...$vals);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('metas_avancar_etapa')) {
    // Move a meta para a proxima etapa do ciclo; ao sair de "aprender" encerra como concluida
    function metas_avancar_etapa($id)
    {
        $meta = metas_por_id($id);
        if (!$meta) { return false; }
        $ordem = array_keys(metas_etapas());
        $pos = array_search($meta['etapa'], $ordem, true);
        if ($pos === false) { $pos = 2; }
        if ($pos >= count($ordem) - 1) {
            db_query("UPDATE metas SET status = 'concluida', resultado = COALESCE(resultado, 'Ciclo concluido na etapa de aprendizado.') WHERE id = " . (int)$id);
            return 'concluida';
        }
        $nova = $ordem[$pos + 1];
        db_query("UPDATE metas SET etapa = '" . $nova . "' WHERE id = " . (int)$id);
        return $nova;
    }
}

if (!function_exists('metas_mudar_status')) {
    function metas_mudar_status($id, $status)
    {
        if (!in_array($status, array('ativa', 'concluida', 'abandonada'), true)) { return false; }
        db_query("UPDATE metas SET status = '" . $status . "' WHERE id = " . (int)$id);
        return true;
    }
}

if (!function_exists('metas_del')) {
    function metas_del($id)
    {
        db_query("DELETE FROM metas WHERE id = " . (int)$id);
        return true;
    }
}

if (!function_exists('metas_stats')) {
    function metas_stats()
    {
        $rows = db_all("SELECT status, COUNT(*) AS n FROM metas GROUP BY status");
        $s = array('ativa' => 0, 'concluida' => 0, 'abandonada' => 0, 'total' => 0, 'pct' => 0.0, 'vencidas' => 0);
        foreach ($rows as $r) {
            $s[$r['status']] = (int)$r['n'];
            $s['total'] += (int)$r['n'];
        }
        $planejadas = $s['ativa'] + $s['concluida'];
        $s['pct'] = $planejadas > 0 ? round($s['concluida'] / $planejadas * 100, 1) : 0.0;
        $v = db_one("SELECT COUNT(*) AS n FROM metas WHERE status = 'ativa' AND prazo IS NOT NULL AND prazo < CURDATE()");
        $s['vencidas'] = $v ? (int)$v['n'] : 0;
        return $s;
    }
}

if (!function_exists('metas_proximas')) {
    function metas_proximas($limite = 4)
    {
        return metas_listar('ativa', null, (int)$limite);
    }
}

if (!function_exists('metas_pilar_labels')) {
    function metas_pilar_labels()
    {
        $base = pilar_labels();
        $base['cross'] = 'Entre pilares';
        return $base;
    }
}

if (!function_exists('metas_cor')) {
    function metas_cor($pilar)
    {
        if ($pilar === 'cross') { return '#f472b6'; }
        $cores = pilar_cores();
        return isset($cores[$pilar]) ? $cores[$pilar] : '#38bdf8';
    }
}
