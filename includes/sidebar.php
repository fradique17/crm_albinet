<?php
$view_ativa  = $_GET['v'] ?? 'dashboard';
$user_perfil = $_SESSION['user_perfil'] ?? '';
?>

<aside class="w-full md:w-64 bg-white dark:bg-slate-900/60 border-r border-slate-200 dark:border-slate-300 dark:border-slate-800/80 p-6 flex flex-col shrink-0 backdrop-blur-xl h-screen overflow-hidden">
    
    <!-- TOPO: logo + nav com scroll interno se necessário -->
    <div class="flex flex-col flex-2 overflow-hidden">
    <a href="index.php?v=dashboard" class="flex justify-center items-center gap-3 mb-8 shrink-0 hover:opacity-80 transition-opacity">
    
        <img src="assets/images/logo.png" alt="Logo Albinet"
            class="block dark:hidden w-44 object-contain">

        <img src="assets/images/darklogo.png" alt="Logo Albinet"
            class="hidden dark:block w-44 object-contain">
    </a>

        <nav class="space-y-1 overflow-y-auto flex-1">

            <a href="index.php?v=dashboard"
               class="flex items-center gap-3 p-3 rounded-xl text-sm font-bold transition <?= $view_ativa === 'dashboard' ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-950' ?>">
                <i data-lucide="grid" class="w-4 h-4"></i>
                Dashboard
            </a>

            <a href="index.php?v=leads"
               class="flex items-center gap-3 p-3 rounded-xl text-sm font-bold transition <?= $view_ativa === 'leads' ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-950' ?>">
                <i data-lucide="git-branch" class="w-4 h-4"></i>
                Leads
            </a>

            <a href="index.php?v=tarefas"
               class="flex items-center gap-3 p-3 rounded-xl text-sm font-bold transition <?= $view_ativa === 'tarefas' ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-950' ?>">
                <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                Tarefas
            </a>

            <?php if (in_array($user_perfil, ['admin', 'gestor'])): ?>
                <a href="index.php?v=users"
                   class="flex items-center gap-3 p-3 rounded-xl text-sm font-bold transition <?= $view_ativa === 'users' ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-950' ?>">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    Utilizadores
                </a>
            <?php endif; ?>

            <a href="index.php?v=reports"
               class="flex items-center gap-3 p-3 rounded-xl text-sm font-bold transition <?= $view_ativa === 'reports' ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-950' ?>">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                Relatórios
            </a>

            <?php if ($user_perfil === 'admin'): ?>
                <a href="index.php?v=tools"
                   class="flex items-center gap-3 p-3 rounded-xl text-sm font-bold transition <?= $view_ativa === 'tools' ? 'bg-indigo-600/10 text-indigo-400 border border-indigo-500/10' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-300 dark:hover:bg-slate-950' ?>">
                    <i data-lucide="wrench" class="w-4 h-4"></i>
                    Configurações
                </a>
            <?php endif; ?>

        </nav>
    </div><!-- /flex-1 overflow wrapper -->

    <!-- USER FOOTER — sempre fixo no fundo, nunca empurrado pelo nav -->
    <div class="border-t border-slate-200 dark:border-slate-300 dark:border-slate-800/80 pt-4 shrink-0 mt-auto">
        <div class="flex items-center justify-between">
            <div>
                <p class="py-1 text-sm font-bold text-slate-800 dark:text-slate-200">
                    <?= htmlspecialchars($_SESSION['user_nome'] ?? 'Utilizador') ?>
                </p>

                <?php
                    $perfil_atual = $u['perfil'] ?? $user_perfil ?? 'Membro';

                    $pbadge = match(strtolower($perfil_atual)) {
                        'admin'     => 'bg-red-500/10 text-red-400 border-red-500/20',
                        'gestor'    => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
                        'comercial' => 'bg-green-500/10 text-green-400 border-green-500/20',
                        default     => 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20',
                    };
                ?>
                <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider border <?= $pbadge ?>">
                    <?= htmlspecialchars($perfil_atual) ?>
                </span>
            </div>

            <button type="button"
                    onclick="toggleConfigDropdown(event)"
                    class="text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:text-white p-2 hover:bg-slate-50 dark:bg-slate-950 rounded-xl transition">
                <i data-lucide="settings" class="w-4 h-4"></i>
            </button>
        </div>
    </div>

