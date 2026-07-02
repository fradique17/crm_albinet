<?php
require_once __DIR__ . '/../config/database.php';

$token = $_GET['token'] ?? '';
$acao = $_GET['acao'] ?? '';

$lead = null;
$msg = '';
$cor = '';

// Verifica se o token existe na base de dados
if ($token) {
    $stmt = $pdo->prepare("SELECT id_lead, nome_contacto, estado_rgpd FROM leads WHERE token_rgpd = ? LIMIT 1");
    $stmt->execute([$token]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Se a lead existir e o link trouxer uma ação (Aceitar ou Recusar)
if ($lead && $acao) {
    if ($acao === 'aceitar') {
        // Atualizamos o estado para 'Aceite', mas NÃO apagamos o token.
        // Assim, o cliente pode ir ao e-mail amanhã e clicar no botão de "Recusar" se mudar de ideias.
        $update = $pdo->prepare("UPDATE leads SET estado_rgpd = 'Aceite' WHERE id_lead = ?");
        $update->execute([$lead['id_lead']]);
        
        $msg = "Obrigado! O teu consentimento RGPD foi registado com sucesso.";
        $cor = "#10b981"; // Verde
        
    } elseif ($acao === 'recusar') {
        // Se recusar, apagamos a lead completamente do sistema
        $delete = $pdo->prepare("DELETE FROM leads WHERE id_lead = ?");
        $delete->execute([$lead['id_lead']]);
        
        $msg = "Ação concluída. Os teus dados foram removidos permanentemente do nosso sistema.";
        $cor = "#ef4444"; // Vermelho
        $lead = null; // A lead já não existe
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <title>Gestão de Privacidade RGPD - CRM Albinet</title>
    <link rel="stylesheet" href="../assets/css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container" style="max-width: 500px; text-align: center;">
        <h1 class="title">Gestão de Privacidade</h1>
        
        <?php if ($msg): ?>
            <div style="color: <?= $cor ?>; font-weight: bold; margin-top: 20px; font-size: 16px; line-height: 1.5;">
                <?= $msg ?>
            </div>
        <?php elseif (!$lead): ?>
            <p style="color:#ef4444; margin-top:20px; font-size:15px; font-weight: 500;">
                Este link é inválido ou os teus dados já foram removidos da nossa plataforma.
            </p>
        <?php else: ?>
            <p style="color:#64748b; font-size:14px; margin-top: 20px;">
                Por favor, utiliza os botões fornecidos no e-mail que recebeste para gerir o teu consentimento.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>