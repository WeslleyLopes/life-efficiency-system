<?php
// conexao.php - MySQL (XAMPP)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'B@ckEnd!DB#55DB');
define('DB_NAME', 'ranksistem');

function conectar()
{
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
        die('<div style="font-family:sans-serif;background:#0b0f1a;color:#f87171;padding:40px">'
            . '<h2>Banco não encontrado</h2>'
            . '<p>Execute o instalador: <a style="color:#22c55e" href="/ranksistem/database/install.php">database/install.php</a></p>'
            . '<p style="color:#94a3b8">' . htmlspecialchars($mysqli->connect_error) . '</p></div>');
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

function db()
{
    static $conn = null;
    if ($conn === null) {
        $conn = conectar();
    }
    return $conn;
}

function db_escape($valor)
{
    return mysqli_real_escape_string(db(), (string)$valor);
}

function db_query($sql)
{
    $res = db()->query($sql);
    if ($res === false) {
        die('Erro SQL: ' . db()->error . '<br>' . htmlspecialchars($sql));
    }
    return $res;
}

function db_all($sql)
{
    $res = db_query($sql);
    $rows = array();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function db_one($sql)
{
    $rows = db_all($sql);
    return isset($rows[0]) ? $rows[0] : null;
}

function db_insert_id()
{
    return db()->insert_id;
}
