<?php
// diagnostico_helper.php - qualidade dos dados, tendencia e motor de diagnostico
// Tipos de alerta: desvio_meta | padrao_identificado | dados_insuficientes | melhoria_sustentada

if (!function_exists('qualidade_dados')) {
    // Mede a confianca dos indicadores (0-100) nos ultimos N dias.
    // Nao trata ausencia de registro como fracasso: compara com a cadencia esperada.
    function qualidade_dados($dias = 30)
    {
        $dias = max(7, (int)$dias);
        $ini = date('Y-m-d', strtotime('-' . $dias . ' days'));
        $hoje = date('Y-m-d');

        $metaSemana = max(1, (int)round($dias / 7));
        $componentes = array();

        $rows = db_one("SELECT COUNT(DISTINCT data) AS n FROM checkin_diario
            WHERE data >= '$ini' AND data <= '$hoje'");
        $componentes['Check-in diario'] = pct((int)$rows['n'], $metaSemana * 5);

        $rows = db_one("SELECT COUNT(DISTINCT data) AS n FROM estudos
            WHERE data >= '$ini' AND data <= '$hoje'");
        $componentes['Sessoes de estudo'] = pct((int)$rows['n'], $metaSemana * 5);

        $rows = db_one("SELECT COUNT(DISTINCT data) AS n FROM treinos
            WHERE data >= '$ini' AND data <= '$hoje'");
        $componentes['Treinos'] = pct((int)$rows['n'], $metaSemana * 4);

        $rows = db_one("SELECT COUNT(*) AS n FROM social_registros
            WHERE data >= '$ini' AND data <= '$hoje'");
        $componentes['Registros sociais'] = pct((int)$rows['n'], $metaSemana * 3);

        // Completude dos campos opcionais que alimentam metricas
        $comp = array();
        $t = db_one("SELECT COUNT(*) AS total, SUM(retencao IS NOT NULL) AS preenchidos FROM estudos
            WHERE data >= '$ini' AND data <= '$hoje'");
        if ((int)$t['total'] > 0) { $comp['Retencao anotada'] = pct((int)$t['preenchidos'], (int)$t['total']); }

        $t = db_one("SELECT COUNT(*) AS total, SUM(qualidade IS NOT NULL) AS preenchidos FROM social_registros
            WHERE data >= '$ini' AND data <= '$hoje'");
        if ((int)$t['total'] > 0) { $comp['Qualidade anotada'] = pct((int)$t['preenchidos'], (int)$t['total']); }

        $t = db_one("SELECT COUNT(*) AS total, SUM(setup_id IS NOT NULL) AS preenchidos FROM financeiro_operacoes
            WHERE data >= '$ini' AND data <= '$hoje'");
        if ((int)$t['total'] > 0) { $comp['Operacoes com plano'] = pct((int)$t['preenchidos'], (int)$t['total']); }

        $t = db_one("SELECT COUNT(*) AS total, SUM(nota IS NOT NULL) AS preenchidos FROM
            (SELECT resultado_nota AS nota FROM planos_experimentais WHERE data_inicio >= '$ini') x");
        if ((int)$t['total'] > 0) { $comp['Experimentos avaliados'] = pct((int)$t['preenchidos'], (int)$t['total']); }

        foreach ($comp as $k => $v) { $componentes[$k] = $v; }

        $soma = 0;
        foreach ($componentes as $v) { $soma += $v; }
        $total = count($componentes);

        return array(
            'pct' => $total ? round($soma / $total, 1) : 0.0,
            'componentes' => $componentes,
            'periodo' => $dias . ' dias',
        );
    }

    function pct($valor, $meta)
    {
        if ($meta <= 0) { return 100.0; }
        return round(min(100, ($valor / $meta) * 100), 1);
    }
}

if (!function_exists('consistencia_global')) {
    // Media das cadencias dos pilares + check-in (0-100)
    function consistencia_global()
    {
        $checkin = db_one("SELECT COUNT(DISTINCT data) AS n FROM checkin_diario
            WHERE data >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
        $c = min(100, ((int)$checkin['n'] / 7) * 100);
        $valores = array($c, ac_consistencia(), fis_consistencia(), soc_conexoes_pct());
        $soma = 0;
        foreach ($valores as $v) { $soma += max(0, min(100, (float)$v)); }
        return round($soma / count($valores), 1);
    }
}

if (!function_exists('score_tendencia')) {
    // Compara a media do score global dos ultimos N dias com o periodo anterior
    function score_tendencia($dias = 7)
    {
        $hist = pilar_scores_historico($dias * 3);
        $pesos = score_pesos();
        $somaPesos = array_sum($pesos) ?: 1;
        $porData = array();
        foreach ($hist as $h) {
            if (!isset($porData[$h['data']])) { $porData[$h['data']] = 0.0; }
            $porData[$h['data']] += (float)$h['score'] * (isset($pesos[$h['pilar']]) ? $pesos[$h['pilar']] : 0);
        }
        foreach ($porData as $d => $v) { $porData[$d] = $v / $somaPesos; }
        ksort($porData);

        $hoje = date('Y-m-d');
        $iniAtual = date('Y-m-d', strtotime('-' . $dias . ' days'));
        $iniAnterior = date('Y-m-d', strtotime('-' . ($dias * 2) . ' days'));

        $atual = array();
        $anterior = array();
        foreach ($porData as $d => $v) {
            if ($d > $iniAtual && $d <= $hoje) { $atual[] = $v; }
            elseif ($d > $iniAnterior && $d <= $iniAtual) { $anterior[] = $v; }
        }

        $media = function ($arr) {
            if (empty($arr)) { return null; }
            return array_sum($arr) / count($arr);
        };
        $ma = $media($atual);
        $mp = $media($anterior);

        if ($ma === null || $mp === null || count($atual) < 2) {
            return array('rotulo' => 'Sem historico suficiente', 'delta' => null, 'classe' => 'estavel', 'icone' => '—');
        }
        $delta = round($ma - $mp, 1);
        if ($delta >= 2) {
            return array('rotulo' => 'Melhorando', 'delta' => $delta, 'classe' => 'up', 'icone' => '▲');
        }
        if ($delta <= -2) {
            return array('rotulo' => 'Piorando', 'delta' => $delta, 'classe' => 'down', 'icone' => '▼');
        }
        return array('rotulo' => 'Estavel', 'delta' => $delta, 'classe' => 'flat', 'icone' => '→');
    }
}

if (!function_exists('diag_gerar')) {
    // Gera/atualiza os diagnosticos abertos a partir dos dados atuais.
    // Diagnostico cuja condicao deixou de valer e encerrado automaticamente.
    function diag_gerar()
    {
        $hoje = date('Y-m-d');
        $semanaAtras = date('Y-m-d', strtotime('-7 days'));
        $doisSemanas = date('Y-m-d', strtotime('-14 days'));
        $candidatos = array();

        $scores = score_pilares();
        $labels = pilar_labels();
        $cores = pilar_cores();

        // 1) Desvio de meta: pilar abaixo do minimo esperado
        foreach ($scores as $pilar => $valor) {
            if ($valor < 55) {
                $candidatos['desvio_meta:' . $pilar] = array(
                    'tipo' => 'desvio_meta',
                    'pilar' => $pilar,
                    'metrica' => 'Eficiencia do pilar ' . $labels[$pilar],
                    'periodo' => 'Ate ' . $hoje,
                    'comparacao' => number_format($valor, 1, ',', '.') . ' / 100 vs minimo esperado de 55',
                    'mensagem' => 'O pilar ' . $labels[$pilar] . ' esta em ' . number_format($valor, 1, ',', '.') . ' pontos, abaixo do minimo esperado (55).',
                    'confianca' => $valor < 40 ? 'media' : 'baixa',
                    'acao_sugerida' => 'Investigar os registros do pilar ' . $labels[$pilar] . ' e abrir uma meta de melhoria com prazo.',
                    'registros_ref' => 'pages/' . $pilar . '.php',
                );
            }
        }

        // 2) Melhoria sustentada: media recente acima do periodo anterior
        $tend = score_tendencia(7);
        if ($tend['delta'] !== null && $tend['delta'] >= 3) {
            $candidatos['melhoria_sustentada:global'] = array(
                'tipo' => 'melhoria_sustentada',
                'pilar' => 'global',
                'metrica' => 'Score global de eficiencia',
                'periodo' => 'Ultimos 7 dias vs 7 anteriores',
                'comparacao' => ($tend['delta'] >= 0 ? '+' : '') . number_format($tend['delta'], 1, ',', '.') . ' pontos na media',
                'mensagem' => 'O score global subiu ' . number_format($tend['delta'], 1, ',', '.') . ' pontos na media dos ultimos 7 dias em relacao aos 7 anteriores.',
                'confianca' => count($scores) >= 3 ? 'media' : 'baixa',
                'acao_sugerida' => 'Manter o que mudou no periodo e observar por mais um ciclo antes de concluir a causa.',
                'registros_ref' => 'index.php',
            );
        }

        // 3) Dados insuficientes: cadencia de registro abaixo de 40%
        $q = qualidade_dados(30);
        foreach ($q['componentes'] as $nome => $v) {
            if ($v < 40) {
                $candidatos['dados_insuficientes:' . md5($nome)] = array(
                    'tipo' => 'dados_insuficientes',
                    'pilar' => 'global',
                    'metrica' => $nome,
                    'periodo' => 'Ultimos ' . $q['periodo'],
                    'comparacao' => number_format($v, 0) . '% da cadencia esperada',
                    'mensagem' => 'A serie de "' . $nome . '" tem apenas ' . number_format($v, 0) . '% da cadencia esperada nos ultimos ' . $q['periodo'] . '. Os indicadores relacionados tem pouca confianca.',
                    'confianca' => 'baixa',
                    'acao_sugerida' => 'Registrar ' . strtolower($nome) . ' de forma mais regular antes de tirar conclusoes sobre esse indicador.',
                    'registros_ref' => 'index.php',
                );
            }
        }

        // 4) Padrao identificado: relacao recorrente entre pilares (pista, nao causa)
        $sono = corr_sono_vs_execucao();
        $boa = isset($sono['boa']) ? $sono['boa'] : null;
        $baixa = isset($sono['baixa']) ? $sono['baixa'] : null;
        if ($boa && $baixa && (int)$boa['total'] >= 5 && (int)$baixa['total'] >= 5 && $boa['adesao_media'] !== null && $baixa['adesao_media'] !== null) {
            $dif = (float)$boa['adesao_media'] - (float)$baixa['adesao_media'];
            if (abs($dif) >= 1.0) {
                $n = (int)$boa['total'] + (int)$baixa['total'];
                $candidatos['padrao:sono_adesao'] = array(
                    'tipo' => 'padrao_identificado',
                    'pilar' => 'financeiro',
                    'metrica' => 'Sono x adesao ao plano',
                    'periodo' => 'Ultimos registros de operacoes',
                    'comparacao' => number_format((float)$boa['adesao_media'], 1, ',', '.') . ' com sono bom vs ' . number_format((float)$baixa['adesao_media'], 1, ',', '.') . ' com sono baixo',
                    'mensagem' => 'A adesao ao plano foi ' . ($dif > 0 ? 'maior' : 'menor') . ' em dias com sono adequado (diferenca de ' . number_format(abs($dif), 1, ',', '.') . ' pontos). N=' . $n . ' operacoes.',
                    'confianca' => $n >= 30 ? 'media' : 'baixa',
                    'acao_sugerida' => 'Observar se manter o sono regular melhora a adesao; nao tratar a relacao como causa.',
                    'registros_ref' => 'pages/financeiro.php',
                );
            }
        }

        $en = corr_energia_vs_treino();
        if ($en['com_treino'] && $en['sem_treino'] && $en['com_treino']['media'] !== null && $en['sem_treino']['media'] !== null) {
                $nT = (int)$en['com_treino']['total'] + (int)$en['sem_treino']['total'];
            if ($nT >= 8) {
                $dif = (float)$en['com_treino']['media'] - (float)$en['sem_treino']['media'];
                if (abs($dif) >= 1.0) {
                    $candidatos['padrao:energia_treino'] = array(
                        'tipo' => 'padrao_identificado',
                        'pilar' => 'fisico',
                        'metrica' => 'Energia x treino',
                        'periodo' => 'Check-ins dos ultimos 60 dias',
                        'comparacao' => number_format((float)$en['com_treino']['media'], 1, ',', '.') . ' com treino vs ' . number_format((float)$en['sem_treino']['media'], 1, ',', '.') . ' sem treino',
                        'mensagem' => 'A energia media registrada foi ' . ($dif > 0 ? 'maior' : 'menor') . ' nos dias com treino (diferenca de ' . number_format(abs($dif), 1, ',', '.') . '). N=' . $nT . ' check-ins.',
                        'confianca' => $nT >= 20 ? 'media' : 'baixa',
                        'acao_sugerida' => 'Manter o registro de energia por mais dois ciclos antes de concluir o efeito do treino.',
                        'registros_ref' => 'pages/fisico.php',
                    );
                }
            }
        }

        $ot = corr_estudo_vs_overtrading();
        if ($ot && isset($ot['com_estudo']) && isset($ot['sem_estudo'])
            && $ot['com_estudo']['media'] !== null && $ot['sem_estudo']['media'] !== null) {
            $nDias = (int)$ot['com_estudo']['dias'] + (int)$ot['sem_estudo']['dias'];
            if ($nDias >= 10) {
                $dif = (float)$ot['sem_estudo']['media'] - (float)$ot['com_estudo']['media'];
                if ($dif >= 1.0) {
                    $candidatos['padrao:estudo_overtrading'] = array(
                        'tipo' => 'padrao_identificado',
                        'pilar' => 'cross',
                        'metrica' => 'Estudo x volume de operacoes',
                        'periodo' => 'Dias com operacoes registradas',
                        'comparacao' => number_format((float)$ot['sem_estudo']['media'], 1, ',', '.') . ' ops/dia sem estudo vs ' . number_format((float)$ot['com_estudo']['media'], 1, ',', '.') . ' com estudo',
                        'mensagem' => 'Ha ' . number_format($dif, 1, ',', '.') . ' operacoes a mais por dia nos dias sem estudo registrado. N=' . $nDias . ' dias.',
                        'confianca' => $nDias >= 30 ? 'media' : 'baixa',
                        'acao_sugerida' => 'Comparar dias com e sem estudo por mais um mes antes de tratar como padrao.',
                        'registros_ref' => 'pages/correlacoes.php',
                    );
                }
            }
        }

        $candidatos = array_filter($candidatos);
        $chavesAtivas = array_keys($candidatos);

        foreach ($candidatos as $chave => $c) {
            $stmt = db()->prepare("INSERT INTO diagnosticos
                (chave, tipo, pilar, metrica, periodo, comparacao, mensagem, confianca, acao_sugerida, registros_ref)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    tipo = VALUES(tipo), pilar = VALUES(pilar), metrica = VALUES(metrica),
                    periodo = VALUES(periodo), comparacao = VALUES(comparacao), mensagem = VALUES(mensagem),
                    confianca = VALUES(confianca), acao_sugerida = VALUES(acao_sugerida),
                    registros_ref = VALUES(registros_ref), atualizado_em = CURRENT_TIMESTAMP,
                    status = IF(status = 'resolvido', 'aberto', status)");
            $stmt->bind_param(
                'ssssssssss',
                $chave,
                $c['tipo'],
                $c['pilar'],
                $c['metrica'],
                $c['periodo'],
                $c['comparacao'],
                $c['mensagem'],
                $c['confianca'],
                $c['acao_sugerida'],
                $c['registros_ref']
            );
            $stmt->execute();
            $stmt->close();
        }

        // Encerra diagnosticos abertos cuja condicao deixou de valer
        $abertos = db_all("SELECT id, chave FROM diagnosticos WHERE status IN ('aberto','aceito')");
        foreach ($abertos as $a) {
            if (!in_array($a['chave'], $chavesAtivas, true)) {
                db_query("UPDATE diagnosticos SET status = 'resolvido', atualizado_em = CURRENT_TIMESTAMP
                    WHERE id = " . (int)$a['id'] . " AND status = 'aberto'");
            }
        }

        return count($candidatos);
    }
}

if (!function_exists('diag_listar')) {
    function diag_listar($status = null, $limite = 50)
    {
        $limite = (int)$limite;
        $sql = "SELECT * FROM diagnosticos";
        if ($status !== null && $status !== '') {
            $sql .= " WHERE status = '" . db_escape($status) . "'";
        }
        $sql .= " ORDER BY FIELD(status, 'aberto','aceito','resolvido','ignorado'),
            FIELD(tipo, 'desvio_meta','padrao_identificado','dados_insuficientes','melhoria_sustentada'),
            atualizado_em DESC LIMIT $limite";
        return db_all($sql);
    }
}

if (!function_exists('diag_atualizar_status')) {
    function diag_atualizar_status($id, $status)
    {
        $permitidos = array('aberto', 'aceito', 'resolvido', 'ignorado');
        if (!in_array($status, $permitidos, true)) { return false; }
        db_query("UPDATE diagnosticos SET status = '" . $status . "', atualizado_em = CURRENT_TIMESTAMP
            WHERE id = " . (int)$id);
        return true;
    }
}

if (!function_exists('diag_tipo_info')) {
    function diag_tipo_info($tipo)
    {
        $mapa = array(
            'desvio_meta' => array('Desvio de meta', '#f87171', 'alvo'),
            'padrao_identificado' => array('Padrao identificado', '#fbbf24', 'link'),
            'dados_insuficientes' => array('Dados insuficientes', '#8ba0c4', 'grafico'),
            'melhoria_sustentada' => array('Melhoria sustentada', '#22c55e', 'check'),
        );
        return isset($mapa[$tipo]) ? $mapa[$tipo] : array('Alerta', '#38bdf8', 'cerebro');
    }
}

if (!function_exists('diag_resumo')) {
    // Monta as tres leituras do dashboard executivo
    function diag_resumo()
    {
        $abertos = diag_listar('aberto', 30);
        $porTipo = array();
        foreach ($abertos as $d) {
            $porTipo[$d['tipo']][] = $d;
        }

        $forte = null;
        $oportunidade = null;
        $proxima = null;

        if (!empty($porTipo['melhoria_sustentada'])) {
            $d = $porTipo['melhoria_sustentada'][0];
            $forte = array('titulo' => 'Ponto forte', 'texto' => $d['mensagem'], 'cor' => '#22c55e', 'icone' => 'check', 'link' => $d['registros_ref']);
        }

        if (empty($forte)) {
            $scores = score_pilares();
            $labels = pilar_labels();
            $cores = pilar_cores();
            $melhor = null;
            foreach ($scores as $p => $v) {
                if ($melhor === null || $v > $melhor['v']) { $melhor = array('p' => $p, 'v' => $v); }
            }
            if ($melhor !== null && $melhor['v'] > 0) {
                $forte = array(
                    'titulo' => 'Ponto forte',
                    'texto' => $labels[$melhor['p']] . ' e a area mais consistente agora, com ' . number_format($melhor['v'], 1, ',', '.') . ' de eficiencia. Use esse padrao como referencia para as demais.',
                    'cor' => $cores[$melhor['p']],
                    'icone' => 'check',
                    'link' => 'pages/' . $melhor['p'] . '.php',
                );
            }
        }

        if (!empty($porTipo['desvio_meta'])) {
            $d = $porTipo['desvio_meta'][0];
            $oportunidade = array('titulo' => 'Oportunidade', 'texto' => $d['mensagem'] . ' ' . $d['acao_sugerida'], 'cor' => '#f87171', 'icone' => 'alvo', 'link' => $d['registros_ref']);
        } elseif (!empty($porTipo['padrao_identificado'])) {
            $d = $porTipo['padrao_identificado'][0];
            $oportunidade = array('titulo' => 'Oportunidade', 'texto' => $d['mensagem'] . ' Pista para investigar, nao causa confirmada.', 'cor' => '#fbbf24', 'icone' => 'link', 'link' => $d['registros_ref']);
        } elseif (!empty($porTipo['dados_insuficientes'])) {
            $d = $porTipo['dados_insuficientes'][0];
            $oportunidade = array('titulo' => 'Oportunidade', 'texto' => $d['mensagem'], 'cor' => '#8ba0c4', 'icone' => 'grafico', 'link' => 'pages/metas.php');
        }

        $ordem = array('desvio_meta', 'padrao_identificado', 'dados_insuficientes', 'melhoria_sustentada');
        foreach ($ordem as $t) {
            if (!empty($porTipo[$t])) {
                $d = $porTipo[$t][0];
                $proxima = array('titulo' => 'Proxima acao', 'texto' => $d['acao_sugerida'], 'cor' => '#38bdf8', 'icone' => 'raio', 'link' => 'pages/metas.php');
                break;
            }
        }
        if ($proxima === null) {
            $proxima = array(
                'titulo' => 'Proxima acao',
                'texto' => 'Nenhum alerta aberto. Escolha uma melhoria, registre a linha de base e avalie o efeito no proximo ciclo.',
                'cor' => '#38bdf8',
                'icone' => 'raio',
                'link' => 'pages/metas.php',
            );
        }

        return array('forte' => $forte, 'oportunidade' => $oportunidade, 'proxima' => $proxima);
    }
}
