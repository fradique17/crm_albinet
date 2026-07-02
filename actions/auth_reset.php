<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

// Verifica se as passwords coincidem
if (!$token || !$password || $password !== $confirm) {
    header("Location: ../views/reset_password.php?token=" . urlencode($token) . "&error=1");
    exit;
}

// Verifica o token na BD novamente por precaução
$stmt = $pdo->prepare("SELECT id_utilizador FROM utilizadores WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if ($user) {
    // Encripta a nova password
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Atualiza a password e APAGA o token para que o link não possa ser usado uma 2ª vez
    $update = $pdo->prepare("UPDATE utilizadores SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id_utilizador = ?");
    $update->execute([$hash, $user['id_utilizador']]);

    header("Location: ../views/login.php?msg=reset_ok");
    exit;
} else {
    header("Location: ../views/reset_password.php?token=" . urlencode($token) . "&error=invalid");
    exit;
}