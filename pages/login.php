<?php
require_once __DIR__ . '/../config.php';
if (isset($_SESSION['user_id'])) {
    header('Location: /ranksistem/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar · Rank Sistem</title>
    <link rel="stylesheet" href="/ranksistem/assets/css/tema.css">
</head>
<body class="login-body">
    <div class="login-card">
        <div class="login-marca">
            <span class="login-ico"><?php echo icone('raio', 30); ?></span>
            <h1>Rank Sistem</h1>
            <p>Medidor de Eficiencia Pessoal</p>
        </div>
        <?php flash_render(); ?>
        <form method="post" action="/ranksistem/controllers/authController.php?acao=login" class="form-login">
            <div class="campo">
                <label for="username">Usuario</label>
                <input id="username" type="text" name="username" required autofocus>
            </div>
            <div class="campo">
                <label for="senha">Senha</label>
                <input id="senha" type="password" name="senha" required>
            </div>
            <button type="submit" class="btn btn-primario">Entrar</button>
        </form>
        <p class="login-dica">Padrao inicial: <strong>admin</strong> / <strong>admin</strong></p>
    </div>
</body>
</html>
