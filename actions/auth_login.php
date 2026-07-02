<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    // Guarda o erro na sessão e redireciona para o URL limpo
    $_SESSION['error'] = 'empty';
    header("Location: ../views/login.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    // Guarda o erro na sessão e redireciona para o URL limpo
    $_SESSION['error'] = 'invalid';
    header("Location: ../views/login.php");
    exit;
}

session_regenerate_id(true);

$_SESSION['user_id'] = $user['id_utilizador'];
$_SESSION['user_nome']   = $user['nome'];
$_SESSION['user_perfil'] = $user['perfil'];

header("Location: ../index.php");
exit;