<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Validação do Token CSRF
$postToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';

if (!$postToken || $postToken !== $sessionToken) {
    http_response_code(403);
    die("Erro de segurança: Pedido inválido (CSRF Token Inválido).");
}

$email = trim($_POST['email'] ?? '');

if (!$email) {
    // Guarda o erro na sessão e redireciona limpo
    $_SESSION['error'] = 'empty';
    header("Location: ../views/forgot_password.php");
    exit;
}

// Verifica se o email existe
$stmt = $pdo->prepare("SELECT id_utilizador FROM utilizadores WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

// Executa a lógica APENAS se o utilizador existir
if ($user) {
    // Gera um token aleatório e define validade para 1 hora
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Atualiza a BD com o token
    $stmt = $pdo->prepare("UPDATE utilizadores SET reset_token = ?, reset_expires = ? WHERE email = ?");
    $stmt->execute([$token, $expires, $email]);

    // Monta o link absoluto
    $pasta_base = dirname($_SERVER['PHP_SELF'], 2);
    $link_recuperacao = "http://" . $_SERVER['HTTP_HOST'] . $pasta_base . "/views/reset_password.php?token=" . $token;

    // Preparar o Email
    $assunto = "Recuperacao de Password - CRM Albinet";
    $mensagem = "Olá,\n\nRecebemos um pedido para repor a password da tua conta.\n";
    $mensagem .= "Clica no link abaixo para criar uma nova password:\n\n";
    $mensagem .= $link_recuperacao . "\n\n";
    $mensagem .= "Se não pediste esta alteração, podes ignorar este email.\n";

    $headers = "From: noreply@albinet.pt\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // Tenta enviar o email
    mail($email, $assunto, $mensagem, $headers);
    
    // Dica para testes em Localhost: 
    // Como a função mail() costuma falhar sem servidor SMTP configurado, 
    // podes descomentar a linha abaixo para guardar o link num ficheiro e conseguires testar o reset:
    // file_put_contents('debug_link.txt', $link_recuperacao);
}

// Independentemente de o email existir ou de a função mail() ter falhado, 
// damos SEMPRE a mesma mensagem de sucesso. O URL fica limpo.
$_SESSION['msg'] = 'enviado';
header("Location: ../views/forgot_password.php");
exit;