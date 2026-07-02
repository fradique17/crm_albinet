<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CRM Albinet</title>
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
        <h1 class="title">Login</h1>
    
        <?php 
        // Verifica se existe um erro na sessão
        if (isset($_SESSION['error'])): 
            $erro = $_SESSION['error'];
        ?>
            <p style="color:#ef4444; text-align:center; margin-bottom:16px; font-size:14px;">
                <?= $erro === 'empty' ? 'Preenche todos os campos.' : 'Email ou password incorretos.' ?>
            </p>
        <?php 
            // Apaga a variável da sessão. Assim, se o utilizador fizer F5, o erro já não aparece.
            unset($_SESSION['error']); 
        endif; 
        ?>

        <?php 
        // A mesma lógica para mensagens de sucesso
        if (isset($_SESSION['msg']) && $_SESSION['msg'] === 'reset_ok'): 
        ?>
            <p style="color:#10b981; text-align:center; margin-bottom:16px; font-size:14px;">
                Password alterada com sucesso! Já podes entrar.
            </p>
        <?php 
            unset($_SESSION['msg']);
        endif; 
        ?>

        <form action="../actions/auth_login.php" method="POST">
            <div class="input-box">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required placeholder="exemplo@albinet.pt">
            </div>

            <div class="input-box">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required placeholder="••••••••">
            </div>

            <div class="login-actions">
                <a href="forgot_password.php">Esqueceu-se da password?</a>
            </div>

            <button type="submit">Entrar</button>
        </form>
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