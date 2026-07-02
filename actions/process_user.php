<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Importa a ligação à base de dados do teu grupo
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recolha e sanitização de dados do formulário
    $nome  = filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $senha = $_POST['password'] ?? '';

    // Validação mínima de preenchimento
    if (!$nome || !$email || strlen($senha) < 4) {
        $_SESSION['msg_erro'] = "Preencha todos os campos. A senha deve ter pelo menos 4 caracteres.";
        header("Location: ../index.php?v=users");
        exit;
    }

    try {
        // 1. Verificar se o e-mail já existe na tabela (usando o nome correto: id_utilizador)
        $checkEmail = $pdo->prepare("SELECT id_utilizador FROM utilizadores WHERE email = :email LIMIT 1");
        $checkEmail->execute(['email' => $email]);
        
        if ($checkEmail->fetch()) {
            $_SESSION['msg_erro'] = "Este endereço de e-mail já se encontra registado.";
            header("Location: ../index.php?v=users");
            exit;
        }

        // 2. Encriptar a password de forma segura
        $senha_encriptada = password_hash($senha, PASSWORD_DEFAULT);

        // 3. Inserir o utilizador na Base de Dados
        $sql = "INSERT INTO utilizadores (nome, email, password_hash, perfil, data_criacao) 
                VALUES (:nome, :email, :password_hash, 'comercial', CURDATE())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'nome'          => $nome,
            'email'         => $email,
            'password_hash' => $senha_encriptada
        ]);

        $_SESSION['msg_sucesso'] = "Utilizador " . htmlspecialchars($nome) . " registado com sucesso!";
        header("Location: ../index.php?v=users");
        exit;

    } catch (PDOException $e) {
        error_log('Erro ao criar utilizador: ' . $e->getMessage());
        $_SESSION['msg_erro'] = "Erro interno ao registar o utilizador. Tenta novamente.";
        header("Location: ../index.php?v=users");
        exit;
    }
} else {
    header("Location: ../index.php");
    exit;
}