<?php
// Permite que Admin e Gestor acedam à página (Comercial continua bloqueado)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_perfil'], ['admin', 'gestor'])) {
    echo '<div class="p-6 bg-rose-500/10 border border-rose-500/20 rounded-xl text-rose-400 text-sm font-medium">
        <i data-lucide="shield-x" class="inline w-4 h-4 mr-2"></i>
        Acesso negado. Apenas administradores e gestores podem ver os utilizadores.
    </div>';
    return;
}

$msg   = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';
$flash_nome = $_SESSION['flash_nome'] ?? '';
unset($_SESSION['flash_nome']);

// Gerar token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ── PESQUISA ───────────────────────────────────────────────────────────────────
$f_pesquisa = trim($_GET['f_pesquisa'] ?? '');

// ── LÓGICA DE PAGINAÇÃO ────────────────────────────────────────────────────────

// 1. Definir Limite (Quantos por página) - Opcões: 10, 25, 50, 100
$limites_permitidos = [10, 25, 50, 100];
$limite = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if (!in_array($limite, $limites_permitidos)) {
    $limite = 10;
}

// 2. Definir a Página Atual
$pagina = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina < 1) $pagina = 1;

// 3. Contar total de utilizadores (com pesquisa)
if ($f_pesquisa !== '') {
    $termo = '%' . $f_pesquisa . '%';
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM utilizadores WHERE nome LIKE ? OR email LIKE ? OR perfil LIKE ?");
    $stmt_count->execute([$termo, $termo, $termo]);
} else {
    $stmt_count = $pdo->query("SELECT COUNT(*) FROM utilizadores");
}
$total_registos = (int)$stmt_count->fetchColumn();

// 4. Calcular Total de Páginas e Offset
$total_paginas = ceil($total_registos / $limite);
if ($pagina > $total_paginas && $total_paginas > 0) {
    $pagina = $total_paginas;
}
$offset = ($pagina - 1) * $limite;

