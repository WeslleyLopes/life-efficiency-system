<?php
require_once __DIR__ . '/auth_check.php';
$page_title = isset($page_title) ? $page_title : 'Dashboard';
$page_sub = isset($page_sub) ? $page_sub : '';
$active = isset($active) ? $active : 'dashboard';

$nav_itens = array(
   // 'dashboard'   => array('titulo' => 'Visao Geral',   'url' => '/ranksistem/index.php',              'icone' => 'grafico',  'cor' => '#38bdf8'),
    'financeiro'  => array('titulo' => 'Financeiro',    'url' => '/ranksistem/pages/financeiro.php',   'icone' => 'carteira', 'cor' => '#22c55e'),
    'academico'   => array('titulo' => 'Academico',     'url' => '/ranksistem/pages/academico.php',    'icone' => 'livro',    'cor' => '#a78bfa'),
    'fisico'      => array('titulo' => 'Fisico',        'url' => '/ranksistem/pages/fisico.php',       'icone' => 'halter',   'cor' => '#fb923c'),
   //'social'      => array('titulo' => 'Social',        'url' => '/ranksistem/pages/social.php',       'icone' => 'pessoas',  'cor' => '#2dd4bf'),
   //'correlacoes' => array('titulo' => 'Correlacoes',   'url' => '/ranksistem/pages/correlacoes.php',  'icone' => 'link',     'cor' => '#f472b6'),
   //'metas'       => array('titulo' => 'Metas e Ciclo', 'url' => '/ranksistem/pages/metas.php',       'icone' => 'chama',    'cor' => '#e879f9'),
    'cadastros'   => array('titulo' => 'Cadastros',     'url' => '/ranksistem/pages/cadastros.php',    'icone' => 'alvo',     'cor' => '#fbbf24'),
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?> · Rank Sistem</title>
    <link rel="stylesheet" href="/ranksistem/assets/css/tema.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="marca">
            <span class="marca-ico"><?php echo icone('raio', 26); ?></span>
            <div>
                <strong>Rank Sistem</strong>
                <small>Medidor de Eficiencia</small>
            </div>
        </div>
        <nav class="nav">
            <?php foreach ($nav_itens as $chave => $item): ?>
                <a class="nav-item <?php echo $active === $chave ? 'ativo' : ''; ?>" href="<?php echo $item['url']; ?>" style="--cor:<?php echo $item['cor']; ?>">
                    <span class="nav-ico"><?php echo icone($item['icone'], 20); ?></span>
                    <span><?php echo e($item['titulo']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-rodape">
            <div class="usuario">
                <span class="avatar"><?php echo strtoupper(substr(isset($_SESSION['user_nome']) ? $_SESSION['user_nome'] : 'U', 0, 1)); ?></span>
                <div class="usuario-info">
                    <strong><?php echo e(isset($_SESSION['user_nome']) ? $_SESSION['user_nome'] : 'Usuario'); ?></strong>
                    <a href="/ranksistem/controllers/authController.php?acao=logout">Sair</a>
                </div>
            </div>
        </div>
    </aside>
    <main class="conteudo">
        <header class="topo">
            <div>
                <h1><?php echo e($page_title); ?></h1>
                <?php if ($page_sub !== ''): ?><p class="topo-sub"><?php echo e($page_sub); ?></p><?php endif; ?>
            </div>
            <div class="topo-acoes">
                <button type="button" class="btn-privado" id="btnPrivado" aria-pressed="false"
                    title="Oculta os valores monetarios absolutos na tela (percentuais continuam visiveis)">
                    <span class="btn-privado-ico" aria-hidden="true">◉</span>
                    <span class="btn-privado-txt">Valores absolutos</span>
                </button>
                <div class="topo-data"><?php echo date('d/m/Y'); ?></div>
            </div>
        </header>
        <?php flash_render(); ?>
        <div class="pagina">
