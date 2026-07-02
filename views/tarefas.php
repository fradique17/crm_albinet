<?php

// views/tarefas.php

date_default_timezone_set('Europe/Lisbon');

if (isset($conn) && !isset($pdo)) {
    $pdo = $conn;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Gerar token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$mensagem    = "";
$status_erro = false;
$data_atual  = date('Y-m-d H:i:s');
$id_utilizador_logado = $_SESSION['user_id'] ?? 0;
$perfil_utilizador    = $_SESSION['user_perfil'] ?? '';

// ── AÇÃO: Criar Tarefa ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_criar']) && isset($pdo)) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Ação não autorizada. Token de segurança inválido.");
    }

    $id_lead     = (int)($_POST['id_lead'] ?? 0);
    $titulo      = trim($_POST['titulo'] ?? '');
    $descricao   = trim($_POST['descricao'] ?? '');
    $data_limite = $_POST['data_limite'] ?? '';

    if ($id_lead === 0 || empty($titulo) || empty($data_limite) || $id_utilizador_logado === 0) {
        $mensagem    = "Erro: Selecione uma lead, defina um título e configure a data-limite.";
        $status_erro = true;
    } else {
        try {
            $data_formatada = date('Y-m-d H:i:s', strtotime($data_limite));
            $sql  = "INSERT INTO tarefas (id_lead, id_utilizador, titulo, descricao, data_limite, estado)
                     VALUES (:id_lead, :id_utilizador, :titulo, :descricao, :data_limite, 'Pendente')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_lead'       => $id_lead,
                ':id_utilizador' => $id_utilizador_logado,
                ':titulo'        => $titulo,
                ':descricao'     => $descricao,
                ':data_limite'   => $data_formatada,
            ]);
            $_SESSION['flash_msg'] = 'tarefa_criada';
            $_SESSION['flash_nome'] = $titulo;
            echo "<script>window.location.href = '?v=tarefas&sucesso=1';</script>";
            exit;
        } catch (PDOException $e) {
            $mensagem    = "Erro ao guardar tarefa: " . $e->getMessage();
            $status_erro = true;
        }
    }
}

