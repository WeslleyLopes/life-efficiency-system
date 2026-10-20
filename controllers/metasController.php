<?php
// metasController.php - ciclo operacional de metas e status dos diagnosticos
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

$acao = isset($_POST['acao']) ? $_POST['acao'] : (isset($_GET['acao']) ? $_GET['acao'] : '');
$voltar = '/ranksistem/pages/metas.php';

function met_redir($url)
{
    header('Location: ' . $url);
    exit;
}

if ($acao === 'add_meta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = metas_add($_POST);
    if ($id) {
        flash_set('ok', 'Meta criada na etapa de planejamento.');
    } else {
        flash_set('erro', 'Informe um titulo para a meta.');
    }
    met_redir($voltar);
}

if ($acao === 'avancar_etapa' && isset($_GET['id'])) {
    $novo = metas_avancar_etapa((int)$_GET['id']);
    if ($novo === 'concluida') {
        flash_set('ok', 'Ciclo encerrado: a meta foi concluida.');
    } elseif ($novo) {
        flash_set('ok', 'Meta movida para a etapa de ' . ucfirst($novo) . '.');
    } else {
        flash_set('erro', 'Meta nao encontrada.');
    }
    met_redir($voltar);
}

if ($acao === 'concluir' && isset($_GET['id'])) {
    metas_mudar_status((int)$_GET['id'], 'concluida');
    flash_set('ok', 'Meta marcada como concluida.');
    met_redir($voltar);
}

if ($acao === 'abandonar' && isset($_GET['id'])) {
    metas_mudar_status((int)$_GET['id'], 'abandonada');
    flash_set('ok', 'Meta abandonada (a decisao fica registrada).');
    met_redir($voltar);
}

if ($acao === 'reativar' && isset($_GET['id'])) {
    metas_mudar_status((int)$_GET['id'], 'ativa');
    flash_set('ok', 'Meta reaberta.');
    met_redir($voltar);
}

if ($acao === 'del_meta' && isset($_GET['id'])) {
    metas_del((int)$_GET['id']);
    flash_set('ok', 'Meta removida.');
    met_redir($voltar);
}

if ($acao === 'diag_status' && isset($_GET['id'], $_GET['status'])) {
    if (diag_atualizar_status((int)$_GET['id'], $_GET['status'])) {
        flash_set('ok', 'Diagnostico atualizado.');
    } else {
        flash_set('erro', 'Status invalido.');
    }
    met_redir($voltar);
}

met_redir($voltar);