</aside>

<!-- DROPDOWN CONFIG -->
<div id="dropdown-config-user"
     class="hidden fixed w-52 bg-white dark:bg-[#0b0f19] border border-slate-300 dark:border-slate-800 rounded-xl shadow-2xl p-1.5 z-50">

    <button type="button"
            onclick="abrirModalConfig('nome')"
            class="w-full flex items-center gap-2 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60 rounded-lg transition text-left">
        <i data-lucide="user" class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400"></i>
        Alterar Nome
    </button>

    <button type="button"
            onclick="abrirModalConfig('password')"
            class="w-full flex items-center gap-2 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60 rounded-lg transition text-left">
        <i data-lucide="lock" class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400"></i>
        Alterar Palavra-passe
    </button>

    <button type="button"
            id="theme-toggle-btn"
            onclick="toggleTheme()"
            class="w-full flex items-center gap-2 px-3 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800/60 rounded-lg transition text-left">
        </button>

    <div class="h-px bg-slate-200 dark:bg-slate-800/60 my-1"></div>

    <a href="actions/logout.php"
       class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-rose-500 dark:text-rose-400 hover:text-rose-600 dark:hover:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-500/10 rounded-lg transition">
        <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
        Terminar Sessão
    </a>

</div>

<!-- MODAL MELHORADO -->
<div id="modal-config-perfil"
     class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     onclick="fecharModalConfigFora(event)">

    <!-- Backdrop com blur -->
    <div class="absolute inset-0 bg-black/75 backdrop-blur-sm"></div>

    <div id="modal-config-box"
         class="relative bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-700/60 rounded-2xl w-full max-w-md shadow-2xl shadow-black/60 overflow-hidden"
         style="animation: modalEntrada 0.2s ease-out;">

        <!-- Faixa decorativa no topo -->
        <div class="h-1 w-full bg-gradient-to-r from-indigo-600 via-indigo-400 to-cyan-400"></div>

        <div class="p-6 space-y-5">

            <!-- Cabeçalho -->
            <div class="flex items-center justify-between">
                <h3 id="modal-perfil-titulo"
                    class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2"></h3>
                <button type="button"
                        onclick="fecharModalConfig()"
                        class="text-slate-500 dark:text-slate-400 hover:text-slate-200 p-1.5 hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <!-- FORMULÁRIO -->
            <form id="form-perfil"
                  action="actions/profile_quick_update.php"
                  method="POST"
                  onsubmit="return validarFormulario(event)"
                  class="space-y-4">

                <input type="hidden" id="modal-config-acao" name="acao_perfil">
                <input type="hidden" name="view_origem" value="<?= htmlspecialchars($view_ativa) ?>">

                <!-- ── CAMPO NOME ── -->
                <div id="campo-modal-nome" class="hidden space-y-1.5">
                    <label class="block text-xs text-slate-800 dark:text-slate-400 font-medium">Novo Nome</label>
                    <input type="text"
                           id="input-nome"
                           name="nome_utilizador"
                           value="<?= htmlspecialchars($_SESSION['user_nome'] ?? '') ?>"
                           autocomplete="off"
                           class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition placeholder-slate-600">
                    <p id="erro-nome" class="hidden text-xs text-rose-400 flex items-center gap-1">
                        <i data-lucide="alert-circle" class="w-3 h-3 inline"></i>
                        <span></span>
                    </p>
                </div>

                <!-- ── CAMPOS PASSWORD ── -->
                <div id="campo-modal-pass" class="hidden space-y-3">

                    <!-- Password Atual -->
                    <div class="space-y-1.5">
                        <label class="block text-xs text-slate-600 dark:text-slate-400 font-medium">Palavra-passe Atual</label>
                        <div class="relative">
                            <input type="password"
                                   id="input-pass-atual"
                                   name="password_atual"
                                   autocomplete="current-password"
                                   placeholder="••••••••"
                                   class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2.5 pr-10 text-sm text-slate-600 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition placeholder-slate-600">
                            <button type="button" onclick="togglePass('input-pass-atual', this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-600 hover:text-slate-700 dark:text-slate-300 transition">
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Esqueceu a palavra-passe -->
                    <div class="flex justify-end -mt-1">
                        <a href="views/forgot_password.php"
                           class="text-[11px] text-indigo-400 hover:text-indigo-300 transition">
                            Esqueceu-se da palavra-passe?
                        </a>
                    </div>

                    <!-- Nova Password -->
                    <div class="space-y-1.5">
                        <label class="block text-xs text-slate-600 dark:text-slate-400 font-medium">Nova Palavra-passe</label>
                        <div class="relative">
                            <input type="password"
                                   id="input-pass-nova"
                                   name="password_utilizadora"
                                   minlength="4"
                                   autocomplete="new-password"
                                   placeholder="Mínimo 4 caracteres"
                                   oninput="atualizarForca(this.value)"
                                   class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2.5 pr-10 text-sm text-slate-600 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition placeholder-slate-600">
                            <button type="button" onclick="togglePass('input-pass-nova', this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-600 hover:text-slate-700 dark:text-slate-300 transition">
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                        <!-- Barra de força da password -->
                        <div id="forca-container" class="hidden space-y-1 pt-0.5">
                            <div class="flex gap-1">
                                <div id="forca-b1" class="h-1 flex-1 rounded-full bg-slate-800 transition-colors duration-300"></div>
                                <div id="forca-b2" class="h-1 flex-1 rounded-full bg-slate-800 transition-colors duration-300"></div>
                                <div id="forca-b3" class="h-1 flex-1 rounded-full bg-slate-800 transition-colors duration-300"></div>
                                <div id="forca-b4" class="h-1 flex-1 rounded-full bg-slate-800 transition-colors duration-300"></div>
                            </div>
                            <p id="forca-label" class="text-[10px] text-slate-500 dark:text-slate-400"></p>
                        </div>
                    </div>

                    <!-- Confirmar Password -->
                    <div class="space-y-1.5">
                        <label class="block text-xs text-slate-600 dark:text-slate-400 font-medium">Confirmar Nova Palavra-passe</label>
                        <div class="relative">
                            <input type="password"
                                   id="input-pass-confirm"
                                   name="password_confirmacao"
                                   minlength="4"
                                   autocomplete="new-password"
                                   placeholder="Repete a nova palavra-passe"
                                   oninput="verificarCoincidencia()"
                                   class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2.5 pr-10 text-sm text-slate-600 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition placeholder-slate-600">
                            <button type="button" onclick="togglePass('input-pass-confirm', this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-600 hover:text-slate-700 dark:text-slate-300 transition">
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                        <p id="match-label" class="hidden text-[10px] flex items-center gap-1"></p>
                    </div>

                    <!-- Aviso de erro geral do form -->
                    <p id="erro-pass" class="hidden text-xs text-rose-400"></p>
                </div>

                <!-- Botões -->
                <div class="flex gap-2 pt-1">
                    <button type="button"
                            onclick="fecharModalConfig()"
                            class="flex-1 py-2.5 rounded-xl border border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400 text-xs font-medium hover:bg-slate-50 dark:hover:bg-slate-900 transition">
                        Cancelar
                    </button>
                    <button type="submit"
                            id="btn-guardar"
                            class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-bold text-xs transition flex items-center justify-center gap-1.5">
                        <i data-lucide="save" class="w-3.5 h-3.5"></i>
                        Guardar
                    </button>
                </div>

            </form>

        </div>
    </div>