// ── AÇÃO: Concluir Tarefa ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_concluir']) && isset($pdo)) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Ação não autorizada. Token de segurança inválido.");
    }

    $id_tarefa_concluir = (int)$_POST['id_tarefa'];
    try {
        if ($perfil_utilizador === 'comercial') {
            $stmt = $pdo->prepare("UPDATE tarefas SET estado = 'Concluída' WHERE id_tarefa = :id AND id_utilizador = :id_user");
            $stmt->execute([
                ':id'      => $id_tarefa_concluir,
                ':id_user' => $id_utilizador_logado
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE tarefas SET estado = 'Concluída' WHERE id_tarefa = :id");
            $stmt->execute([':id' => $id_tarefa_concluir]);
        }
        
        // Fetch the title for the notification
        $stmt_titulo = $pdo->prepare("SELECT titulo FROM tarefas WHERE id_tarefa = ?");
        $stmt_titulo->execute([$id_tarefa_concluir]);
        $titulo_concluida = $stmt_titulo->fetchColumn() ?: 'Tarefa';
        $_SESSION['flash_msg'] = 'tarefa_concluida';
        $_SESSION['flash_nome'] = $titulo_concluida;
        echo "<script>window.location.href = '?v=tarefas&sucesso=2';</script>";
        exit;
    } catch (PDOException $e) {
        $mensagem    = "Erro ao atualizar estado: " . $e->getMessage();
        $status_erro = true;
    }
}

// ── Mensagens de sucesso via GET (ou via sessão flash) ──────────────────────────────────────────────
$flash_nome_tarefa = '';
if (isset($_GET['sucesso'])) {
    $flash_nome_tarefa = $_SESSION['flash_nome'] ?? '';
    unset($_SESSION['flash_nome'], $_SESSION['flash_msg']);
    if ($_GET['sucesso'] == 1) {
        $mensagem = !empty($flash_nome_tarefa) ? "Tarefa \"" . $flash_nome_tarefa . "\" criada e agendada com sucesso!" : "Tarefa criada e agendada com sucesso!";
    } elseif ($_GET['sucesso'] == 2) {
        $mensagem = !empty($flash_nome_tarefa) ? "Tarefa \"" . $flash_nome_tarefa . "\" marcada como concluída!" : "Tarefa marcada como concluída!";
    }
}

// ── Carregar Leads disponíveis ────────────────────────────────────────────────
$leads_disponiveis = [];
if (isset($pdo)) {
    try {
        $leads_disponiveis = $pdo->query("SELECT id_lead, empresa FROM leads ORDER BY empresa ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao carregar leads: " . $e->getMessage());
    }
}
$f_pesquisa = trim($_GET['f_pesquisa'] ?? '');

// Limites Permitidos (Exatamente igual a users_manage.php)
$limites_permitidos = [10, 25, 50, 100];
$limite = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
if (!in_array($limite, $limites_permitidos)) {
    $limite = 10;
}

// Definição das Páginas Atuais de cada separador
$pagina_p = isset($_GET['p_p']) ? max(1, (int)$_GET['p_p']) : 1;
$pagina_c = isset($_GET['p_c']) ? max(1, (int)$_GET['p_c']) : 1;

$offset_p = ($pagina_p - 1) * $limite;
$offset_c = ($pagina_c - 1) * $limite;

$tarefas_pendentes = [];
$tarefas_concluidas = [];
$total_pendentes = 0;
$total_concluidas = 0;
$total_paginas_p = 0;
$total_paginas_c = 0;

if (isset($pdo)) {
    try {
        // Construção do Filtro SQL de Pesquisa
        $search_query = "";
        $params = [];
        if ($f_pesquisa !== '') {
            $search_query = " AND (t.titulo LIKE ? OR t.descricao LIKE ? OR l.empresa LIKE ?)";
            $params = ['%' . $f_pesquisa . '%', '%' . $f_pesquisa . '%', '%' . $f_pesquisa . '%'];
        }

        // 1. CONTAGEM E CARREGAMENTO DE PENDENTES
        $stmt_count_p = $pdo->prepare("SELECT COUNT(*) FROM tarefas t INNER JOIN leads l ON t.id_lead = l.id_lead WHERE t.estado = 'Pendente'" . $search_query);
        $stmt_count_p->execute($params);
        $total_pendentes = (int)$stmt_count_p->fetchColumn();

        $total_paginas_p = ceil($total_pendentes / $limite);
        if ($pagina_p > $total_paginas_p && $total_paginas_p > 0) $pagina_p = $total_paginas_p;
        $offset_p = ($pagina_p - 1) * $limite;

        $sql_p = "SELECT t.*, l.empresa FROM tarefas t 
                  INNER JOIN leads l ON t.id_lead = l.id_lead 
                  WHERE t.estado = 'Pendente'" . $search_query . " 
                  ORDER BY t.data_limite ASC LIMIT $limite OFFSET $offset_p";
        $stmt_p = $pdo->prepare($sql_p);
        $stmt_p->execute($params);
        $tarefas_pendentes = $stmt_p->fetchAll(PDO::FETCH_ASSOC);


        // 2. CONTAGEM E CARREGAMENTO DE CONCLUÍDAS
        $stmt_count_c = $pdo->prepare("SELECT COUNT(*) FROM tarefas t INNER JOIN leads l ON t.id_lead = l.id_lead WHERE t.estado = 'Concluída'" . $search_query);
        $stmt_count_c->execute($params);
        $total_concluidas = (int)$stmt_count_c->fetchColumn();

        $total_paginas_c = ceil($total_concluidas / $limite);
        if ($pagina_c > $total_paginas_c && $total_paginas_c > 0) $pagina_c = $total_paginas_c;
        $offset_c = ($pagina_c - 1) * $limite;

        $sql_c = "SELECT t.*, l.empresa FROM tarefas t 
                  INNER JOIN leads l ON t.id_lead = l.id_lead 
                  WHERE t.estado = 'Concluída'" . $search_query . " 
                  ORDER BY t.data_limite DESC LIMIT $limite OFFSET $offset_c";
        $stmt_c = $pdo->prepare($sql_c);
        $stmt_c->execute($params);
        $tarefas_concluidas = $stmt_c->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Erro ao carregar separação de tarefas: " . $e->getMessage());
    }
}

$total_tarefas = $total_pendentes + $total_concluidas;
// Para fins de calendário mantemos a junção das listagens atuais
$todas_tarefas = array_merge($tarefas_pendentes, $tarefas_concluidas);

// ── Construtor de URLs Seguros para os links da paginação (igual a users_manage.php) ──
$query_params_base = $_GET;
unset($query_params_base['sucesso']);
$query_params_base['limit'] = $limite;

function urlPaginaTarefas($p, $campoPagina, $params) {
    $params[$campoPagina] = $p;
    return '?' . http_build_query($params);
}
?>

<div id="crm-tarefas-app">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-black tracking-tight bg-gradient-to-r from-indigo-400 to-cyan-400 bg-clip-text text-transparent">
                Tarefas & Follow-ups
            </h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                Total de <span class="text-indigo-400 font-bold"><?= $total_tarefas ?></span> atividades em vista.
            </p>
        </div>

        <button onclick="abrirModal('modal-criar-tarefa')"
                class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm px-4 py-2.5 rounded-xl shadow-lg shadow-indigo-600/20 transition">
            <i data-lucide="plus" class="w-4 h-4"></i> Nova Tarefa
        </button>
    </div>

    <form method="GET" action="index.php" id="form-pesquisa-tarefas" class="mb-6">
        <input type="hidden" name="v" value="tarefas">
        <input type="hidden" name="limit" value="<?= $limite ?>">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none z-10">
                <i data-lucide="search" class="w-4 h-4 text-slate-500 dark:text-slate-400"></i>
            </div>
            <input type="text" name="f_pesquisa" value="<?= htmlspecialchars($f_pesquisa) ?>"
                   placeholder="Pesquisar por tarefa, descrição ou empresa..." autocomplete="off"
                   class="w-full bg-white dark:bg-slate-900/60 border <?= $f_pesquisa !== '' ? 'border-indigo-500/60' : 'border-slate-200 dark:border-slate-300 dark:border-slate-800/80' ?> rounded-2xl pl-11 pr-12 py-3 text-sm text-slate-800 dark:text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500 focus:bg-white dark:bg-slate-900 transition backdrop-blur-xl">
            <?php if ($f_pesquisa !== ''): ?>
                <a href="index.php?v=tarefas" class="absolute inset-y-0 right-4 flex items-center text-slate-500 dark:text-slate-400 hover:text-rose-400 transition z-10" title="Limpar pesquisa">
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
                A filtrar tarefas por <span class="text-indigo-400 font-semibold">"<?= htmlspecialchars($f_pesquisa) ?>"</span> · <a href="index.php?v=tarefas" class="text-rose-400 hover:underline">Limpar</a>
            </p>
        </div>
        <?php endif; ?>
    </form>

    <?php if (!empty($mensagem)): ?>
        <div id="mensagem" class="mb-6 flex items-center gap-2 text-sm px-4 py-3 rounded-xl border
            <?= $status_erro ? 'bg-rose-500/10 border-rose-500/20 text-rose-400' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' ?>">
            <i data-lucide="<?= $status_erro ? 'alert-triangle' : 'check-circle' ?>" class="w-4 h-4 shrink-0"></i>
            <span class="flex-1"><?= htmlspecialchars($mensagem) ?></span>
            <button onclick="this.parentElement.remove()" class="ml-2 <?= $status_erro ? 'text-rose-400 hover:text-rose-200' : 'text-emerald-400 hover:text-emerald-200' ?> transition"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
    <?php endif; ?>

    <div class="flex space-x-1 bg-slate-200/60 dark:bg-slate-800/50 p-1.5 rounded-2xl mb-6 overflow-x-auto border border-slate-300 dark:border-slate-700/50">
        <button onclick="mudarTab('calendario')" id="btn-tab-calendario" class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-sm transition-all whitespace-nowrap">
            <i data-lucide="calendar" class="w-4 h-4"></i> Calendário
        </button>
        <button onclick="mudarTab('pendentes')" id="btn-tab-pendentes" class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-sm transition-all whitespace-nowrap">
            <i data-lucide="clock" class="w-4 h-4"></i> Pendentes 
            <span class="bg-indigo-500/20 text-indigo-500 dark:text-indigo-400 text-[10px] font-bold px-1.5 py-0.5 rounded-md"><?= $total_pendentes ?></span>
        </button>
        <button onclick="mudarTab('concluidas')" id="btn-tab-concluidas" class="flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-sm transition-all whitespace-nowrap">
            <i data-lucide="check-circle-2" class="w-4 h-4"></i> Concluídas
        </button>
    </div>

    <div id="tab-container" class="relative">
        
        <div id="tab-calendario" class="tab-content hidden">
            <div class="mb-8 bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-300 dark:border-slate-800/80 rounded-2xl p-6 backdrop-blur-xl">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        Agenda Mensal
                    </h2>
                    <div class="flex items-center gap-4">
                        <button onclick="mudarMes(-1)" class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            <i data-lucide="chevron-left" class="w-5 h-5"></i>
                        </button>
                        <span id="mes-ano-display" class="font-bold text-slate-800 dark:text-slate-200 min-w-[120px] text-center capitalize"></span>
                        <button onclick="mudarMes(1)" class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            <i data-lucide="chevron-right" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
                
                <div class="grid grid-cols-7 gap-2 mb-2 text-center text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    <div>Dom</div><div>Seg</div><div>Ter</div><div>Qua</div><div>Qui</div><div>Sex</div><div>Sáb</div>
                </div>
                <div id="calendario-grelha" class="grid grid-cols-7 gap-2">
                    </div>
                <div class="mt-4 flex flex-wrap gap-4 text-xs text-slate-500 dark:text-slate-400 justify-end">
                    <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-400 shadow-[0_0_8px_rgba(248,113,113,0.6)]"></span> Atrasada</div>
                    <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-cyan-400 shadow-[0_0_8px_rgba(34,211,238,0.6)]"></span> Pendente</div>
                    <div class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.6)]"></span> Concluída</div>
                </div>
            </div>
        </div>

        <div id="tab-pendentes" class="tab-content hidden">
            <div class="bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-300 dark:border-slate-800/80 rounded-2xl overflow-hidden backdrop-blur-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-300 dark:border-slate-800/80 bg-slate-50 bg-slate-50 dark:bg-slate-950/40 text-slate-600 dark:text-slate-400 text-xs font-mono uppercase tracking-wider">
                                <th class="p-4 font-bold">#</th>
                                <th class="p-4 font-bold">Lead</th>
                                <th class="p-4 font-bold">Tarefa</th>
                                <th class="p-4 font-bold">Data-Limite</th>
                                <th class="p-4 font-bold">Estado</th>
                                <th class="p-4 font-bold text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50 text-sm">
                            <?php if ($total_pendentes > 0):
                                $num_visual = $offset_p + 1;
                                foreach ($tarefas_pendentes as $tf):
                                    $estado_final = 'Pendente';
                                    if ($tf['data_limite'] < $data_atual) {
                                        $estado_final = 'Atrasada';
                                    }
                                    $badge = ($estado_final === 'Atrasada') ? 'bg-red-500/10 text-red-400 border-red-500/20 animate-pulse' : 'bg-blue-500/10 text-blue-400 border-blue-500/20';
                            ?>
                                <tr class="hover:bg-slate-100 dark:hover:bg-slate-950/30 transition text-slate-700 dark:text-slate-300">
                                    <td class="p-4 font-mono text-slate-500 dark:text-slate-400">#<?= $num_visual++ ?></td>
                                    <td class="p-4">
                                        <p class="font-medium text-slate-800 dark:text-white whitespace-nowrap">
                                            <?= htmlspecialchars($tf['empresa'] ?: 'Ficha Individual') ?>
                                        </p>
                                    </td>
                                    <td class="p-4">
                                        <p class="font-medium text-slate-800 dark:text-white whitespace-nowrap">
                                            <?= htmlspecialchars($tf['titulo']) ?>
                                        </p>
                                        <?php if (!empty($tf['descricao'])): ?>
                                            <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5 italic max-w-xs truncate" title="<?= htmlspecialchars($tf['descricao']) ?>">
                                                "<?= htmlspecialchars($tf['descricao']) ?>"
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 font-mono text-xs text-slate-700 dark:text-slate-300 whitespace-nowrap">
                                        <?= date('d/m/Y H:i', strtotime($tf['data_limite'])) ?>
                                    </td>
                                    <td class="p-4 whitespace-nowrap">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider border <?= $badge ?>">
                                            <?= $estado_final ?>
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick='abrirVerTarefa(<?= htmlspecialchars(json_encode([
                                                    'id_tarefa'   => $tf['id_tarefa'],
                                                    'id_lead'     => $tf['id_lead'],
                                                    'id_utilizador'=> $tf['id_utilizador'],
                                                    'titulo'      => $tf['titulo'],
                                                    'descricao'   => $tf['descricao'],
                                                    'empresa'     => $tf['empresa'],
                                                    'data_limite' => date('d/m/Y H:i', strtotime($tf['data_limite'])),
                                                    'estado'      => $estado_final,
                                                ]), ENT_QUOTES, "UTF-8") ?>)'
                                                class="p-1.5 rounded-lg text-slate-600 dark:text-slate-400 hover:text-cyan-400 hover:bg-cyan-500/10 transition">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </button>

                                        <?php if ($perfil_utilizador !== 'comercial' || $tf['id_utilizador'] == $id_utilizador_logado): ?>
                                        <form action="?v=tarefas" method="POST" class="inline m-0 p-0">
                                            <input type="hidden" name="acao_concluir" value="1">
                                            <input type="hidden" name="id_tarefa" value="<?= $tf['id_tarefa'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <button type="submit" class="p-1.5 rounded-lg text-slate-600 dark:text-slate-400 hover:text-emerald-400 hover:bg-emerald-500/10 transition">
                                                <i data-lucide="check-circle" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-500 dark:text-slate-400 italic">
                                        <div class="flex flex-col items-center gap-2 justify-center py-4">
                                            <i data-lucide="calendar-off" class="w-8 h-8 text-slate-600"></i>
                                            <span>Nenhuma tarefa pendente na agenda.</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pendentes > 0): ?>
                <?php $params_p = $query_params_base; $params_p['p_c'] = $pagina_c; ?>
                <div class="p-4 border-t border-slate-200 dark:border-slate-300 dark:border-slate-800/80 bg-white dark:bg-slate-900/20 flex flex-col md:flex-row justify-between items-center gap-4">

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
                        <a href="<?= $pagina_p > 1 ? urlPaginaTarefas(1, 'p_p', $params_p) : '#' ?>"
                           class="px-3 py-1.5 rounded-lg border transition flex items-center justify-center
                                  <?= $pagina_p <= 1 ? 'border-slate-200 dark:border-slate-300 dark:border-slate-800/50 text-slate-600 pointer-events-none' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-800 hover:text-slate-800 dark:text-white' ?>">
                            Primeira
                        </a>

                        <?php
                        $start_page_p = max(1, $pagina_p - 2);
                        $end_page_p   = min($total_paginas_p, $pagina_p + 2);
                        for ($i = $start_page_p; $i <= $end_page_p; $i++):
                        ?>
                            <?php if ($i === $pagina_p): ?>
                                <span class="px-3 py-1.5 rounded-lg bg-indigo-600 border border-indigo-500 text-white font-bold pointer-events-none">
                                    <?= $i ?>
                                </span>
                            <?php else: ?>
                                <a href="<?= urlPaginaTarefas($i, 'p_p', $params_p) ?>"
                                   class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-800 hover:border-slate-400 dark:hover:border-slate-700 hover:text-slate-800 dark:hover:text-slate-200 transition">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <a href="<?= $pagina_p < $total_paginas_p ? urlPaginaTarefas($total_paginas_p, 'p_p', $params_p) : '#' ?>"
                           class="px-3 py-1.5 rounded-lg border transition flex items-center justify-center
                                  <?= $pagina_p >= $total_paginas_p ? 'border-slate-200 dark:border-slate-300 dark:border-slate-800/50 text-slate-600 pointer-events-none' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-800 hover:text-slate-800 dark:text-white' ?>">
                            Última
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>


   <div id="tab-concluidas" class="tab-content hidden">
            <div class="bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-300 dark:border-slate-800/80 rounded-2xl overflow-hidden backdrop-blur-xl">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-300 dark:border-slate-800/80 bg-slate-50 dark:bg-slate-950/40 text-slate-600 dark:text-slate-400 text-xs font-mono uppercase tracking-wider">
                                <th class="p-4 font-bold">#</th>
                                <th class="p-4 font-bold">Lead</th>
                                <th class="p-4 font-bold">Tarefa</th>
                                <th class="p-4 font-bold">Data-Limite</th>
                                <th class="p-4 font-bold">Estado</th>
                                <th class="p-4 font-bold text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800/50 text-sm">
                            <?php if ($total_concluidas > 0):
                                $num_visual = $offset_c + 1;
                                foreach ($tarefas_concluidas as $tc): ?>
                                <tr class="hover:bg-slate-100 dark:hover:bg-slate-950/30 transition text-slate-600 dark:text-slate-400 opacity-80 hover:opacity-100">
                                    <td class="p-4 font-mono text-slate-500 dark:text-slate-400">#<?= $num_visual++ ?></td>
                                    <td class="p-4">
                                        <p class="font-medium text-slate-800 dark:text-white whitespace-nowrap">
                                            <?= htmlspecialchars($tc['empresa'] ?: 'Ficha Individual') ?>
                                        </p>
                                    </td>
                                    <td class="p-4">
                                        <p class="font-medium text-slate-800 dark:text-white whitespace-nowrap">
                                            <?= htmlspecialchars($tc['titulo']) ?>
                                        </p>
                                    </td>
                                    <td class="p-4 font-mono text-xs text-slate-800 dark:text-slate-300 whitespace-nowrap">
                                        <?= date('d/m/Y H:i', strtotime($tc['data_limite'])) ?>
                                    </td>
                                    <td class="p-4 whitespace-nowrap">
                                        <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wider border bg-emerald-500/10 text-emerald-400 border-emerald-500/20">
                                            Concluída
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <button onclick='abrirVerTarefa(<?= htmlspecialchars(json_encode([
                                                    'id_tarefa'   => $tc['id_tarefa'],
                                                    'id_lead'     => $tc['id_lead'],
                                                    'id_utilizador'=> $tc['id_utilizador'],
                                                    'titulo'      => $tc['titulo'],
                                                    'descricao'   => $tc['descricao'],
                                                    'empresa'     => $tc['empresa'],
                                                    'data_limite' => date('d/m/Y H:i', strtotime($tc['data_limite'])),
                                                    'estado'      => 'Concluída',
                                                ]), ENT_QUOTES, "UTF-8") ?>)' class="p-1.5 rounded-lg text-slate-600 dark:text-slate-400 hover:text-cyan-400 hover:bg-cyan-500/10 transition" title="Ver Detalhes">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-500 dark:text-slate-400 italic">
                                        <div class="flex flex-col items-center gap-2 justify-center py-4">
                                            <i data-lucide="calendar-off" class="w-8 h-8 text-slate-600"></i>
                                            <span>Nenhuma tarefa concluída encontrada.</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_concluidas > 0): ?>
                <?php $params_c = $query_params_base; $params_c['p_p'] = $pagina_p; ?>
                <div class="p-4 border-t border-slate-200 dark:border-slate-300 dark:border-slate-800/80 bg-white dark:bg-slate-900/20 flex flex-col md:flex-row justify-between items-center gap-4">

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
                        <a href="<?= $pagina_c > 1 ? urlPaginaTarefas(1, 'p_c', $params_c) : '#' ?>"
                           class="px-3 py-1.5 rounded-lg border transition flex items-center justify-center
                                  <?= $pagina_c <= 1 ? 'border-slate-200 dark:border-slate-300 dark:border-slate-800/50 text-slate-600 pointer-events-none' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-800 hover:text-slate-800 dark:text-white' ?>">
                            Primeira
                        </a>

                        <?php
                        $start_page_c = max(1, $pagina_c - 2);
                        $end_page_c   = min($total_paginas_c, $pagina_c + 2);
                        for ($i = $start_page_c; $i <= $end_page_c; $i++):
                        ?>
                            <?php if ($i === $pagina_c): ?>
                                <span class="px-3 py-1.5 rounded-lg bg-indigo-600 border border-indigo-500 text-white font-bold pointer-events-none">
                                    <?= $i ?>
                                </span>
                            <?php else: ?>
                                <a href="<?= urlPaginaTarefas($i, 'p_c', $params_c) ?>"
                                   class="px-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-800 hover:border-slate-400 dark:hover:border-slate-700 hover:text-slate-800 dark:hover:text-slate-200 transition">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <a href="<?= $pagina_c < $total_paginas_c ? urlPaginaTarefas($total_paginas_c, 'p_c', $params_c) : '#' ?>"
                           class="px-3 py-1.5 rounded-lg border transition flex items-center justify-center
                                  <?= $pagina_c >= $total_paginas_c ? 'border-slate-200 dark:border-slate-300 dark:border-slate-800/50 text-slate-600 pointer-events-none' : 'border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-800 hover:text-slate-800 dark:text-white' ?>">
                            Última
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <div id="modal-criar-tarefa" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" onclick="fecharModalFora(event, 'modal-criar-tarefa')">
        <div class="absolute inset-0 bg-black/75 backdrop-blur-sm"></div>
        <div class="relative bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-700/60 rounded-2xl w-full max-w-lg shadow-2xl shadow-black/60 overflow-hidden my-auto modal-anim">
            <div class="h-1 w-full bg-gradient-to-r from-indigo-600 via-indigo-400 to-cyan-400"></div>
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i data-lucide="bell-plus" class="w-4 h-4 text-indigo-400"></i> Nova Tarefa
                    </h3>
                    <button onclick="fecharModal('modal-criar-tarefa')" class="text-slate-500 dark:text-slate-400 hover:text-slate-200 transition p-1 rounded-lg hover:bg-slate-800"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
                <form action="" method="POST" class="space-y-3">
                    <input type="hidden" name="acao_criar" value="1">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Associar à Lead <span class="text-rose-400">*</span></label>
                        <select name="id_lead" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                            <option value="">— Escolher uma Lead —</option>
                            <?php foreach ($leads_disponiveis as $ld): ?>
                                <option value="<?= $ld['id_lead'] ?>"><?= htmlspecialchars($ld['empresa'] ?: 'Particular') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Ação / Título da Tarefa <span class="text-rose-400">*</span></label>
                        <input type="text" name="titulo" required placeholder="Ex: Telefonar para fechar proposta" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Data & Hora Limite <span class="text-rose-400">*</span></label>
                        <input type="datetime-local" name="data_limite" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Observações / Notas</label>
                        <textarea name="descricao" rows="3" placeholder="Ex: Detalhar abordagem..." class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500 transition resize-none"></textarea>
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button type="button" onclick="fecharModal('modal-criar-tarefa')" class="flex-1 py-2.5 rounded-xl border border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400 text-xs font-medium hover:bg-slate-200 dark:hover:bg-slate-900 hover:text-slate-800 transition">Cancelar</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition flex items-center justify-center gap-1.5"><i data-lucide="bell" class="w-3.5 h-3.5"></i> Guardar na Agenda</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="modal-lista-tarefas-dia" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto" onclick="fecharModalFora(event, 'modal-lista-tarefas-dia')">
        <div class="absolute inset-0 bg-black/75 backdrop-blur-sm"></div>
        <div class="relative bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-700/60 rounded-2xl w-full max-w-lg shadow-2xl shadow-black/60 overflow-hidden my-auto modal-anim">
            <div class="h-1 w-full bg-gradient-to-r from-indigo-600 via-indigo-400 to-cyan-400"></div>
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i data-lucide="calendar-days" class="w-4 h-4 text-indigo-400"></i>
                        Tarefas de <span id="lista-dia-titulo" class="text-indigo-400"></span>
                    </h3>
                    <button onclick="fecharModal('modal-lista-tarefas-dia')" class="text-slate-500 dark:text-slate-400 hover:text-slate-200 transition p-1 rounded-lg hover:bg-slate-800"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
                <div id="lista-dia-conteudo" class="space-y-3 max-h-[60vh] overflow-y-auto pr-1">
                    </div>
            </div>
        </div>
    </div>

    <div id="modal-ver-tarefa" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4 overflow-y-auto" onclick="fecharModalFora(event, 'modal-ver-tarefa')">
        <div class="absolute inset-0 bg-black/75 backdrop-blur-sm"></div>
        <div class="relative bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-700/60 rounded-2xl w-full max-w-md shadow-2xl shadow-black/60 overflow-hidden my-auto modal-anim">
            <div class="h-1 w-full bg-gradient-to-r from-indigo-600 via-indigo-400 to-cyan-400"></div>
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i data-lucide="calendar-check" class="w-4 h-4 text-indigo-400"></i> Detalhe da Tarefa
                    </h3>
                    <button onclick="fecharModal('modal-ver-tarefa')" class="text-slate-500 dark:text-slate-400 hover:text-slate-200 transition p-1 rounded-lg hover:bg-slate-800"><i data-lucide="x" class="w-4 h-4"></i></button>
                </div>
                <div class="grid grid-cols-1 gap-3">
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3.5 border border-slate-300 dark:border-slate-800">
                        <p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Título</p>
                        <p id="view-tarefa-titulo" class="text-slate-800 dark:text-slate-200 text-sm font-medium"></p>
                    </div>
                    
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3.5 border border-slate-300 dark:border-slate-800">
                        <p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Lead Associada</p>
                        <div class="flex justify-between items-center gap-2">
                            <p id="view-tarefa-empresa" class="text-slate-800 dark:text-slate-200 text-sm truncate"></p>
                            <a id="link-abrir-lead" href="#" class="shrink-0 flex items-center gap-1.5 px-2.5 py-1 bg-indigo-500/10 border border-indigo-500/20 text-indigo-500 hover:bg-indigo-500/20 hover:text-indigo-400 rounded-lg text-[11px] font-bold uppercase tracking-wide transition hidden">
                                Ver Ficha <i data-lucide="external-link" class="w-3 h-3"></i>
                            </a>
                        </div>
                    </div>

                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3.5 border border-slate-300 dark:border-slate-800">
                        <p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Data-Limite</p>
                        <p id="view-tarefa-data" class="text-slate-800 dark:text-slate-200 text-sm font-mono"></p>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3.5 border border-slate-300 dark:border-slate-800">
                        <p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Estado</p>
                        <p id="view-tarefa-estado" class="text-slate-800 dark:text-slate-200 text-sm"></p>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3.5 border border-slate-300 dark:border-slate-800">
                        <p class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Observações</p>
                        <p id="view-tarefa-descricao" class="text-slate-700 dark:text-slate-300 whitespace-pre-line text-sm"></p>
                    </div>
                </div>

                <div class="pt-2 flex gap-2">
                    <button onclick="fecharModal('modal-ver-tarefa')" class="flex-1 py-2.5 rounded-xl bg-white dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-800 font-bold text-xs transition">
                        Fechar
                    </button>
                    
                    <form id="form-concluir-modal" action="?v=tarefas" method="POST" class="flex-1 m-0 p-0 hidden">
                        <input type="hidden" name="acao_concluir" value="1">
                        <input type="hidden" name="id_tarefa" id="input-concluir-id" value="">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <button type="submit" class="w-full h-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition flex items-center justify-center gap-1.5">
                            <i data-lucide="check-circle" class="w-4 h-4"></i> Concluir Tarefa
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.modal-anim { animation: modalIn .2s ease-out; }
@keyframes modalIn {
    from { opacity: 0; transform: scale(0.96) translateY(8px); }
    to   { opacity: 1; transform: scale(1)    translateY(0); }
}
@keyframes tabFadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to   { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
    animation: tabFadeIn 0.25s ease-out forwards;
}

