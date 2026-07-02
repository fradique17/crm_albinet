<?php

session_start();

// Caminho robusto independente de onde o ficheiro é chamado
require_once dirname(__DIR__) . '/config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../views/login.php');
    exit;
}

$user_id     = (int) $_SESSION['user_id'];
$acao        = $_POST['acao_perfil'] ?? '';
$view_origem = preg_replace('/[^a-z_]/', '', $_POST['view_origem'] ?? 'dashboard'); // sanitização

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php?v={$view_origem}");
    exit;
}

try {

    // ── ALTERAR NOME ─────────────────────────────────────────────────────────
    if ($acao === 'atualizar_nome') {

        $novo_nome = trim($_POST['nome_utilizador'] ?? '');

        if (empty($novo_nome)) {
            $_SESSION['erro'] = 'O nome não pode estar vazio.';
            header("Location: ../index.php?v={$view_origem}");
            exit;
        }

        if (mb_strlen($novo_nome) < 3) {
            $_SESSION['erro'] = 'O nome deve ter pelo menos 3 caracteres.';
            header("Location: ../index.php?v={$view_origem}");
            exit;
        }

        $stmt = $pdo->prepare("UPDATE utilizadores SET nome = ? WHERE id_utilizador = ?");
        $stmt->execute([$novo_nome, $user_id]);

        $_SESSION['user_nome'] = $novo_nome;
        $_SESSION['sucesso']   = 'Nome atualizado com sucesso.';

    // ── ALTERAR PASSWORD ──────────────────────────────────────────────────────
    } elseif ($acao === 'atualizar_password') {

        // NOTA: NÃO usar trim() em passwords — pode cortar caracteres intencionais
        $password_atual = $_POST['password_atual']        ?? '';
        $nova_password  = $_POST['password_utilizadora']  ?? '';
        $confirmacao    = $_POST['password_confirmacao']  ?? '';

        if (empty($password_atual) || empty($nova_password) || empty($confirmacao)) {
            $_SESSION['erro'] = 'Preenche todos os campos.';
            header("Location: ../index.php?v={$view_origem}");
            exit;
        }

        if (strlen($nova_password) < 4) {
            $_SESSION['erro'] = 'A nova password deve ter pelo menos 4 caracteres.';
            header("Location: ../index.php?v={$view_origem}");
            exit;
        }

        if ($nova_password !== $confirmacao) {
            $_SESSION['erro'] = 'A confirmação da password não coincide.';
            header("Location: ../index.php?v={$view_origem}");
            exit;
        }

        // Buscar hash atual
        $stmt = $pdo->prepare("SELECT password_hash FROM utilizadores WHERE id_utilizador = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $_SESSION['erro'] = 'Utilizador não encontrado.';
            header("Location: ../index.php?v={$view_origem}");
            exit;
        }

        // Verificar password atual
        if (!password_verify($password_atual, $user['password_hash'])) {
            $_SESSION['erro'] = 'A password atual está incorreta.';
            header("Location: ../index.php?v={$view_origem}");
            exit;
        }

        // Impedir password igual à atual
        if (password_verify($nova_password, $user['password_hash'])) {
            $_SESSION['erro'] = 'A nova password não pode ser igual à atual.';
            header("Location: ../index.php?v={$view_origem}");
            exit;
        }

        // Gerar hash e gravar
        $password_hash = password_hash($nova_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE utilizadores SET password_hash = ? WHERE id_utilizador = ?");
        $stmt->execute([$password_hash, $user_id]);

        $_SESSION['sucesso'] = 'Password alterada com sucesso.';

    } else {
        $_SESSION['erro'] = 'Ação inválida.';
    }

} catch (PDOException $e) {
    // Em produção não expõe a mensagem; em dev podes ligar o erro
    $_SESSION['erro'] = 'Erro interno ao atualizar perfil.';
    // error_log($e->getMessage()); // descomenta para debug
}

header("Location: ../index.php?v={$view_origem}");
exit;