</div>

<style>
@keyframes modalEntrada {
    from { opacity: 0; transform: scale(0.96) translateY(8px); }
    to   { opacity: 1; transform: scale(1)    translateY(0);   }
}
</style>

<script>

// ── DROPDOWN ─────────────────────────────────────────────────────────────────

function toggleConfigDropdown(event) {
    event.stopPropagation();
    const btn      = event.currentTarget;
    const dropdown = document.getElementById('dropdown-config-user');
    const isHidden = dropdown.classList.toggle('hidden');
    
    if (!isHidden) {
        const rect = btn.getBoundingClientRect();
        dropdown.style.top  = (rect.top - dropdown.offsetHeight - 8) + 'px';
        dropdown.style.left = (rect.right - dropdown.offsetWidth) + 'px';
    }
}

// ── MODAL: ABRIR / FECHAR ─────────────────────────────────────────────────────

function abrirModalConfig(tipo) {
    document.getElementById('dropdown-config-user').classList.add('hidden');

    const modal    = document.getElementById('modal-config-perfil');
    const titulo   = document.getElementById('modal-perfil-titulo');
    const campoNome = document.getElementById('campo-modal-nome');
    const campoPass = document.getElementById('campo-modal-pass');
    const acaoInput = document.getElementById('modal-config-acao');

    campoNome.classList.add('hidden');
    campoPass.classList.add('hidden');
    limparErros();

    if (tipo === 'nome') {
        titulo.innerHTML = `<i data-lucide="user" class="w-4 h-4 text-indigo-400"></i> Alterar Nome`;
        campoNome.classList.remove('hidden');
        acaoInput.value = 'atualizar_nome';
        setTimeout(() => document.getElementById('input-nome')?.focus(), 50);
    } else {
        titulo.innerHTML = `<i data-lucide="lock" class="w-4 h-4 text-indigo-400"></i> Alterar Palavra-passe`;
        campoPass.classList.remove('hidden');
        acaoInput.value = 'atualizar_password';
        // Limpar campos de password ao abrir
        ['input-pass-atual','input-pass-nova','input-pass-confirm'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('forca-container').classList.add('hidden');
        document.getElementById('match-label').classList.add('hidden');
        setTimeout(() => document.getElementById('input-pass-atual')?.focus(), 50);
    }

    modal.classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function fecharModalConfig() {
    document.getElementById('modal-config-perfil').classList.add('hidden');
}

function fecharModalConfigFora(event) {
    if (event.target === document.getElementById('modal-config-perfil')) {
        fecharModalConfig();
    }
}

// Fechar com Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') fecharModalConfig();
});