#lista-dia-conteudo::-webkit-scrollbar { width: 6px; }
#lista-dia-conteudo::-webkit-scrollbar-track { background: transparent; }
#lista-dia-conteudo::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
</style>

<script>
// ── GESTÃO DE SEPARADORES (TABS) ──────────────────────────────────────────────
function mudarTab(tabId) {
    // 1. Esconder todas as tabs e retirar a animação
    document.querySelectorAll('.tab-content').forEach(el => {
        el.classList.add('hidden');
        el.classList.remove('block', 'animate-fade-in');
    });

    // 2. Resetar o estilo visual de todos os botões para o estado inativo
    document.querySelectorAll('[id^="btn-tab-"]').forEach(btn => {
        btn.className = 'flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-sm font-medium transition-all text-slate-600 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 whitespace-nowrap';
    });

    // 3. Mostrar a tab selecionada com animação
    const activeTab = document.getElementById('tab-' + tabId);
    if (activeTab) {
        activeTab.classList.remove('hidden');
        activeTab.classList.add('block', 'animate-fade-in');
    }

    // 4. Aplicar o estilo "ativo" ao botão clicado (Destacado + Fundo compatível com o Dark Mode)
    const activeBtn = document.getElementById('btn-tab-' + tabId);
    if (activeBtn) {
        activeBtn.className = 'flex-1 flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl text-sm font-bold transition-all bg-white dark:bg-[#0b0f19] shadow text-indigo-600 dark:text-indigo-400 whitespace-nowrap';
    }

    // 5. Guardar a escolha do utilizador na memória do browser
    localStorage.setItem('tarefas_active_tab', tabId);
}

