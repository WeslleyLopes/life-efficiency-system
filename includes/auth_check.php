<?php
// auth_check.php - protege paginas do sistema
if (!isset($_SESSION)) {
    session_start();
}

$paginas_publicas = array('login.php', 'install.php');

$pagina_atual = basename($_SERVER['SCRIPT_NAME']);

if (!isset($_SESSION['user_id']) && !in_array($pagina_atual, $paginas_publicas, true)) {
    header('Location: /ranksistem/pages/login.php');
    exit;
}

if (isset($_SESSION['user_id']) && $pagina_atual === 'login.php') {
    header('Location: /ranksistem/index.php');
    exit;
}