// Fechar dropdown ao clicar fora
window.addEventListener('click', function() {
    const dd = document.getElementById('dropdown-config-user');
    if (dd) dd.classList.add('hidden');
});

// ── MOSTRAR / OCULTAR PASSWORD ────────────────────────────────────────────────

function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const isPass = input.type === 'password';
    input.type = isPass ? 'text' : 'password';
    // Trocar ícone
    btn.innerHTML = isPass
        ? '<i data-lucide="eye-off" class="w-3.5 h-3.5"></i>'
        : '<i data-lucide="eye"     class="w-3.5 h-3.5"></i>';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// ── FORÇA DA PASSWORD ─────────────────────────────────────────────────────────

function atualizarForca(val) {
    const container = document.getElementById('forca-container');
    const label     = document.getElementById('forca-label');
    const barras    = [
        document.getElementById('forca-b1'),
        document.getElementById('forca-b2'),
        document.getElementById('forca-b3'),
        document.getElementById('forca-b4'),
    ];

    if (!val) {
        container.classList.add('hidden');
        return;
    }
    container.classList.remove('hidden');

    let score = 0;
    if (val.length >= 4)  score++;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const cfg = [
        { cor: 'bg-rose-500',   texto: 'Muito fraca',  labelCor: 'text-rose-400'   },
        { cor: 'bg-amber-500',  texto: 'Fraca',        labelCor: 'text-amber-400'  },
        { cor: 'bg-yellow-400', texto: 'Razoável',     labelCor: 'text-yellow-400' },
        { cor: 'bg-emerald-500',texto: 'Forte',        labelCor: 'text-emerald-400'},
    ];

    const nivel = Math.max(0, Math.min(score - 1, 3));

    barras.forEach((b, i) => {
        b.className = 'h-1 flex-1 rounded-full transition-colors duration-300 ' +
            (i <= nivel ? cfg[nivel].cor : 'bg-slate-800');
    });

    label.textContent = cfg[nivel].texto;
    label.className   = 'text-[10px] ' + cfg[nivel].labelCor;

    // Verificar coincidência ao digitar nova pass
    verificarCoincidencia();
}