// ── NAVEGAÇÃO E PAGINAÇÃO (igual a users_manage.php) ──────────────────────────
function mudarLimitePagina(novoLimite) {
    // Usar API do browser para alterar só o parâmetro limit e reiniciar ambas as páginas para 1
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('limit', novoLimite);
    urlParams.set('p_p', '1');
    urlParams.set('p_c', '1');
    window.location.search = urlParams.toString();
}

// ── DADOS DO PHP PARA O JS ────────────────────────────────────────────────────
const todasTarefas = <?= json_encode($todas_tarefas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const idUserAtual = <?= (int)$id_utilizador_logado ?>;
const perfilUserAtual = '<?= htmlspecialchars($perfil_utilizador) ?>';

// ── CALENDÁRIO LÓGICA ─────────────────────────────────────────────────────────
let dataCalendario = new Date(); 

function renderCalendario() {
    const grelha = document.getElementById('calendario-grelha');
    const displayMesAno = document.getElementById('mes-ano-display');
    
    grelha.innerHTML = '';
    
    const mes = dataCalendario.getMonth();
    const ano = dataCalendario.getFullYear();
    const mesesPt = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    
    displayMesAno.innerText = `${mesesPt[mes]} ${ano}`;

    const primeiroDia = new Date(ano, mes, 1).getDay();
    const totalDias = new Date(ano, mes + 1, 0).getDate();
    
    const dataHoje = new Date();
    const isMesAtual = (dataHoje.getMonth() === mes && dataHoje.getFullYear() === ano);

    for (let i = 0; i < primeiroDia; i++) {
        const divBranco = document.createElement('div');
        divBranco.className = 'p-2 rounded-xl opacity-30';
        grelha.appendChild(divBranco);
    }

    for (let dia = 1; dia <= totalDias; dia++) {
        const dataStr = `${ano}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        
        const tarefasDoDia = todasTarefas.filter(t => t.data_limite.startsWith(dataStr));
        
        let totalAtrasadas = 0;
        let totalPendentes = 0;
        let totalConcluidas = 0;

        tarefasDoDia.forEach(t => {
            if (t.estado === 'Concluída') {
                totalConcluidas++;
            } else {
                const limitObj = new Date(t.data_limite);
                if (limitObj < dataHoje) {
                    totalAtrasadas++;
                } else {
                    totalPendentes++;
                }
            }
        });

        const isHoje = isMesAtual && dia === dataHoje.getDate();
        const divDia = document.createElement('div');
        
        let classesDia = 'relative p-2 h-14 md:h-16 flex flex-col items-center justify-start rounded-xl border border-slate-100 dark:border-slate-800/50 bg-slate-50/50 dark:bg-slate-900/30 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:border-indigo-200 dark:hover:border-indigo-800 transition cursor-pointer group';
        if (isHoje) {
            classesDia = 'relative p-2 h-14 md:h-16 flex flex-col items-center justify-start rounded-xl border border-indigo-400 bg-indigo-50 dark:bg-indigo-900/30 shadow-[0_0_12px_rgba(99,102,241,0.2)] cursor-pointer group';
        }

        divDia.className = classesDia;
        divDia.onclick = () => abrirTarefasDia(dataStr, `${dia} de ${mesesPt[mes]}`);

        let htmlDots = `<div class="mt-auto flex flex-wrap gap-1 pt-1 justify-center max-w-full opacity-80 group-hover:opacity-100 transition">`;
        
        for (let a = 0; a < totalAtrasadas; a++) {
            htmlDots += `<span class="w-2 h-2 rounded-full bg-red-400 shadow-[0_0_8px_rgba(248,113,113,0.8)] shrink-0" title="Atrasada"></span>`;
        }
        
        for (let p = 0; p < totalPendentes; p++) {
            htmlDots += `<span class="w-2 h-2 rounded-full bg-cyan-400 shadow-[0_0_8px_rgba(34,211,238,0.8)] shrink-0" title="Pendente"></span>`;
        }
        
        for (let c = 0; c < totalConcluidas; c++) {
            htmlDots += `<span class="w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)] shrink-0" title="Concluída"></span>`;
        }
        
        htmlDots += `</div>`;

        const temQualquerTarefa = tarefasDoDia.length > 0;

        divDia.innerHTML = `
            <span class="text-sm ${isHoje ? 'font-bold text-indigo-500 dark:text-indigo-400' : 'font-medium text-slate-700 dark:text-slate-300'}">${dia}</span>
            ${temQualquerTarefa ? htmlDots : ''}
        `;
        
        grelha.appendChild(divDia);
    }
}

function mudarMes(direcao) {
    dataCalendario.setMonth(dataCalendario.getMonth() + direcao);
    renderCalendario();
}

function abrirTarefasDia(dataStr, tituloDisplay) {
    const tarefasDoDia = todasTarefas.filter(t => t.data_limite.startsWith(dataStr));
    if (tarefasDoDia.length === 0) return;

    document.getElementById('lista-dia-titulo').innerText = tituloDisplay;
    const container = document.getElementById('lista-dia-conteudo');
    container.innerHTML = '';

    tarefasDoDia.forEach(tf => {
        const isPendente = tf.estado === 'Pendente';
        const hojeObj = new Date();
        const dataTaskObj = new Date(tf.data_limite);
        
        let estadoFinal = tf.estado;
        if (isPendente && dataTaskObj < hojeObj) estadoFinal = 'Atrasada';

        const borderClass = estadoFinal === 'Concluída' 
            ? 'border-emerald-500/30 hover:border-emerald-500' 
            : (estadoFinal === 'Atrasada' ? 'border-red-500/30 hover:border-red-500' : 'border-cyan-500/30 hover:border-cyan-500');

        const hora = tf.data_limite.split(' ')[1].substring(0, 5);

        const card = document.createElement('div');
        card.className = `p-3 rounded-xl border bg-slate-50 dark:bg-slate-900/50 cursor-pointer transition ${borderClass}`;
        
        const strJson = JSON.stringify({
            id_tarefa: tf.id_tarefa,
            id_utilizador: tf.id_utilizador,
            id_lead: tf.id_lead,
            titulo: tf.titulo,
            descricao: tf.descricao,
            empresa: tf.empresa,
            data_limite: `${tf.data_limite.split(' ')[0].split('-').reverse().join('/')} ${hora}`,
            estado: estadoFinal
        }).replace(/"/g, '&quot;');

        card.innerHTML = `
            <div class="flex justify-between items-start gap-2 mb-1">
                <span class="font-bold text-slate-800 dark:text-slate-200 text-sm truncate">${tf.titulo}</span>
                <span class="text-xs font-mono font-bold text-slate-500 bg-slate-200/50 dark:bg-slate-800 px-1.5 py-0.5 rounded">${hora}</span>
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-400 mb-2">${tf.empresa || 'Ficha Individual'}</p>
            <div class="text-[10px] font-bold uppercase px-2 py-0.5 rounded inline-block ${estadoFinal === 'Concluída' ? 'bg-emerald-500/10 text-emerald-400' : (estadoFinal === 'Atrasada' ? 'bg-red-500/10 text-red-400' : 'bg-cyan-500/10 text-cyan-400')}">
                ${estadoFinal}
            </div>
        `;
        card.onclick = () => {
            fecharModal('modal-lista-tarefas-dia');
            setTimeout(() => abrirVerTarefa(JSON.parse(strJson.replace(/&quot;/g, '"'))), 100);
        };
        container.appendChild(card);
    });

    abrirModal('modal-lista-tarefas-dia');
}

// ── ABRIR / FECHAR MODAIS E VER TAREFA ────────────────────────────────────────

function fecharModal(id) { document.getElementById(id).classList.add('hidden'); }

// Rasteia onde o mousedown começou para evitar fechar o modal ao arrastar de dentro para fora
let _modalMousedownTarget = null;
document.addEventListener('mousedown', function(e) { _modalMousedownTarget = e.target; }, true);

function fecharModalFora(event, id) {
    const modal = document.getElementById(id);
    // Só fecha se o mousedown E o click ocorreram no backdrop (o próprio elemento do modal)
    if (event.target === modal && _modalMousedownTarget === modal) fecharModal(id);
}

function abrirModal(id) {
    document.getElementById(id).classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();

    if (id === 'modal-criar-tarefa') {
        const inputData = document.querySelector('input[name="data_limite"]');
        if (inputData) {
            const agora = new Date();
            const tzo = agora.getTimezoneOffset() * 60000;
            inputData.value = (new Date(agora - tzo)).toISOString().slice(0, 16);
        }
    }
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        ['modal-criar-tarefa', 'modal-ver-tarefa', 'modal-lista-tarefas-dia'].forEach(fecharModal);
    }
});

function abrirVerTarefa(tarefa) {
    document.getElementById('view-tarefa-titulo').innerText   = tarefa.titulo    || '—';
    document.getElementById('view-tarefa-empresa').innerText  = tarefa.empresa   || '—';
    document.getElementById('view-tarefa-data').innerText     = tarefa.data_limite || '—';
    document.getElementById('view-tarefa-estado').innerText   = tarefa.estado    || '—';
    document.getElementById('view-tarefa-descricao').innerText = tarefa.descricao || 'Sem observações.';
    
    const linkLead = document.getElementById('link-abrir-lead');
    if (tarefa.id_lead) {
        linkLead.href = '?v=leads&abrir_lead_id=' + tarefa.id_lead;
        linkLead.classList.remove('hidden');
    } else {
        linkLead.classList.add('hidden');
    }
    
    const formConcluir = document.getElementById('form-concluir-modal');
    const inputConcluirId = document.getElementById('input-concluir-id');
    
    let podeConcluir = false;
    if (tarefa.estado === 'Pendente' || tarefa.estado === 'Atrasada') {
        if (perfilUserAtual !== 'comercial' || tarefa.id_utilizador == idUserAtual) {
            podeConcluir = true;
        }
    }

    if (podeConcluir) {
        formConcluir.classList.remove('hidden');
        inputConcluirId.value = tarefa.id_tarefa;
    } else {
        formConcluir.classList.add('hidden');
    }

    abrirModal('modal-ver-tarefa');
}

// ── LIMPEZA DE AVISOS E INICIALIZAÇÃO ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    renderCalendario();

    // Ler qual era a Tab ativa, por defeito abre a aba do calendário
    const tabGuardada = localStorage.getItem('tarefas_active_tab') || 'calendario';
    mudarTab(tabGuardada);

    const alerta = document.getElementById('mensagem');
    if (window.history.replaceState) {
        const url = new URL(window.location.href);
        if (url.searchParams.has('sucesso')) {
            url.searchParams.delete('sucesso');
            window.history.replaceState(null, '', url.href);
        }
    }

    // Notificação com X é removida apenas via clique, sem temporizador auto-dismiss

    
    agendarAtualizacaoExata();
});

// ── ATUALIZAR PÁGINA AO VIRAR DO MINUTO ──────────────────────────────────────
function agendarAtualizacaoExata() {
    const agora = new Date();
    let tempoAteProximoMinuto = (60 - agora.getSeconds()) * 1000 - agora.getMilliseconds() + 1000;
    
    setTimeout(() => {
        const criarEscondido = document.getElementById('modal-criar-tarefa')?.classList.contains('hidden');
        const verEscondido = document.getElementById('modal-ver-tarefa')?.classList.contains('hidden');
        const diaEscondido = document.getElementById('modal-lista-tarefas-dia')?.classList.contains('hidden');

        if (criarEscondido && verEscondido && diaEscondido) {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const novoDoc = new DOMParser().parseFromString(html, 'text/html');
                    const novoConteudo = novoDoc.getElementById('crm-tarefas-app');
                    const containerAtual = document.getElementById('crm-tarefas-app');
                    
                    if (novoConteudo && containerAtual) {
                        containerAtual.innerHTML = novoConteudo.innerHTML;
                        renderCalendario();
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                        
                        // Re-aplicar a tab correta após a injeção do HTML
                        const tabAtiva = localStorage.getItem('tarefas_active_tab') || 'calendario';
                        mudarTab(tabAtiva);
                    }
                    agendarAtualizacaoExata();
                }).catch(() => agendarAtualizacaoExata());
        } else {
            agendarAtualizacaoExata();
        }
    }, tempoAteProximoMinuto);
}
</script>

<?php
// ── ABRIR AUTOMATICAMENTE O MODAL DE DETALHE DE UMA TAREFA VIA URL ────────────
if (isset($_GET['abrir_tarefa_id']) && isset($pdo)):
    $id_tarefa_alvo = (int)$_GET['abrir_tarefa_id'];
    try {
        $stmt_tarefa_alvo = $pdo->prepare(
            "SELECT t.*, l.empresa, l.nome_contacto
             FROM tarefas t
             INNER JOIN leads l ON t.id_lead = l.id_lead
             WHERE t.id_tarefa = ?"
        );
        $stmt_tarefa_alvo->execute([$id_tarefa_alvo]);
        $tarefa_alvo = $stmt_tarefa_alvo->fetch(PDO::FETCH_ASSOC);

        if ($tarefa_alvo):
            $estado_alvo_final = $tarefa_alvo['estado'];
            if ($estado_alvo_final === 'Pendente' && $tarefa_alvo['data_limite'] < $data_atual) {
                $estado_alvo_final = 'Atrasada';
            }
            $tarefa_alvo_js = [
                'id_tarefa'     => $tarefa_alvo['id_tarefa'],
                'id_lead'       => $tarefa_alvo['id_lead'],
                'id_utilizador' => $tarefa_alvo['id_utilizador'],
                'titulo'        => $tarefa_alvo['titulo'],
                'descricao'     => $tarefa_alvo['descricao'],
                'empresa'       => $tarefa_alvo['empresa'],
                'data_limite'   => date('d/m/Y H:i', strtotime($tarefa_alvo['data_limite'])),
                'estado'        => $estado_alvo_final,
            ];
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            const tarefaData = <?= json_encode($tarefa_alvo_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            if (typeof abrirVerTarefa === 'function') {
                abrirVerTarefa(tarefaData);
                if (window.history && window.history.replaceState) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('abrir_tarefa_id');
                    window.history.replaceState({ path: url.href }, '', url.href);
                }
            }
        }, 400);
    });
</script>
<?php
        endif;
    } catch (Exception $e) {}
endif;
?>