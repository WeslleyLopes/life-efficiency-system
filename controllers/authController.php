<?php
// authController.php
require_once __DIR__ . '/../config.php';

$acao = isset($_GET['acao']) ? $_GET['acao'] : (isset($_POST['acao']) ? $_POST['acao'] : '');

if ($acao === 'logout') {
    $_SESSION = array();
    session_destroy();
    header('Location: /ranksistem/pages/login.php');
    exit;
}

if ($acao === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $senha = isset($_POST['senha']) ? $_POST['senha'] : '';

    $stmt = db()->prepare("SELECT * FROM usuarios WHERE username = ? LIMIT 1");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $usuario = $res->fetch_assoc();
    $stmt->close();

    if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
        $_SESSION['user_id'] = $usuario['id'];
        $_SESSION['user_nome'] = $usuario['nome'];
        $_SESSION['user_username'] = $usuario['username'];
        header('Location: /ranksistem/index.php');
        exit;
    }

    flash_set('erro', 'Usuario ou senha invalidos.');
    header('Location: /ranksistem/pages/login.php');
    exit;
}

header('Location: /ranksistem/pages/login.php');
exit;
