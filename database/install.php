<?php
// install.php - Rank Sistem: cria banco, tabelas e dados iniciais
// Acesso: http://localhost/ranksistem/database/install.php

$host = 'localhost';
$user = 'root';
$pass = 'B@ckEnd!DB#55DB';
$dbname = 'ranksistem';

$mensagens = array();
$erro = null;

try {
    $mysqli = @new mysqli($host, $user, $pass);
    if ($mysqli->connect_error) {
        throw new Exception('Falha na conexao MySQL: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');

    $mysqli->query("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $mysqli->select_db($dbname);

    $tabelas = array();

    $tabelas[] = "CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        senha_hash VARCHAR(255) NOT NULL,
        nome VARCHAR(100) NOT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tabelas[] = "CREATE TABLE IF NOT EXISTS config_pesos_pilares (
        id INT AUTO_INCREMENT PRIMARY KEY,
        peso_financeiro TINYINT NOT NULL DEFAULT 25,
        peso_academico TINYINT NOT NULL DEFAULT 25,
        peso_fisico TINYINT NOT NULL DEFAULT 25,
        peso_social TINYINT NOT NULL DEFAULT 25
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tabelas[] = "CREATE TABLE IF NOT EXISTS checkin_diario (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data DATE NOT NULL UNIQUE,
        energia TINYINT NOT NULL DEFAULT 5,
        humor TINYINT NOT NULL DEFAULT 5,
        estresse TINYINT NOT NULL DEFAULT 5,
        sono_horas DECIMAL(4,1) NOT NULL DEFAULT 7.0,
        notas VARCHAR(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // ---------- Financeiro ----------
    $tabelas[] = "CREATE TABLE IF NOT EXISTS financeiro_contas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        tipo ENUM('trading','investimento','renda_trabalho') NOT NULL DEFAULT 'trading',
        saldo_inicial DECIMAL(15,2) NOT NULL DEFAULT 1000.00,
        saldo_atual DECIMAL(15,2) NOT NULL DEFAULT 1000.00,
        data_saldo DATE DEFAULT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tabelas[] = "CREATE TABLE IF NOT EXISTS financeiro_movimentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conta_id INT NOT NULL,
        data DATE NOT NULL,
        tipo ENUM('aporte','retirada') NOT NULL,
        valor DECIMAL(15,2) NOT NULL,
        descricao VARCHAR(255) DEFAULT NULL,
        FOREIGN KEY (conta_id) REFERENCES financeiro_contas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tabelas[] = "CREATE TABLE IF NOT EXISTS financeiro_setups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        descricao TEXT,
        regras TEXT,
        ativo TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tabelas[] = "CREATE TABLE IF NOT EXISTS financeiro_operacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        conta_id INT DEFAULT NULL,
        data DATE NOT NULL,
        setup_id INT DEFAULT NULL,
        direcao ENUM('long','short') NOT NULL DEFAULT 'long',
        resultado ENUM('win','loss','breakeven') NOT NULL DEFAULT 'win',
        risco_pct DECIMAL(6,2) NOT NULL DEFAULT 1.00,
        retorno_pct DECIMAL(8,2) NOT NULL DEFAULT 0.00,
        adesao_plano TINYINT NOT NULL DEFAULT 10,
        notas VARCHAR(255) DEFAULT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (conta_id) REFERENCES financeiro_contas(id) ON DELETE SET NULL,
        FOREIGN KEY (setup_id) REFERENCES financeiro_setups(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // ---------- Importacao de planilha (carteira / notas) ----------
    $tabelas[] = "CREATE TABLE IF NOT EXISTS financeiro_importacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        arquivo VARCHAR(255) NOT NULL,
        total_linhas INT NOT NULL DEFAULT 0,
        importadas INT NOT NULL DEFAULT 0,
        credito_total DECIMAL(15,2) NOT NULL DEFAULT 0,
        debito_total DECIMAL(15,2) NOT NULL DEFAULT 0,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tabelas[] = "CREATE TABLE IF NOT EXISTS financeiro_regras_ativo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        padrao VARCHAR(80) NOT NULL,
        classe ENUM('acao','indice') NOT NULL DEFAULT 'indice',
        descricao VARCHAR(150) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tabelas[] = "CREATE TABLE IF NOT EXISTS financeiro_ativos_movimentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        importacao_id INT DEFAULT NULL,
        data DATE NOT NULL,
        tipo ENUM('credito','debito') NOT NULL DEFAULT 'credito',
        operacao VARCHAR(120) DEFAULT NULL,
        ativo_codigo VARCHAR(40) NOT NULL,
        ativo_nome VARCHAR(160) DEFAULT NULL,
        corretora VARCHAR(120) DEFAULT NULL,
        classe ENUM('acao','indice','outro') NOT NULL DEFAULT 'acao',
        quantidade DECIMAL(18,4) NOT NULL DEFAULT 0,
        preco_unitario DECIMAL(15,4) NOT NULL DEFAULT 0,
        valor_total DECIMAL(15,2) NOT NULL DEFAULT 0,
        conta_id INT DEFAULT NULL,
        criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (importacao_id) REFERENCES financeiro_importacoes(id) ON DELETE CASCADE,
        FOREIGN KEY (conta_id) REFERENCES financeiro_contas(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // ---------- Academico ----------
    $tabelas[] = "CREATE TABLE IF NOT EXISTS materias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        cor VARCHAR(20) NOT NULL DEFAULT '#a78bfa',
        ativo TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tabelas[] = "CREATE TABLE IF NOT EXISTS modulos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        materia_id INT NOT NULL,
        titulo VARCHAR(150) NOT NULL,
        ordem INT NOT NULL DEFAULT 1,
        data_inicio DATE DEFAULT NULL,
        data_fim_prevista DATE DEFAULT NULL,
        horas_meta DECIMAL(6,1) NOT NULL DEFAULT 20.0,
        concluido TINYINT(1) NOT NULL DEFAULT 0,
        FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tabelas[] = "CREATE TABLE IF NOT EXISTS estudos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        modulo_id INT DEFAULT NULL,
        data DATE NOT NULL,
        minutos INT NOT NULL DEFAULT 50,
        resumo TEXT,
        retencao TINYINT DEFAULT NULL,
        FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // ---------- Fisico (Calistenia) ----------
    $tabelas[] = "CREATE TABLE IF NOT EXISTS exercicios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        grupo VARCHAR(50) NOT NULL DEFAULT 'Geral',
        ativo TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tabelas[] = "CREATE TABLE IF NOT EXISTS exercicios_niveis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exercicio_id INT NOT NULL,
        nivel INT NOT NULL,
        nome VARCHAR(120) NOT NULL,
        fator_dificuldade DECIMAL(4,2) NOT NULL DEFAULT 1.00,
        FOREIGN KEY (exercicio_id) REFERENCES exercicios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tabelas[] = "CREATE TABLE IF NOT EXISTS treinos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data DATE NOT NULL UNIQUE,
        energia_pre TINYINT DEFAULT NULL,
        sono_horas DECIMAL(4,1) DEFAULT NULL,
        dor_muscular TINYINT DEFAULT NULL,
        observacoes VARCHAR(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tabelas[] = "CREATE TABLE IF NOT EXISTS treino_series (
        id INT AUTO_INCREMENT PRIMARY KEY,
        treino_id INT NOT NULL,
        exercicio_id INT NOT NULL,
        nivel_usado INT NOT NULL DEFAULT 1,
        series INT NOT NULL DEFAULT 3,
        reps INT NOT NULL DEFAULT 10,
        FOREIGN KEY (treino_id) REFERENCES treinos(id) ON DELETE CASCADE,
        FOREIGN KEY (exercicio_id) REFERENCES exercicios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // ---------- Social ----------
    $tabelas[] = "CREATE TABLE IF NOT EXISTS social_registros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data DATE NOT NULL,
        tipo ENUM('conexao','lazer','networking','desconexao') NOT NULL DEFAULT 'conexao',
        descricao VARCHAR(255) NOT NULL,
        duracao_min INT NOT NULL DEFAULT 60,
        qualidade TINYINT DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tabelas[] = "CREATE TABLE IF NOT EXISTS social_checkins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        semana_inicio DATE NOT NULL UNIQUE,
        recarga_mental TINYINT NOT NULL DEFAULT 5,
        observacoes VARCHAR(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // ---------- Cross-pilar ----------
    $tabelas[] = "CREATE TABLE IF NOT EXISTS pilar_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data DATE NOT NULL,
        pilar ENUM('financeiro','academico','fisico','social') NOT NULL,
        score DECIMAL(5,2) NOT NULL DEFAULT 0,
        UNIQUE KEY unico (data, pilar)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $tabelas[] = "CREATE TABLE IF NOT EXISTS planos_experimentais (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pilar ENUM('financeiro','academico','fisico','social','cross') NOT NULL DEFAULT 'financeiro',
        nome VARCHAR(150) NOT NULL,
        hipotese TEXT,
        data_inicio DATE NOT NULL,
        data_fim DATE DEFAULT NULL,
        resultado_nota TINYINT DEFAULT NULL,
        observacoes TEXT,
        status ENUM('ativo','concluido','cancelado') NOT NULL DEFAULT 'ativo'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    foreach ($tabelas as $sql) {
        if (!$mysqli->query($sql)) {
            throw new Exception('Erro ao criar tabela: ' . $mysqli->error);
        }
    }
    $mensagens[] = 'Tabelas verificadas/criadas com sucesso.';

    // ---------- Migracoes de colunas (bancos ja existentes) ----------
    $colunasNovas = array(
        'financeiro_contas' => array(
            'data_saldo' => "ALTER TABLE financeiro_contas ADD COLUMN data_saldo DATE DEFAULT NULL AFTER saldo_atual",
        ),
    );
    foreach ($colunasNovas as $tabela => $cols) {
        foreach ($cols as $coluna => $sqlAlter) {
            $chk = $mysqli->query("SHOW COLUMNS FROM `$tabela` LIKE '" . $mysqli->real_escape_string($coluna) . "'");
            if ($chk && $chk->num_rows === 0) {
                if (!$mysqli->query($sqlAlter)) {
                    throw new Exception("Erro ao adicionar coluna $coluna em $tabela: " . $mysqli->error);
                }
                $mensagens[] = "Coluna $coluna adicionada em $tabela.";
            }
        }
    }

    // ---------- Seeds ----------
    // Usuario admin (senha: admin)
    $res = $mysqli->query("SELECT COUNT(*) AS qtd FROM usuarios");
    $qtd = (int)$res->fetch_assoc()['qtd'];
    if ($qtd === 0) {
        $hash = password_hash('admin', PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("INSERT INTO usuarios (username, senha_hash, nome) VALUES ('admin', ?, 'Administrador')");
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $stmt->close();
        $mensagens[] = 'Usuario criado: <strong>admin</strong> / senha: <strong>admin</strong>';
    }

    // Pesos dos pilares
    $res = $mysqli->query("SELECT COUNT(*) AS qtd FROM config_pesos_pilares");
    if ((int)$res->fetch_assoc()['qtd'] === 0) {
        $mysqli->query("INSERT INTO config_pesos_pilares (peso_financeiro, peso_academico, peso_fisico, peso_social) VALUES (25,25,25,25)");
    }

    // Setups de trading
    $res = $mysqli->query("SELECT COUNT(*) AS qtd FROM financeiro_setups");
    if ((int)$res->fetch_assoc()['qtd'] === 0) {
        $setups = array(
            array('Rompimento (Breakout)', 'Operar a favor da ruptura de uma zona de consolidacao com volume.', '1. Entrar apenas apos fechamento do candle acima/abaixo da zona.\n2. Stop atras do ponto invalido.\n3. Alvo minimo 2x o risco.'),
            array('Price Action', 'Sinais de price action puro (engolfo, pin bar, flip de estrutura).', '1. Identificar estrutura (HH/HL/LH/LL).\n2. Esperar confirmacao do sinal na zona.\n3. Risco maximo 1% por operacao.'),
            array('Correcao a Media', 'Entrada em correcao ate media dinamica a favor da tendencia.', '1. Tendencia definida na HT maior.\n2. Esperar toque/correcao na media.\n3. Sair no topo anterior ou 2x risco.'),
            array('Reversao em Suporte/Resistencia', 'Reacao em niveis testados multiplas vezes.', '1. Nivel com minimo 3 toques.\n2. Confirmacao de reacao.\n3. Alvo conservador na media oposta.'),
        );
        $stmt = $mysqli->prepare("INSERT INTO financeiro_setups (nome, descricao, regras) VALUES (?, ?, ?)");
        foreach ($setups as $s) {
            $stmt->bind_param('sss', $s[0], $s[1], $s[2]);
            $stmt->execute();
        }
        $stmt->close();
        $mensagens[] = '4 setups de trading cadastrados.';
    }

    // Regras de classificacao de ativos (indices x acoes)
    $res = $mysqli->query("SELECT COUNT(*) AS qtd FROM financeiro_regras_ativo");
    if ((int)$res->fetch_assoc()['qtd'] === 0) {
        $regras = array(
            array('^', 'indice', 'Tickers com prefixo ^ (indices)'),
            array('IND', 'indice', 'Ibovespa / mini indice'),
            array('WIN', 'indice', 'Mini indice'),
            array('WDO', 'indice', 'Mini dolar'),
            array('DOL', 'indice', 'Dolar futuro'),
            array('BGI', 'indice', 'Boi gordo'),
            array('CCM', 'indice', 'Milho'),
            array('ICF', 'indice', 'Indice de commodities'),
            array('SJC', 'indice', 'Soja'),
        );
        $stmt = $mysqli->prepare("INSERT INTO financeiro_regras_ativo (padrao, classe, descricao) VALUES (?, ?, ?)");
        foreach ($regras as $r) {
            $stmt->bind_param('sss', $r[0], $r[1], $r[2]);
            $stmt->execute();
        }
        $stmt->close();
        $mensagens[] = 'Regras de classificacao de ativos cadastradas (indices x acoes).';
    }

    // Materias
    $res = $mysqli->query("SELECT COUNT(*) AS qtd FROM materias");
    if ((int)$res->fetch_assoc()['qtd'] === 0) {
        $stmt = $mysqli->prepare("INSERT INTO materias (nome, cor) VALUES (?, ?)");
        $m1 = 'Psicologia'; $c1 = '#a78bfa';
        $stmt->bind_param('ss', $m1, $c1); $stmt->execute();
        $m2 = 'Economia'; $c2 = '#fbbf24';
        $stmt->bind_param('ss', $m2, $c2); $stmt->execute();
        $stmt->close();
        $mensagens[] = 'Materias iniciais: Psicologia, Economia.';
    }

    // Exercicios de calistenia com escada de niveis
    $res = $mysqli->query("SELECT COUNT(*) AS qtd FROM exercicios");
    if ((int)$res->fetch_assoc()['qtd'] === 0) {
        $escadas = array(
            array('Puxada / Flexao de Barra', 'Costas', array(
                array(1, 'Flexao de Barra Assistida (elastico)', 1.00),
                array(2, 'Flexao de Barra (prona)', 1.30),
                array(3, 'Flexao de Barra L-Sit', 1.55),
                array(4, 'Archer Pull-up', 1.85),
                array(5, 'Muscle-up', 2.40),
            )),
            array('Flexao de Peito', 'Peito/Triceps', array(
                array(1, 'Flexao de Joelhos', 0.60),
                array(2, 'Flexao de Peito', 1.00),
                array(3, 'Flexao Declinada', 1.20),
                array(4, 'Flexao Diamond', 1.40),
                array(5, 'Pseudo Planche Push-up', 1.75),
                array(6, 'Full Planche Push-up', 2.30),
            )),
            array('Prancha / Core', 'Core', array(
                array(1, 'Prancha', 0.80),
                array(2, 'Prancha Lateral', 1.00),
                array(3, 'Planche Lean', 1.30),
                array(4, 'L-Sit', 1.50),
                array(5, 'Tuck Planche', 1.90),
            )),
            array('Handstand', 'Ombro/Core', array(
                array(1, 'Negativa de Handstand (sair da parede)', 1.00),
                array(2, 'Handstand na Parede', 1.25),
                array(3, 'Handstand Hold livre', 1.60),
                array(4, 'HSPU Negativa', 1.90),
                array(5, 'Handstand Push-up', 2.40),
            )),
            array('Agachamento', 'Pernas', array(
                array(1, 'Agachamento Bico', 0.70),
                array(2, 'Agachamento', 1.00),
                array(3, 'Agachamento Sumo', 1.15),
                array(4, 'Pistol Assistida', 1.45),
                array(5, 'Pistol', 1.80),
            )),
            array('Abdominal Avancado', 'Core', array(
                array(1, 'Abdominal tradicional', 0.60),
                array(2, 'Hanging Knee Raise', 1.00),
                array(3, 'Hanging Leg Raise', 1.30),
                array(4, 'Toes to Bar', 1.55),
            )),
        );
        $stmtEx = $mysqli->prepare("INSERT INTO exercicios (nome, grupo) VALUES (?, ?)");
        $stmtNv = $mysqli->prepare("INSERT INTO exercicios_niveis (exercicio_id, nivel, nome, fator_dificuldade) VALUES (?, ?, ?, ?)");
        foreach ($escadas as $ex) {
            $stmtEx->bind_param('ss', $ex[0], $ex[1]);
            $stmtEx->execute();
            $exId = $mysqli->insert_id;
            foreach ($ex[2] as $nv) {
                $stmtNv->bind_param('iisd', $exId, $nv[0], $nv[1], $nv[2]);
                $stmtNv->execute();
            }
        }
        $stmtEx->close();
        $stmtNv->close();
        $mensagens[] = '6 exercicios de calistenia com escada de progressao cadastrados.';
    }

    // Contas financeiras iniciais (saldo atual informado pelo usuario em Cadastros)
    $res = $mysqli->query("SELECT COUNT(*) AS qtd FROM financeiro_contas");
    if ((int)$res->fetch_assoc()['qtd'] === 0) {
        $mysqli->query("INSERT INTO financeiro_contas (nome, tipo, saldo_inicial, saldo_atual, data_saldo) VALUES ('Conta Principal', 'trading', 10000.00, 10000.00, CURDATE())");
        $mysqli->query("INSERT INTO financeiro_contas (nome, tipo, saldo_inicial, saldo_atual, data_saldo) VALUES ('Investimentos', 'investimento', 20000.00, 20000.00, CURDATE())");
        $mysqli->query("INSERT INTO financeiro_contas (nome, tipo, saldo_inicial, saldo_atual, data_saldo) VALUES ('Renda do Trabalho', 'renda_trabalho', 5000.00, 5000.00, CURDATE())");
        $mensagens[] = 'Contas financeiras iniciais criadas.';
    }

    $status = 'success';
    $titulo = 'Instalacao concluida';
} catch (Exception $e) {
    $erro = $e->getMessage();
    $status = 'error';
    $titulo = 'Erro na instalacao';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - Rank Sistem</title>
    <style>
        body { margin:0; font-family: 'Segoe UI', sans-serif; background:#0b0f1a; color:#e2e8f0; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .box { background:#1a2236; border:1px solid #2a3550; border-radius:16px; padding:40px; max-width:640px; width:92%; box-shadow:0 20px 60px rgba(0,0,0,.5); }
        h1 { margin-top:0; font-size:22px; }
        .ok { color:#22c55e; } .err { color:#f87171; }
        li { margin:8px 0; line-height:1.5; }
        a.btn { display:inline-block; margin-top:20px; background:linear-gradient(135deg,#22c55e,#16a34a); color:#fff; text-decoration:none; padding:12px 28px; border-radius:10px; font-weight:600; }
        code { background:#0b0f1a; padding:2px 8px; border-radius:6px; color:#fbbf24; }
    </style>
</head>
<body>
    <div class="box">
        <h1><?php echo $status === 'success' ? '<span class="ok">&#10003;</span>' : '<span class="err">&#10007;</span>'; ?> <?php echo $titulo; ?> — Rank Sistem</h1>
        <?php if ($erro): ?>
            <p class="err"><?php echo htmlspecialchars($erro); ?></p>
        <?php else: ?>
            <ul>
                <?php foreach ($mensagens as $m): ?>
                    <li><?php echo $m; ?></li>
                <?php endforeach; ?>
            </ul>
            <p style="color:#94a3b8">Login padrao: <code>admin</code> / <code>admin</code></p>
            <a class="btn" href="/ranksistem/pages/login.php">Ir para o Login</a>
        <?php endif; ?>
    </div>
</body>
</html>
