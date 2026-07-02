<?php
require_once __DIR__ . '/../config/database.php';
$token = $_GET['token'] ?? '';

// Verifica se o token é válido e não expirou
$valido = false;
if ($token) {
    $stmt = $pdo->prepare("SELECT id_utilizador FROM utilizadores WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    if ($stmt->fetch()) {
        $valido = true;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Nova Password - CRM Albinet</title>
    <link rel="stylesheet" href="../assets/css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <h1 class="title">Nova Password</h1>
        
        <?php if (!$valido): ?>
            <p style="color:#ef4444; text-align:center; margin-bottom:16px; font-size:14px;">
                O link de recuperação é inválido ou já expirou.
            </p>
            <div style="text-align:center; margin-top: 20px;">
                <a href="forgot_password.php" style="color:#818cf8; text-decoration:none; font-size:14px;">Pedir novo link</a>
            </div>
        <?php else: ?>
            <?php if (isset($_GET['error'])): ?>
                <p style="color:#ef4444; text-align:center; margin-bottom:16px; font-size:14px;">
                    As passwords não coincidem! Tenta novamente.
                </p>
            <?php endif; ?>

            <form action="../actions/auth_reset.php" method="POST">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                
                <div class="input-box">
                    <label for="password">Nova Password</label>
                    <input type="password" name="password" id="password" required placeholder="Mínimo 4 caracteres" minlength="4">
                </div>

                <div class="input-box">
                    <label for="confirm_password">Confirmar Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required placeholder="Repete a password">
                </div>

                <button type="submit">Guardar Nova Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>