// 5. Query principal com pesquisa + LIMIT e OFFSET
if ($f_pesquisa !== '') {
    $termo = '%' . $f_pesquisa . '%';
    $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE nome LIKE :t1 OR email LIKE :t2 OR perfil LIKE :t3 ORDER BY id_utilizador DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':t1', $termo);
    $stmt->bindValue(':t2', $termo);
    $stmt->bindValue(':t3', $termo);
} else {
    $stmt = $pdo->prepare("SELECT * FROM utilizadores ORDER BY id_utilizador DESC LIMIT :limit OFFSET :offset");
}
$stmt->bindValue(':limit', $limite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$utilizadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Construtor de URLs Seguros para os links da paginação
$query_params = $_GET;
unset($query_params['msg'], $query_params['err']); // Limpa as msgs de sucesso/erro ao mudar de página
$query_params['limit'] = $limite;

function urlPagina($p, $params) {
    $params['p'] = $p;
    return '?' . http_build_query($params);
}
?>

<div class="flex flex-col gap-4 mb-6">

    <!-- Linha 1: Título + Botão -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
        <div>
            <h1 class="text-2xl font-black tracking-tight bg-gradient-to-r from-indigo-400 to-cyan-400 bg-clip-text text-transparent">
                Gestão de Utilizadores
            </h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                <?php if ($f_pesquisa !== ''): ?>
                    <span class="text-slate-800 dark:text-indigo-400 font-bold"><?= $total_registos ?></span>
                    resultado<?= $total_registos !== 1 ? 's' : '' ?> encontrado<?= $total_registos !== 1 ? 's' : '' ?>.
                <?php else: ?>
                    Total de <span class="text-slate-800 dark:text-indigo-400 font-bold"><?= $total_registos ?></span> utilizadores registados.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($_SESSION['user_perfil'] === 'admin'): ?>
        <button onclick="abrirModalCriar()" class="shrink-0 flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm px-4 py-2.5 rounded-xl shadow-lg shadow-indigo-600/20 transition">
            <i data-lucide="user-plus" class="w-4 h-4"></i> Novo Utilizador
        </button>
        <?php endif; ?>
    </div>

    <!-- Linha 2: Barra de pesquisa global -->
    <form method="GET" action="index.php" id="form-pesquisa-users">
        <input type="hidden" name="v" value="users">
        <input type="hidden" name="limit" value="<?= $limite ?>">

        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none z-10">
                <i data-lucide="search" class="w-4 h-4 text-slate-500 dark:text-slate-400"></i>
            </div>

            <input type="text"
                   name="f_pesquisa"
                   id="input-pesquisa-users"
                   value="<?= htmlspecialchars($f_pesquisa) ?>"
                   placeholder="Pesquisar por nome, e-mail ou perfil..."
                   autocomplete="off"
                   class="w-full bg-white dark:bg-slate-900/60 border <?= $f_pesquisa !== '' ? 'border-indigo-500/60' : 'border-slate-200 border-slate-300 dark:border-slate-800/80' ?> rounded-2xl pl-11 pr-12 py-3 text-sm text-slate-800 dark:text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:bg-white dark:bg-slate-900 transition backdrop-blur-xl">

            <?php if ($f_pesquisa !== ''): ?>
                <a href="index.php?v=users" class="absolute inset-y-0 right-4 flex items-center text-slate-500 dark:text-slate-400 hover:text-rose-400 transition z-10" title="Limpar pesquisa">
                    <i data-lucide="x-circle" class="w-4 h-4"></i>
                </a>
            <?php else: ?>
                <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none z-10">
                    <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none z-10"><kbd class="text-[10px] text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 px-1.5 py-0.5 rounded font-mono">Enter</kbd></div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($f_pesquisa !== ''): ?>
        <div class="flex items-center gap-2 mt-2 px-1">
            <i data-lucide="info" class="w-3 h-3 text-indigo-400 shrink-0"></i>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                A pesquisar por <span class="text-indigo-400 font-semibold">"<?= htmlspecialchars($f_pesquisa) ?>"</span>
                em todos os campos · <a href="index.php?v=users" class="text-rose-400 hover:underline">Limpar</a>
            </p>
        </div>
        <?php endif; ?>
    </form>

</div>



<?php if ($msg === 'criado'): ?>
    <div class="mb-6 flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm px-4 py-3 rounded-xl">
        <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
        <span class="flex-1">Utilizador <strong>"<?= htmlspecialchars($flash_nome) ?>"</strong> criado com sucesso.</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-emerald-400 hover:text-emerald-200 transition"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
<?php elseif ($msg === 'editado'): ?>
    <div class="mb-6 flex items-center gap-2 bg-blue-500/10 border border-blue-500/20 text-blue-400 text-sm px-4 py-3 rounded-xl">
        <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
        <span class="flex-1">Utilizador <strong>"<?= htmlspecialchars($flash_nome) ?>"</strong> atualizado com sucesso.</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-blue-400 hover:text-blue-200 transition"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
<?php elseif ($msg === 'apagado'): ?>
    <div class="mb-6 flex items-center gap-2 bg-amber-500/10 border border-amber-500/20 text-amber-400 text-sm px-4 py-3 rounded-xl">
        <i data-lucide="trash-2" class="w-4 h-4 shrink-0"></i>
        <span class="flex-1">Utilizador <strong>"<?= htmlspecialchars($flash_nome) ?>"</strong> removido.</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-amber-400 hover:text-amber-200 transition"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
<?php elseif ($error === 'self'): ?>
    <div class="mb-6 flex items-center gap-2 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm px-4 py-3 rounded-xl">
        <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
        <span class="flex-1">Não podes apagar a tua própria conta.</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-rose-400 hover:text-rose-200 transition"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
<?php elseif ($error === 'email'): ?>
    <div class="mb-6 flex items-center gap-2 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm px-4 py-3 rounded-xl">
        <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
        <span class="flex-1">Esse e-mail já está em uso.</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-rose-400 hover:text-rose-200 transition"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
<?php endif; ?>

<div class="bg-white dark:bg-slate-900/40 border border-slate-200 border-slate-300 dark:border-slate-800/80 rounded-2xl overflow-hidden backdrop-blur-xl">
    
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b border-slate-200 border-slate-300 dark:border-slate-800/80 bg-slate-50 dark:bg-slate-50 dark:bg-slate-950/40 text-slate-600 dark:text-slate-400 text-xs font-mono uppercase tracking-wider">
                    <th class="p-4 font-bold">ID</th>
                    <th class="p-4 font-bold">Nome</th>
                    <th class="p-4 font-bold">E-mail</th>
                    <th class="p-4 font-bold">Perfil</th>
                    <th class="p-4 font-bold">Data de Criação</th>
                    <th class="p-4 font-bold text-center"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50 text-sm">
                <?php if (count($utilizadores) > 0): ?>
                    <?php foreach ($utilizadores as $u): ?>
                    <tr class="hover:bg-slate-100 dark:hover:bg-slate-950/30 transition text-slate-700 dark:text-slate-300">
                        <td class="p-4 font-mono text-slate-500 dark:text-slate-400">#<?= $u['id_utilizador'] ?></td>

                        <td class="p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-indigo-600/20 border border-indigo-500/20 flex items-center justify-center text-slate-800 dark:text-indigo-400 font-bold text-xs shrink-0">
                                    <?= strtoupper(substr($u['nome'], 0, 1)) ?>
                                </div>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">
                                    <?= htmlspecialchars($u['nome']) ?>
                                    <?php if ((int)$u['id_utilizador'] === (int)$_SESSION['user_id']): ?>
                                        <span class="ml-1 text-[10px] text-indigo-400 font-mono">(tu)</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </td>

                        <td class="p-4 text-slate-600 dark:text-slate-400 text-xs font-mono"><?= htmlspecialchars($u['email']) ?></td>

                        <td class="p-4">
                            <?php
                            $pbadge = match(strtolower($u['perfil'])) {
                                'admin'     => 'bg-red-500/10 text-red-400 border-red-500/20',
                                'gestor'    => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
                                'comercial' => 'bg-green-500/10 text-green-400 border-green-500/20',
                                default     => 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20',
                            };
                            ?>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider border <?= $pbadge ?>">
                                <?= htmlspecialchars($u['perfil']) ?>
                            </span>
                        </td>

                        <td class="p-4 text-slate-500 dark:text-slate-400 text-xs font-mono">
                            <?= !empty($u['data_criacao']) ? date('d-m-Y', strtotime($u['data_criacao'])) : '—' ?>
                        </td>

                        <td class="p-4">
                            <?php if ($_SESSION['user_perfil'] === 'admin'): ?>
                            <div class="flex items-center justify-center gap-2">
                                <button
                                    onclick="abrirModalEditar(<?= htmlspecialchars(json_encode([
                                        'id'     => $u['id_utilizador'],
                                        'nome'   => $u['nome'],
                                        'email'  => $u['email'],
                                        'perfil' => $u['perfil'],
                                    ]), ENT_QUOTES) ?>)"
                                    class="p-1.5 rounded-lg text-slate-600 dark:text-slate-400 hover:text-indigo-400 hover:bg-indigo-500/10 transition"
                                    title="Editar">
                                    <i data-lucide="pencil" class="w-4 h-4"></i>
                                </button>

                                <?php if ((int)$u['id_utilizador'] !== (int)$_SESSION['user_id']): ?>
                                <button
                                    onclick="confirmarApagar(<?= (int)$u['id_utilizador'] ?>, '<?= htmlspecialchars(addslashes($u['nome'])) ?>')"
                                    class="p-1.5 rounded-lg text-slate-600 dark:text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition"
                                    title="Apagar">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                                <?php else: ?>
                                <span class="p-1.5 text-slate-700 cursor-not-allowed" title="Não podes apagar a tua própria conta">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="p-10 text-center">
                            <?php if ($f_pesquisa !== ''): ?>
                                <div class="flex flex-col items-center gap-2 text-slate-500 dark:text-slate-400">
                                    <i data-lucide="search-x" class="w-8 h-8 text-slate-700"></i>
                                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Nenhum resultado para <span class="text-indigo-400">"<?= htmlspecialchars($f_pesquisa) ?>"</span></p>
                                    <a href="index.php?v=users" class="text-xs text-rose-400 hover:underline mt-1">Limpar pesquisa</a>
                                </div>
                            <?php else: ?>
                                <p class="text-slate-500 dark:text-slate-400 italic text-sm">Sem utilizadores registados.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_registos > 0): ?>
    <div class="p-4 border-t border-slate-200 border-slate-300 dark:border-slate-800/80 bg-white dark:bg-slate-900/20 flex flex-col md:flex-row justify-between items-center gap-4">
        
        <div class="flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
            <span>Mostrar</span>
            <select onchange="mudarLimitePagina(this.value)" 
                    class="bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-lg px-2 py-1.5 text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 transition cursor-pointer">
                <?php foreach ($limites_permitidos as $l): ?>
                    <option value="<?= $l ?>" <?= $limite === $l ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <span>registos</span>
        </div>

        <div class="flex items-center gap-1.5 text-sm">
    <a href="<?= $pagina > 1 ? urlPagina(1, $query_params) : '#' ?>" 
       class="px-3 py-1.5 rounded-lg border transition flex items-center justify-center 
              <?= $pagina <= 1 ? 'border-slate-200 border-slate-300 dark:border-slate-800/50 text-slate-600 pointer-events-none' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-800 hover:text-slate-800 dark:text-white' ?>">
        Primeira
    </a>

    <?php 
    $start_page = max(1, $pagina - 2);
    $end_page   = min($total_paginas, $pagina + 2);

        for ($i = $start_page; $i <= $end_page; $i++): 
        ?>
            <?php if ($i === $pagina): ?>
                <span class="px-3 py-1.5 rounded-lg bg-indigo-600 border border-indigo-500 text-white font-bold pointer-events-none">
                    <?= $i ?>
                </span>
            <?php else: ?>
                <a href="<?= urlPagina($i, $query_params) ?>" 
                class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-800 hover:border-slate-400 dark:hover:border-slate-700 hover:text-slate-800 dark:hover:text-slate-200 transition">
                    <?= $i ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>

        <a href="<?= $pagina < $total_paginas ? urlPagina($total_paginas, $query_params) : '#' ?>" 
        class="px-3 py-1.5 rounded-lg border transition flex items-center justify-center 
                <?= $pagina >= $total_paginas ? 'border-slate-200 border-slate-300 dark:border-slate-800/50 text-slate-600 pointer-events-none' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-800 hover:text-slate-800 dark:text-white' ?>">
            Última
        </a>
    </div>
    </div>
    <?php endif; ?>

