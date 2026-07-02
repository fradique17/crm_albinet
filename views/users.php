<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// Só administradores podem executar estas ações
if (!isset($_SESSION['user_id']) || $_SESSION['user_perfil'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Validação do token CSRF (protege criar/editar/apagar)
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die("Ação não autorizada. Token de segurança inválido.");
}

$action = $_POST['action'] ?? '';

switch ($action) {

    // ── CRIAR UTILIZADOR ──────────────────────────────────────────
    case 'criar':
        $nome     = trim($_POST['nome'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $perfil   = $_POST['perfil'] ?? 'comercial';

        // Verifica se o e-mail já existe
        $check = $pdo->prepare("SELECT id_utilizador FROM utilizadores WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            header("Location: ../index.php?v=users&err=email");
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO utilizadores (nome, email, password_hash, perfil, data_criacao)
            VALUES (?, ?, ?, ?, CURDATE())
        ");
        $stmt->execute([$nome, $email, $hash, $perfil]);

        $_SESSION['flash_nome'] = $nome;
        header("Location: ../index.php?v=users&msg=criado");
        exit;


    // ── EDITAR UTILIZADOR ─────────────────────────────────────────
    case 'editar':
        $id     = (int)($_POST['id'] ?? 0);
        $nome   = trim($_POST['nome'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $perfil = $_POST['perfil'] ?? 'comercial';
        $password = $_POST['password'] ?? '';

        // Verifica se o e-mail já pertence a outro utilizador
        $check = $pdo->prepare("SELECT id_utilizador FROM utilizadores WHERE email = ? AND id_utilizador != ?");
        $check->execute([$email, $id]);
        if ($check->fetch()) {
            header("Location: ../index.php?v=users&err=email");
            exit;
        }

        if (!empty($password)) {
            // Atualiza também a password
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE utilizadores SET nome = ?, email = ?, password_hash = ?, perfil = ?
                WHERE id_utilizador = ?
            ");
            $stmt->execute([$nome, $email, $hash, $perfil, $id]);
        } else {
            // Mantém a password atual
            $stmt = $pdo->prepare("
                UPDATE utilizadores SET nome = ?, email = ?, perfil = ?
                WHERE id_utilizador = ?
            ");
            $stmt->execute([$nome, $email, $perfil, $id]);
        }

        $_SESSION['flash_nome'] = $nome;
        header("Location: ../index.php?v=users&msg=editado");
        exit;


    // ── APAGAR UTILIZADOR ─────────────────────────────────────────
    case 'apagar':
        $id = (int)($_POST['id'] ?? 0);

        // Não pode apagar a própria conta
        if ($id === (int)$_SESSION['user_id']) {
            header("Location: ../index.php?v=users&err=self");
            exit;
        }

        // Fetch user name before deleting
        $stmt_nome = $pdo->prepare("SELECT nome FROM utilizadores WHERE id_utilizador = ?");
        $stmt_nome->execute([$id]);
        $nome_user = $stmt_nome->fetchColumn() ?: 'Utilizador';

        $stmt = $pdo->prepare("DELETE FROM utilizadores WHERE id_utilizador = ?");
        $stmt->execute([$id]);

        $_SESSION['flash_nome'] = $nome_user;
        header("Location: ../index.php?v=users&msg=apagado");
        exit;


    default:
        header("Location: ../index.php?v=users");
        exit;
}