// ── VERIFICAR COINCIDÊNCIA ────────────────────────────────────────────────────

function verificarCoincidencia() {
    const nova    = document.getElementById('input-pass-nova')?.value    ?? '';
    const confirm = document.getElementById('input-pass-confirm')?.value ?? '';
    const label   = document.getElementById('match-label');

    if (!confirm) {
        label.classList.add('hidden');
        return;
    }

    label.classList.remove('hidden');

    if (nova === confirm) {
        label.innerHTML = `<span class="text-emerald-400 text-[10px]">✓ As passwords coincidem</span>`;
    } else {
        label.innerHTML = `<span class="text-rose-400 text-[10px]">✗ As passwords não coincidem</span>`;
    }
}

// ── VALIDAÇÃO CLIENT-SIDE ANTES DE SUBMETER ───────────────────────────────────

function validarFormulario(event) {
    const acao = document.getElementById('modal-config-acao').value;

    if (acao === 'atualizar_nome') {
        const nome = document.getElementById('input-nome').value.trim();
        if (nome.length < 3) {
            mostrarErroNome('O nome deve ter pelo menos 3 caracteres.');
            event.preventDefault();
            return false;
        }
    }

    if (acao === 'atualizar_password') {
        const atual   = document.getElementById('input-pass-atual').value;
        const nova    = document.getElementById('input-pass-nova').value;
        const confirm = document.getElementById('input-pass-confirm').value;

        if (!atual || !nova || !confirm) {
            mostrarErroPass('Preenche todos os campos.');
            event.preventDefault();
            return false;
        }
        if (nova.length < 4) {
            mostrarErroPass('A nova password deve ter pelo menos 4 caracteres.');
            event.preventDefault();
            return false;
        }
        if (nova !== confirm) {
            mostrarErroPass('As passwords não coincidem.');
            event.preventDefault();
            return false;
        }
    }

    // Feedback visual no botão durante o submit
    const btn = document.getElementById('btn-guardar');
    btn.innerHTML = `<svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" stroke-dasharray="31.4" stroke-dashoffset="10"/>
    </svg> A guardar…`;
    btn.disabled = true;

    return true;
}

function mostrarErroNome(msg) {
    const el   = document.getElementById('erro-nome');
    const span = el.querySelector('span');
    span.textContent = msg;
    el.classList.remove('hidden');
}

function mostrarErroPass(msg) {
    const el = document.getElementById('erro-pass');
    el.textContent = msg;
    el.classList.remove('hidden');
}

function limparErros() {
    document.getElementById('erro-nome')?.classList.add('hidden');
    document.getElementById('erro-pass')?.classList.add('hidden');
    document.getElementById('match-label')?.classList.add('hidden');
}

// ── MODO CLARO / ESCURO ───────────────────────────────────────────────────────

// 1. Aplicar o tema assim que o script carrega (evita piscar cores se possível)
if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
} else {
    document.documentElement.classList.remove('dark');
}

// 2. Função para atualizar o visual do botão (Ícone + Texto)
function updateThemeButtonUI() {
    const btn = document.getElementById('theme-toggle-btn');
    if (!btn) return;
    
    const isDark = document.documentElement.classList.contains('dark');
    
    if (isDark) {
        btn.innerHTML = `
            <i data-lucide="sun" class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400"></i>
            Modo Claro
        `;
    } else {
        btn.innerHTML = `
            <i data-lucide="moon" class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400"></i>
            Modo Escuro
        `;
    }
    
    // Recarregar os ícones do Lucide após alterar o HTML interno
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// 3. Função acionada ao clicar no botão
function toggleTheme() {
    const html = document.documentElement;
    
    if (html.classList.contains('dark')) {
        // Mudar para Modo Claro
        html.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    } else {
        // Mudar para Modo Escuro
        html.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    }
    
    updateThemeButtonUI();
}

// Atualizar o botão assim que o DOM estiver pronto
document.addEventListener('DOMContentLoaded', updateThemeButtonUI);

</script>