</div>


<div id="modal-criar"
     class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     onclick="fecharModalFora(event, 'modal-criar')">
    <div class="absolute inset-0 bg-black/75 backdrop-blur-sm"></div>

    <div class="relative bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-700/60 rounded-2xl w-full max-w-md shadow-2xl shadow-black/60 overflow-hidden"
         style="animation: modalIn .2s ease-out;">
        <div class="h-1 w-full bg-gradient-to-r from-indigo-600 via-indigo-400 to-cyan-400"></div>

        <div class="p-6 space-y-5">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i data-lucide="user-plus" class="w-4 h-4 text-indigo-400"></i>
                    Novo Utilizador
                </h3>
                <button onclick="fecharModal('modal-criar')"
                        class="text-slate-500 dark:text-slate-400 hover:text-slate-200 p-1.5 hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <form action="views/users.php" method="POST" class="space-y-4" onsubmit="return validarCriar(event)">
                <input type="hidden" name="action" value="criar">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <div class="space-y-1.5">
                    <label class="block text-xs text-slate-600 dark:text-slate-400 font-medium">Nome</label>
                    <input type="text" name="nome" id="criar-nome" required
                           placeholder="Nome completo"
                           class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition placeholder-slate-600">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs text-slate-600 dark:text-slate-400 font-medium">E-mail</label>
                    <input type="email" name="email" id="criar-email" required
                           placeholder="email@exemplo.com"
                           class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition placeholder-slate-600">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs text-slate-600 dark:text-slate-400 font-medium">Password</label>
                    <div class="relative">
                        <input type="password" name="password" id="criar-password" required minlength="4"
                               placeholder="Mínimo 4 caracteres"
                               class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2.5 pr-10 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition placeholder-slate-600">
                        <button type="button" onclick="togglePassVis('criar-password', this)"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-600 hover:text-slate-700 dark:text-slate-300 transition">
                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs text-slate-600 dark:text-slate-400 font-medium">Perfil</label>
                    <select name="perfil" id="criar-perfil"
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition">
                        <option value="comercial">Comercial</option>
                        <option value="gestor">Gestor</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <p id="erro-criar" class="hidden text-xs text-rose-400"></p>

                <div class="flex gap-2 pt-1">
                    <button type="button" onclick="fecharModal('modal-criar')"
                            class="flex-1 py-2.5 rounded-xl border border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400 text-xs font-medium hover:bg-slate-100 dark:hover:bg-slate-900 hover:text-slate-800 dark:hover:text-slate-200 transition">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition flex items-center justify-center gap-1.5">
                        <i data-lucide="user-plus" class="w-3.5 h-3.5"></i>
                        Criar Utilizador
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<div id="modal-editar"
     class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     onclick="fecharModalFora(event, 'modal-editar')">
    <div class="absolute inset-0 bg-black/75 backdrop-blur-sm"></div>

    <div class="relative bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-700/60 rounded-2xl w-full max-w-md shadow-2xl shadow-black/60 overflow-hidden"
         style="animation: modalIn .2s ease-out;">
        <div class="h-1 w-full bg-gradient-to-r from-violet-600 via-indigo-400 to-cyan-400"></div>

        <div class="p-6 space-y-5">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i data-lucide="pencil" class="w-4 h-4 text-indigo-400"></i>
                    Editar Utilizador
                </h3>
                <button onclick="fecharModal('modal-editar')"
                        class="text-slate-500 dark:text-slate-400 hover:text-slate-200 p-1.5 hover:bg-slate-800 rounded-lg transition">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <form action="views/users.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="editar">
                <input type="hidden" name="id" id="editar-id">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <div class="space-y-1.5">
                    <label class="block text-xs text-slate-600 dark:text-slate-400 font-medium">Nome</label>
                    <input type="text" name="nome" id="editar-nome" required
                           placeholder="Nome completo"
                           class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition placeholder-slate-600">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs text-slate-600 dark:text-slate-400 font-medium">E-mail</label>
                    <input type="email" name="email" id="editar-email" required
                           placeholder="email@exemplo.com"
                           class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition placeholder-slate-600">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs text-slate-600 dark:text-slate-400 font-medium">
                        Nova Password
                        <span class="text-slate-600 font-normal">(deixa em branco para manter a atual)</span>
                    </label>
                    <div class="relative">
                        <input type="password" name="password" id="editar-password"
                               placeholder="Nova password (opcional)"
                               class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2.5 pr-10 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition placeholder-slate-600">
                        <button type="button" onclick="togglePassVis('editar-password', this)"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-600 hover:text-slate-700 dark:text-slate-300 transition">
                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs text-slate-600 dark:text-slate-400 font-medium">Perfil</label>
                    <select name="perfil" id="editar-perfil"
                            class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30 transition">
                        <option value="comercial">Comercial</option>
                        <option value="gestor">Gestor</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="flex gap-2 pt-1">
                    <button type="button" onclick="fecharModal('modal-editar')"
                            class="flex-1 py-2.5 rounded-xl border border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400 text-xs font-medium hover:bg-slate-100 dark:hover:bg-slate-900 hover:text-slate-800 dark:hover:text-slate-200 transition">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition flex items-center justify-center gap-1.5">
                        <i data-lucide="save" class="w-3.5 h-3.5"></i>
                        Guardar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<div id="modal-apagar"
     class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     onclick="fecharModalFora(event, 'modal-apagar')">
    <div class="absolute inset-0 bg-black/75 backdrop-blur-sm"></div>

    <div class="relative bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-700/60 rounded-2xl w-full max-w-sm shadow-2xl shadow-black/60 overflow-hidden"
         style="animation: modalIn .2s ease-out;">
        <div class="h-1 w-full bg-gradient-to-r from-rose-600 to-rose-400"></div>

        <div class="p-6 space-y-5">
            <div class="flex items-start gap-4">
                <div class="bg-rose-500/10 border border-rose-500/20 p-2.5 rounded-xl shrink-0">
                    <i data-lucide="trash-2" class="w-5 h-5 text-rose-400"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">Apagar utilizador</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">
                        Tens a certeza que queres apagar <strong id="apagar-nome-display" class="text-slate-800 dark:text-slate-200"></strong>?
                        Esta ação não pode ser desfeita.
                    </p>
                </div>
            </div>

            <form action="views/users.php" method="POST">
                <input type="hidden" name="action" value="apagar">
                <input type="hidden" name="id" id="apagar-id">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                <div class="flex gap-2">
                    <button type="button" onclick="fecharModal('modal-apagar')"
                            class="flex-1 py-2.5 rounded-xl border border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400 text-xs font-medium hover:bg-slate-100 dark:hover:bg-slate-900 hover:text-slate-800 dark:hover:text-slate-200 transition">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="flex-1 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs transition flex items-center justify-center gap-1.5">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        Apagar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<style>
