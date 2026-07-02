<?php
session_start();

// Gera um token CSRF seguro se ainda não existir na sessão
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Password - CRM Albinet</title>
    <link rel="icon" type="image/png" href="../assets/images/logo.jpg">
    <link rel="stylesheet" href="../assets/css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
    // Verifica a preferência guardada ou o tema do sistema operativo ANTES de renderizar o body
    // NOTA: Confirma se a chave 'theme' é a mesma que estás a usar no teu script do index.php
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
</script>
</head>
<body>
    <button id="theme-toggle" class="theme-toggle-btn" aria-label="Alternar Tema">
    <svg id="theme-toggle-dark-icon" class="theme-icon hidden" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
    <svg id="theme-toggle-light-icon" class="theme-icon hidden" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" fill-rule="evenodd" clip-rule="evenodd"></path></svg>
    </button>

    <div class="login-container">
        <h1 class="title">Recuperar Password</h1>
        
        <?php 
        // 1. Mostrar Erros (ex: email vazio)
        if (isset($_SESSION['error'])): 
        ?>
            <p style="color:#ef4444; text-align:center; margin-bottom:16px; font-size:14px;">
                <?php 
                if ($_SESSION['error'] === 'empty') {
                    echo "Por favor, preenche o campo de email.";
                }
                ?>
            </p>
        <?php 
            unset($_SESSION['error']);
        endif; 
        ?>

        <?php 
        // Mostrar Sucesso
        if (isset($_SESSION['msg']) && $_SESSION['msg'] === 'enviado'): 
        ?>
            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
                <p style="color: #10b981; font-size: 14px; margin-top:0;"><b>Pedido processado com sucesso!</b></p>
                <p style="color: #d1d5db; font-size: 13px; margin-bottom: 0;">Verifica a tua caixa de entrada para repores a password.</p>
            </div>
            <a href="login.php" style="display:block; text-align:center; color:#818cf8; text-decoration:none; font-size:14px; margin-top: 15px;">Voltar ao Login</a>
        
        <?php 
            // A SOLUÇÃO: Apaga a mensagem AQUI, logo depois de a mostrar.
            unset($_SESSION['msg']);
            
        else: // Mostra o formulário APENAS se não houver mensagem de sucesso 
        ?>
            <p style="color:#9ca3af; text-align:center; margin-bottom:20px; font-size:14px;">
                Insere o teu email e vamos gerar um link para definires uma nova password.
            </p>
            
            <form action="../actions/auth_forgot.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="input-box">
                    <label for="email">Email da Conta</label>
                    <input type="email" name="email" id="email" required placeholder="exemplo@albinet.pt">
                </div>
                <button type="submit">Gerar Link de Recuperação</button>
            </form>
            
            <div class="login-actions" style="margin-top: 20px; justify-content: center;">
                <a href="login.php">Voltar ao Login</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
    const themeToggleBtn = document.getElementById('theme-toggle');
    const darkIcon = document.getElementById('theme-toggle-dark-icon');
    const lightIcon = document.getElementById('theme-toggle-light-icon');

    // Mostra o ícone correto de acordo com o tema atual
    if (document.documentElement.classList.contains('dark')) {
        lightIcon.classList.remove('hidden');
    } else {
        darkIcon.classList.remove('hidden');
    }

    themeToggleBtn.addEventListener('click', function() {
        // Alterna ícones
        darkIcon.classList.toggle('hidden');
        lightIcon.classList.toggle('hidden');

        // Alterna a classe no HTML e guarda a escolha
        if (document.documentElement.classList.contains('dark')) {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        } else {
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        }
    });
    </script>
</body>
</html>