<?php
// components.php - funcoes reutilizaveis de UI (cards, gauge, tabelas, forms)

if (!function_exists('e')) {
    function e($valor)
    {
        return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('icone')) {
    function icone($nome, $tamanho = 22)
    {
        $paths = array(
            'grafico'  => '<path d="M4 19h16M7 16V9m5 7V4m5 12v-6"/>',
            'carteira' => '<path d="M3 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M16 12h3"/><circle cx="16" cy="12" r="1.4"/>',
            'livro'    => '<path d="M4 5a2 2 0 0 1 2-2h12v18H6a2 2 0 0 1-2-2z"/><path d="M8 3v18"/>',
            'halter'   => '<path d="M6.5 8v8M17.5 8v8M4 10v4M20 10v4M6.5 12h11"/>',
            'pessoas'  => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0M16 6a3 3 0 0 1 0 6M21 20a6 6 0 0 0-4-5.6"/>',
            'alvo'     => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1"/>',
            'raio'     => '<path d="M13 3 5 13h6l-1 8 8-10h-6z"/>',
            'mais'     => '<path d="M12 5v14M5 12h14"/>',
            'lixo'     => '<path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M6 7l1 13h10l1-13"/>',
            'check'    => '<path d="M4 12l5 5L20 6"/>',
            'cerebro'  => '<path d="M9 4a3 3 0 0 0-3 3 3 3 0 0 0-1 5 3 3 0 0 0 1 5 3 3 0 0 0 3 3V4z"/><path d="M15 4a3 3 0 0 1 3 3 3 3 0 0 1 1 5 3 3 0 0 1-1 5 3 3 0 0 1-3 3V4z"/>',
            'chama'    => '<path d="M12 3c1 3-2 4-2 7a2 2 0 0 0 4 0c1 1 2 2 2 4a4 4 0 0 1-8 0c0-4 4-6 4-11z"/>',
            'link'     => '<path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/>',
            'sol'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
        );
        $d = isset($paths[$nome]) ? $paths[$nome] : $paths['alvo'];
        return '<svg class="ico" width="' . (int)$tamanho . '" height="' . (int)$tamanho . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $d . '</svg>';
    }
}

if (!function_exists('card_stat')) {
    // $opts: titulo, valor, sub, cor, icone, delta (numerico, opcional)
    function card_stat($opts)
    {
        $cor = isset($opts['cor']) ? $opts['cor'] : '#38bdf8';
        $icone = isset($opts['icone']) ? icone($opts['icone']) : '';
        $delta = isset($opts['delta']) && $opts['delta'] !== null ? (float)$opts['delta'] : null;
        $deltaHtml = '';
        if ($delta !== null) {
            $classe = $delta >= 0 ? 'up' : 'down';
            $sinal = $delta >= 0 ? '+' : '';
            $deltaHtml = '<span class="stat-delta ' . $classe . '">' . $sinal . number_format($delta, 1, ',', '.') . '%</span>';
        }
        echo '<div class="card card-stat" style="--cor:' . e($cor) . '">'
            . '<div class="stat-ico">' . $icone . '</div>'
            . '<div class="stat-info">'
            . '<span class="stat-titulo">' . e(isset($opts['titulo']) ? $opts['titulo'] : '') . '</span>'
            . '<span class="stat-valor">' . (isset($opts['valor']) ? $opts['valor'] : '') . '</span>'
            . '<span class="stat-sub">' . e(isset($opts['sub']) ? $opts['sub'] : '') . '</span>'
            . '</div>'
            . $deltaHtml
            . '</div>';
    }
}

if (!function_exists('gauge')) {
    function gauge($pct, $cor, $label = '', $tamanho = 180)
    {
        $pct = max(0, min(100, (float)$pct));
        $graus = $pct * 3.6;
        echo '<div class="gauge" style="--cor:' . e($cor) . ';--pct:' . $pct . ';width:' . (int)$tamanho . 'px;height:' . (int)$tamanho . 'px">'
            . '<div class="gauge-anel"></div>'
            . '<div class="gauge-centro">'
            . '<span class="gauge-num" data-count="' . $pct . '">' . number_format($pct, 0, ',', '.') . '%</span>'
            . '<span class="gauge-label">' . e($label) . '</span>'
            . '</div>'
            . '</div>';
    }
}

if (!function_exists('barra_progresso')) {
    function barra_progresso($pct, $cor = '#38bdf8', $label_direita = '')
    {
        $pct = max(0, min(100, (float)$pct));
        echo '<div class="progress-wrap">'
            . '<div class="progress-track">'
            . '<div class="progress-fill" style="width:' . $pct . '%;background:' . e($cor) . '"></div>'
            . '</div>'
            . '<div class="progress-info"><span>' . number_format($pct, 1, ',', '.') . '%</span>'
            . ($label_direita !== '' ? '<span class="muted">' . e($label_direita) . '</span>' : '')
            . '</div></div>';
    }
}

if (!function_exists('badge')) {
    function badge($texto, $cor = '#38bdf8')
    {
        return '<span class="badge" style="--cor:' . e($cor) . '">' . e($texto) . '</span>';
    }
}

if (!function_exists('tabela')) {
    // $headers: array de strings. $rows: array de arrays (celulas ja em HTML ou texto)
    // $opts: data_table (bool), id (string), placeholder (string)
    function tabela($headers, $rows, $vazio = 'Nenhum registro encontrado.', $opts = array())
    {
        $dataTable = !empty($opts['data_table']);
        $id = isset($opts['id']) ? $opts['id'] : '';
        $placeholder = isset($opts['placeholder']) ? $opts['placeholder'] : 'Pesquisar...';

        if ($dataTable) {
            echo '<div class="tabela-dt" data-tabela>';
            echo '<div class="tabela-barra">';
            echo '<input type="search" class="tabela-pesquisa" placeholder="' . e($placeholder) . '" aria-label="Pesquisar na tabela">';
            echo '<label class="tabela-exibir">Exibir <select class="tabela-por-pagina" aria-label="Itens por pagina">';
            foreach (array(10, 25, 50, 100) as $n) {
                echo '<option value="' . $n . '"' . ($n === 10 ? ' selected' : '') . '>' . $n . '</option>';
            }
            echo '<option value="0">Todos</option>';
            echo '</select> por pagina</label>';
            echo '</div>';
        }

        echo '<div class="tabela-wrap"><table class="tabela' . ($dataTable ? ' tabela-ordenavel' : '') . '"' . ($id ? ' id="' . e($id) . '"' : '') . '><thead><tr>';
        foreach ($headers as $h) {
            echo '<th>' . $h . '</th>';
        }
        echo '</tr></thead><tbody>';
        if (empty($rows)) {
            echo '<tr><td colspan="' . count($headers) . '" class="tabela-vazia">' . e($vazio) . '</td></tr>';
        } else {
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $celula) {
                    echo '<td>' . $celula . '</td>';
                }
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';

        if ($dataTable) {
            echo '<div class="tabela-rodape">';
            echo '<span class="tabela-info"></span>';
            echo '<div class="tabela-paginacao"></div>';
            echo '</div>';
            echo '</div>';
        }
    }
}

if (!function_exists('card_bloco')) {
    function card_bloco($titulo, $conteudo, $cor = '#38bdf8', $icone_nome = 'alvo', $extra = '')
    {
        echo '<div class="card card-bloco" style="--cor:' . e($cor) . '">'
            . '<div class="bloco-head">'
            . '<span class="bloco-ico">' . icone($icone_nome, 20) . '</span>'
            . '<h3 class="bloco-titulo">' . e($titulo) . '</h3>'
            . ($extra !== '' ? '<span class="bloco-extra">' . $extra . '</span>' : '')
            . '</div>'
            . '<div class="bloco-body">' . $conteudo . '</div>'
            . '</div>';
    }
}

if (!function_exists('form_campo')) {
    // $opts: name, label, type (text,number,date,select,textarea,hidden), value, options(array), required, min, max, step, placeholder
    function form_campo($opts)
    {
        $tipo = isset($opts['type']) ? $opts['type'] : 'text';
        $name = isset($opts['name']) ? $opts['name'] : '';
        $id = 'f_' . $name . '_' . substr(md5(serialize($opts) . microtime()), 0, 4);
        $label = isset($opts['label']) ? $opts['label'] : '';
        $value = isset($opts['value']) ? $opts['value'] : '';
        $required = !empty($opts['required']) ? ' required' : '';
        $attrs = '';
        foreach (array('min', 'max', 'step', 'placeholder', 'autocomplete') as $a) {
            if (isset($opts[$a]) && $opts[$a] !== '') {
                $attrs .= ' ' . $a . '="' . e($opts[$a]) . '"';
            }
        }
        echo '<div class="campo">';
        if ($tipo === 'hidden') {
            echo '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
            echo '</div>';
            return;
        }
        echo '<label for="' . $id . '">' . e($label) . '</label>';
        if ($tipo === 'select') {
            echo '<select id="' . $id . '" name="' . e($name) . '"' . $required . '>';
            foreach ($opts['options'] as $val => $texto) {
                $sel = ((string)$val === (string)$value) ? ' selected' : '';
                echo '<option value="' . e($val) . '"' . $sel . '>' . e($texto) . '</option>';
            }
            echo '</select>';
        } elseif ($tipo === 'textarea') {
            echo '<textarea id="' . $id . '" name="' . e($name) . '"' . $required . $attrs . '>' . e($value) . '</textarea>';
        } else {
            echo '<input id="' . $id . '" type="' . e($tipo) . '" name="' . e($name) . '" value="' . e($value) . '"' . $required . $attrs . '>';
        }
        echo '</div>';
    }
}

if (!function_exists('flash')) {
    function flash_set($tipo, $msg)
    {
        $_SESSION['flash'] = array('tipo' => $tipo, 'msg' => $msg);
    }

    function flash_render()
    {
        if (!empty($_SESSION['flash'])) {
            $f = $_SESSION['flash'];
            unset($_SESSION['flash']);
            $cor = $f['tipo'] === 'erro' ? '#f87171' : '#22c55e';
            echo '<div class="flash" style="--cor:' . $cor . '">' . e($f['msg']) . '</div>';
        }
    }
}