@keyframes modalIn {
    from { opacity: 0; transform: scale(0.96) translateY(8px); }
    to   { opacity: 1; transform: scale(1)    translateY(0);   }
}
</style>

<script>
// ── ABRIR / FECHAR ────────────────────────────────────────────────────────────

function abrirModalCriar() {
    // Limpar campos
    ['criar-nome','criar-email','criar-password'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('criar-perfil').value = 'comercial';
    document.getElementById('erro-criar').classList.add('hidden');
    abrirModal('modal-criar');
    setTimeout(() => document.getElementById('criar-nome')?.focus(), 60);
}

function abrirModalEditar(dados) {
    document.getElementById('editar-id').value      = dados.id;
    document.getElementById('editar-nome').value    = dados.nome;
    document.getElementById('editar-email').value   = dados.email;
    document.getElementById('editar-perfil').value  = dados.perfil.toLowerCase();
    document.getElementById('editar-password').value = '';
    abrirModal('modal-editar');
    setTimeout(() => document.getElementById('editar-nome')?.focus(), 60);
}

function confirmarApagar(id, nome) {
    document.getElementById('apagar-id').value = id;
    document.getElementById('apagar-nome-display').textContent = nome;
    abrirModal('modal-apagar');
}

function abrirModal(id) {
    document.getElementById(id).classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function fecharModal(id) {
    document.getElementById(id).classList.add('hidden');
}

// Rasteia onde o mousedown começou para evitar fechar o modal ao arrastar de dentro para fora
let _modalMousedownTarget = null;
document.addEventListener('mousedown', function(e) { _modalMousedownTarget = e.target; }, true);

function fecharModalFora(event, id) {
    const modal = document.getElementById(id);
    // Só fecha se o mousedown E o click ocorreram no backdrop
    if (event.target === modal && _modalMousedownTarget === modal) fecharModal(id);
}

// Fechar com Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        ['modal-criar','modal-editar','modal-apagar'].forEach(fecharModal);
    }
});

