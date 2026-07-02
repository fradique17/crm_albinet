<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Albinet</title>
    
    <link rel="icon" type="image/png" href="assets/images/logo.jpg">
    
    <script>
        (function () {
            const temaGuardado = localStorage.getItem('theme');
            const prefereEscuro = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (temaGuardado === 'dark' || (!temaGuardado && prefereEscuro)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class', // Isto diz ao Tailwind para escutar a classe "dark" no <html>
            theme: {
                extend: {}
            }
        }
    </script>

    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        /* Ajuste fino para scrollbars escuras e fluidas */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #e2e8f0; }
        ::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #64748b; }

        html.dark ::-webkit-scrollbar-track { background: #020617; }
        html.dark ::-webkit-scrollbar-thumb { background: #1e293b; }
        html.dark ::-webkit-scrollbar-thumb:hover { background: #334155; }
    </style>
</head>
<body class="bg-[#030712] text-slate-100 font-sans min-h-screen antialiased overflow-y-auto">