// ── MOSTRAR / OCULTAR PASSWORD ────────────────────────────────────────────────

function togglePassVis(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';
    btn.innerHTML = visible
        ? '<i data-lucide="eye"     class="w-3.5 h-3.5"></i>'
        : '<i data-lucide="eye-off" class="w-3.5 h-3.5"></i>';
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// ── VALIDAÇÃO CRIAR ───────────────────────────────────────────────────────────

function validarCriar(event) {
    const nome  = document.getElementById('criar-nome').value.trim();
    const email = document.getElementById('criar-email').value.trim();
    const pass  = document.getElementById('criar-password').value;
    const erro  = document.getElementById('erro-criar');

    if (nome.length < 2) {
        erro.textContent = 'O nome deve ter pelo menos 2 caracteres.';
        erro.classList.remove('hidden');
        event.preventDefault();
        return false;
    }
    if (!email.includes('@')) {
        erro.textContent = 'Introduz um e-mail válido.';
        erro.classList.remove('hidden');
        event.preventDefault();
        return false;
    }
    if (pass.length < 4) {
        erro.textContent = 'A password deve ter pelo menos 4 caracteres.';
        erro.classList.remove('hidden');
        event.preventDefault();
        return false;
    }

    erro.classList.add('hidden');
    return true;
}

// Retrocompatibilidade
function abrirEditar(dados) { abrirModalEditar(dados); }

// ── NAVEGAÇÃO E PAGINAÇÃO ───────────────────────────────────────────────────────
function mudarLimitePagina(novoLimite) {
    // Usar API do browser para alterar só o parâmetro limit e reiniciar p para 1
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('limit', novoLimite);
    urlParams.set('p', '1');
    window.location.search = urlParams.toString();
}
</script>