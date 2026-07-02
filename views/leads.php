<?php
// Gerar token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$filtros_ativos = array_filter([
    $f_nome ?? '', $f_empresa ?? '', $f_estado ?? '', $f_prioridade ?? '',
    $f_origem ?? '', $f_servico ?? '', $f_responsavel ?? '',
    $f_data_de ?? '', $f_data_ate ?? ''
]);
$tem_filtros = count($filtros_ativos) > 0;

// Vai buscar a mensagem à sessão (se existir) e apaga-a imediatamente
$msg = $_SESSION['flash_msg'] ?? '';
$flash_nome = $_SESSION['flash_nome'] ?? '';
unset($_SESSION['flash_msg'], $_SESSION['flash_nome']);

$opcoes_dinamicas = ['origem' => [], 'servicos' => [], 'estado' => [], 'prioridade' => [], 'interacao' => []];
$stmt_opcoes = $pdo->query("SELECT * FROM crm_opcoes ORDER BY ordem ASC, id ASC");
while ($row = $stmt_opcoes->fetch(PDO::FETCH_ASSOC)) {
    if (array_key_exists($row['tipo'], $opcoes_dinamicas)) {
        $opcoes_dinamicas[$row['tipo']][] = $row['valor'];
    }
}
$comerciais = [];
$stmt_comerciais = $pdo->query("SELECT id_utilizador, nome FROM utilizadores ORDER BY id_utilizador ASC");
$comerciais = $stmt_comerciais->fetchAll(PDO::FETCH_ASSOC);

$funil_estados = !empty($opcoes_dinamicas['estado']) ? $opcoes_dinamicas['estado'] : ['Nova'];

$f_pesquisa = trim($_GET['f_pesquisa'] ?? ''); 
?>

<div class="flex flex-col gap-4 mb-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
        <div>
            <h1 class="text-2xl font-black tracking-tight bg-gradient-to-r from-indigo-400 to-cyan-400 bg-clip-text text-transparent">
                Pipeline & Gestão de Leads
            </h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                <?php if ($tem_filtros || $f_pesquisa): ?>
                    <span class="text-indigo-400 font-bold"><?= $totalRegistos ?></span> resultado<?= $totalRegistos !== 1 ? 's' : '' ?> encontrado<?= $totalRegistos !== 1 ? 's' : '' ?>.
                <?php else: ?>
                    Total de <span class="text-indigo-400 font-bold"><?= $totalRegistos ?></span> potenciais clientes.
                <?php endif; ?>
            </p>
        </div>
        <?php if ($_SESSION['user_perfil'] !== 'comercial'): ?>
            <button onclick="abrirModal('modal-criar-lead')" class="shrink-0 flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm px-4 py-2.5 rounded-xl shadow-lg shadow-indigo-600/20 transition">
                <i data-lucide="plus" class="w-4 h-4"></i>Nova Lead
            </button>
        <?php endif; ?>
    </div>

    <?php
    $n_filtros_avancados = count(array_filter([$f_nome ?? '', $f_estado ?? '', $f_prioridade ?? '', $f_origem ?? '', $f_servico ?? '', $f_responsavel ?? '', $f_data_de ?? '', $f_data_ate ?? '']));
    $painel_aberto = $n_filtros_avancados > 0;
    ?>

    <div class="flex flex-col gap-2 w-full">
        <div class="flex items-center gap-3 w-full">
            <form method="GET" action="index.php" id="form-pesquisa" class="relative flex-1">
                <input type="hidden" name="v" value="leads">
                <?php foreach (['f_estado','f_origem','f_servico','f_responsavel','f_data_de','f_data_ate'] as $fk): ?>
                    <?php if (!empty($$fk)): ?><input type="hidden" name="<?= $fk ?>" value="<?= htmlspecialchars($$fk) ?>"><?php endif; ?>
                <?php endforeach; ?>

                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none z-10"><i data-lucide="search" class="w-4 h-4 text-slate-500 dark:text-slate-400"></i></div>
                    <input type="text" name="f_pesquisa" id="input-pesquisa" value="<?= htmlspecialchars($f_pesquisa ?? '') ?>" placeholder="Pesquisar por nome, empresa, email, telefone, observações..." autocomplete="off" class="w-full h-[46px] bg-white dark:bg-slate-900/60 border <?= !empty($f_pesquisa) ? 'border-indigo-500/60' : 'border-slate-200 dark:border-slate-800/80' ?> rounded-2xl pl-11 pr-12 text-sm text-slate-800 dark:text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500 transition backdrop-blur-xl">
                    <?php if (!empty($f_pesquisa)): ?>
                        <a href="index.php?v=leads" class="absolute inset-y-0 right-4 flex items-center text-slate-500 hover:text-rose-400 transition z-10" title="Limpar pesquisa"><i data-lucide="x-circle" class="w-4 h-4"></i></a>
                    <?php else: ?>
                        <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none z-10"><kbd class="text-[10px] text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 px-1.5 py-0.5 rounded font-mono">Enter</kbd></div>
                    <?php endif; ?>
                </div>
            </form>

            <button type="button" id="btn-toggle-filtros" onclick="toggleFiltros()" title="Filtros" class="relative shrink-0 flex items-center justify-center w-[46px] h-[46px] rounded-2xl border transition <?= $painel_aberto ? 'bg-indigo-600/20 border-indigo-500/50 text-indigo-300' : 'bg-white dark:bg-slate-900/60 border-slate-200 dark:border-slate-800/80 text-slate-600 dark:text-slate-400' ?> backdrop-blur-xl">
                <i data-lucide="sliders-horizontal" class="w-5 h-5 transition-opacity" id="icon-filtros"></i>
                <?php if ($n_filtros_avancados > 0): ?><span class="absolute -top-1.5 -right-1.5 flex items-center justify-center w-5 h-5 rounded-full bg-indigo-600 text-slate-800 dark:text-white text-[10px] font-bold leading-none ring-4 ring-[#0b0f19]"><?= $n_filtros_avancados ?></span><?php endif; ?>
            </button>
        </div>
    </div>
</div>

<div class="mb-6">
    <div id="painel-filtros" class="overflow-hidden transition-all duration-300">
        <div class="bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800/80 rounded-2xl overflow-hidden backdrop-blur-xl">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-300 dark:border-slate-800/60">
                <div class="flex items-center gap-2">
                    <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5 text-indigo-400"></i>
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Filtros</span>
                </div>
                <button type="button" onclick="toggleFiltros()" class="text-slate-500 dark:text-slate-400 hover:text-slate-200 transition"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            
            <form method="GET" action="index.php" id="form-filtros" class="p-4">
                <input type="hidden" name="v" value="leads">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div class="space-y-1.5"><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-widest"><i data-lucide="search" class="w-3 h-3 inline-block mr-1 -mt-0.5"></i>Nome / Empresa</label><input type="text" name="f_nome" value="<?= htmlspecialchars($f_nome ?? '') ?>" class="w-full bg-slate-50 dark:bg-slate-950 border rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-white border-slate-300 dark:border-slate-800 focus:outline-none focus:border-indigo-500 transition"></div>
                    <div class="space-y-1.5"><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-widest"><i data-lucide="activity" class="w-3 h-3 inline-block mr-1 -mt-0.5"></i>Estado</label><select name="f_estado" class="w-full bg-slate-50 dark:bg-slate-950 border rounded-xl px-3 py-2 text-xs border-slate-300 dark:border-slate-800 text-slate-800 dark:text-white"><option value="">Todos</option><?php foreach ($opcoes_dinamicas['estado'] as $e): ?><option value="<?= htmlspecialchars($e) ?>" <?= ($f_estado ?? '') === $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option><?php endforeach; ?></select></div>
                    <div class="space-y-1.5"><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-widest"><i data-lucide="flag" class="w-3 h-3 inline-block mr-1 -mt-0.5"></i>Prioridade</label><select name="f_prioridade" class="w-full bg-slate-50 dark:bg-slate-950 border rounded-xl px-3 py-2 text-xs border-slate-300 dark:border-slate-800 text-slate-800 dark:text-white"><option value="">Todas</option><?php foreach ($opcoes_dinamicas['prioridade'] as $e): ?><option value="<?= htmlspecialchars($e) ?>" <?= ($f_prioridade ?? '') === $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option><?php endforeach; ?></select></div>
                    <div class="space-y-1.5"><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-widest"><i data-lucide="git-branch" class="w-3 h-3 inline-block mr-1 -mt-0.5"></i>Origem</label><select name="f_origem" class="w-full bg-slate-50 dark:bg-slate-950 border rounded-xl px-3 py-2 text-xs border-slate-300 dark:border-slate-800 text-slate-800 dark:text-white"><option value="">Todas</option><?php foreach ($opcoes_dinamicas['origem'] as $opt): ?><option value="<?= htmlspecialchars($opt) ?>" <?= ($f_origem ?? '') === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option><?php endforeach; ?></select></div>
                    <div class="space-y-1.5"><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-widest"><i data-lucide="package" class="w-3 h-3 inline-block mr-1 -mt-0.5"></i>Serviço</label><select name="f_servico" class="w-full bg-slate-50 dark:bg-slate-950 border rounded-xl px-3 py-2 text-xs border-slate-300 dark:border-slate-800 text-slate-800 dark:text-white"><option value="">Todos</option><?php foreach ($opcoes_dinamicas['servicos'] as $opt): ?><option value="<?= htmlspecialchars($opt) ?>" <?= ($f_servico ?? '') === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option><?php endforeach; ?></select></div>
                    <div class="space-y-1.5"><label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-widest"><i data-lucide="user-check" class="w-3 h-3 inline-block mr-1 -mt-0.5"></i>Responsável</label><select name="f_responsavel" class="w-full bg-slate-50 dark:bg-slate-950 border rounded-xl px-3 py-2 text-xs border-slate-300 dark:border-slate-800 text-slate-800 dark:text-white"><option value="">Todos</option><?php foreach ($comerciais as $c): ?><option value="<?= (int)$c['id_utilizador'] ?>" <?= ($f_responsavel ?? '') == $c['id_utilizador'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nome']) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-slate-300 dark:border-slate-800/60">
                    <a href="index.php?v=leads" class="px-3 py-1.5 text-xs rounded-xl border border-slate-300 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-800 dark:hover:text-slate-200 text-slate-600 dark:text-slate-400">Limpar</a>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-4 py-1.5 rounded-xl transition">Aplicar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const painel = document.getElementById('painel-filtros');
    const guardado = localStorage.getItem('painel_filtros_aberto');
    let aberto = guardado !== null ? (guardado === 'true') : <?= $painel_aberto ? 'true' : 'false' ?>;
    
    function aplicarVisual(estadoAberto) {
        if(estadoAberto) { painel.style.maxHeight = painel.scrollHeight + 'px'; painel.style.opacity = '1'; }
        else { painel.style.maxHeight = '0px'; painel.style.opacity = '0'; }
    }
    aplicarVisual(aberto);
    
    window.toggleFiltros = function () {
        aberto = !aberto; localStorage.setItem('painel_filtros_aberto', aberto); aplicarVisual(aberto);
    };
})();
</script>

<?php if ($msg === 'criado'): ?>
    <div id="msg" class="mb-6 flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm px-4 py-3 rounded-xl">
        <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
        <span class="flex-1">Lead <strong>"<?= htmlspecialchars($flash_nome) ?>"</strong> criada com sucesso.</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-emerald-400 hover:text-emerald-200 transition"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
<?php elseif ($msg === 'editado'): ?>
    <div id="msg" class="mb-6 flex items-center gap-2 bg-blue-500/10 border border-blue-500/20 text-blue-400 text-sm px-4 py-3 rounded-xl">
        <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
        <span class="flex-1">Lead <strong>"<?= htmlspecialchars($flash_nome) ?>"</strong> atualizada com sucesso.</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-blue-400 hover:text-blue-200 transition"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
<?php elseif ($msg === 'apagado'): ?>
    <div id="msg" class="mb-6 flex items-center gap-2 bg-amber-500/10 border border-amber-500/20 text-amber-400 text-sm px-4 py-3 rounded-xl">
        <i data-lucide="trash-2" class="w-4 h-4 shrink-0"></i>
        <span class="flex-1">Lead <strong>"<?= htmlspecialchars($flash_nome) ?>"</strong> apagada.</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-amber-400 hover:text-amber-200 transition"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
<?php elseif ($msg === 'interacao_salva'): ?>
    <div id="msg" class="mb-6 flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm px-4 py-3 rounded-xl">
        <i data-lucide="check-circle" class="w-4 h-4 shrink-0"></i>
        <span class="flex-1">Interação registada na lead <strong>"<?= htmlspecialchars($flash_nome) ?>"</strong>.</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-emerald-400 hover:text-emerald-200 transition"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
<?php elseif ($msg === 'erro_validacao'): ?>
    <div id="msg" class="mb-6 flex items-center gap-2 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm px-4 py-3 rounded-xl">
        <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
        <span class="flex-1">Preenche pelo menos o nome ou a empresa.</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-rose-400 hover:text-rose-200 transition"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
<?php elseif ($msg === 'erro_interacao'): ?>
    <div id="msg" class="mb-6 flex items-center gap-2 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm px-4 py-3 rounded-xl">
        <i data-lucide="alert-triangle" class="w-4 h-4 shrink-0"></i>
        <span class="flex-1">Preenche o tipo e a descrição da interação.</span>
        <button onclick="this.parentElement.remove()" class="ml-2 text-rose-400 hover:text-rose-200 transition"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
<?php endif; ?>

<!-- Notificações com X são auto-removidas apenas via botão, sem timeout -->

<?php
if (!empty($resultados) && isset($pdo)) {
    try {
        $stmt_int = $pdo->prepare("SELECT i.tipo, i.descricao, DATE_FORMAT(i.data_registo, '%d/%m/%Y %H:%i') as data_formatada, COALESCE(u.nome, 'Sistema') as registado_por FROM interacoes i LEFT JOIN utilizadores u ON i.id_utilizador = u.id_utilizador WHERE i.id_lead = ? ORDER BY i.data_registo ASC");
        foreach ($resultados as &$linha_ref) {
            $stmt_int->execute([$linha_ref['id_lead']]);
            $linha_ref['historico_completo'] = $stmt_int->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($linha_ref);
    } catch (Exception $e) {}
}


$estados_cols = $funil_estados;
// Apenas adicionamos a coluna de arquivadas se NÃO for comercial
if ($_SESSION['user_perfil'] !== 'comercial') {
    $estados_cols[] = 'Arquivada';
}
$leads_agrupadas = array_fill_keys($estados_cols, []);

if (!empty($resultados)) {
    foreach ($resultados as $lead) {
        $e_atual = !empty($lead['estado']) ? $lead['estado'] : 'Nova';
        
        // Se for comercial, ignoramos as leads arquivadas por completo
        if ($_SESSION['user_perfil'] === 'comercial' && $e_atual === 'Arquivada') {
            continue;
        }
        
        if (!isset($leads_agrupadas[$e_atual])) {
            $e_atual = $funil_estados[0] ?? 'Arquivada';
        }
        $leads_agrupadas[$e_atual][] = $lead;
    }
}
?>

<div class="w-full min-w-0 overflow-hidden">
<div id="kanban-board" class="flex gap-4 overflow-x-auto pb-4 pt-2 items-start custom-scrollbar" ondragover="kanbanBoardDragOver(event)">
    <?php if ($totalRegistos > 0): ?>
        <?php foreach ($leads_agrupadas as $col_estado => $leads_coluna): 
            $is_arquivada = ($col_estado === 'Arquivada');
            
            // Lógica para ordenar por prioridade dentro da coluna
            usort($leads_coluna, function($a, $b) {
                $ordem = ['urgente' => 1, 'alta' => 2, 'média' => 3, 'media' => 3, 'baixa' => 4];
                $pesoA = $ordem[strtolower($a['prioridade'] ?? '')] ?? 5;
                $pesoB = $ordem[strtolower($b['prioridade'] ?? '')] ?? 5;
                return $pesoA <=> $pesoB;
            });

            $leads_display = $is_arquivada ? array_slice($leads_coluna, 0, 3) : array_slice($leads_coluna, 0, 5);
        ?>
            <div class="kanban-column w-[320px] shrink-0 flex flex-col max-h-[75vh] bg-white dark:bg-slate-900/40 border border-slate-200 dark:border-slate-800/80 rounded-2xl overflow-hidden" ondragover="allowDrop(event)" ondragleave="dragLeave(event)" ondrop="drop(event, '<?= htmlspecialchars($col_estado) ?>')">
                <div class="p-3 border-b border-slate-200 dark:border-slate-800/80 bg-slate-50 dark:bg-slate-950/60 flex justify-between items-center shadow-sm pointer-events-none">
                    <h3 class="font-bold text-slate-500 dark:text-slate-300 text-[13px] uppercase tracking-wider flex items-center gap-2"><i data-lucide="<?= $is_arquivada ? 'archive' : 'layers' ?>" class="w-4 h-4 text-indigo-400"></i><?= htmlspecialchars($col_estado === 'Arquivada' ? 'Leads Arquivadas' : $col_estado) ?></h3>
                    <span class="bg-white dark:bg-indigo-600/20 border border-indigo-500/30 text-indigo-300 text-[11px] font-bold px-2 py-0.5 rounded-full shadow-sm"><?= count($leads_coluna) ?></span>
                </div>

                <div class="p-2 space-y-2 overflow-y-auto kanban-cards flex-1 min-h-[150px] bg-slate-50 dark:bg-slate-950/20" data-estado="<?= htmlspecialchars($col_estado) ?>">
                    <?php foreach ($leads_coluna as $index => $linha): 
                        // Limite de exibição inicial: só se aplica à coluna "Arquivada"
                        // (as restantes colunas mostram tudo, o scroll interno trata do resto)
                        $deve_ocultar = $is_arquivada && ($index >= 3);

                        $badge = 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20';
                        if (!empty($linha['prioridade'])) {
                            $pri = strtolower($linha['prioridade']);
                            if ($pri === 'urgente') $badge = 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                            elseif ($pri === 'alta') $badge = 'bg-orange-500/10 text-orange-400 border-orange-500/20';
                            elseif ($pri === 'média' || $pri === 'media') $badge = 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                            elseif ($pri === 'baixa') $badge = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
                        }
                        $ultima_data = !empty($linha['historico_completo']) ? end($linha['historico_completo'])['data_formatada'] : '—';
                        $json_lead = htmlspecialchars(json_encode($linha), ENT_QUOTES, "UTF-8");
                    ?>
                        <?php $is_comercial = ($_SESSION['user_perfil'] === 'comercial'); ?>
                            <div id="lead-card-<?= $linha['id_lead'] ?>" 
                                <?= !$is_comercial ? 'draggable="true"' : 'draggable="false"' ?> 
                                data-estado="<?= htmlspecialchars($col_estado) ?>" 
                                data-lead='<?= $json_lead ?>' 
                                <?= !$is_comercial ? 'ondragstart="drag(event, ' . $linha['id_lead'] . ')" ondragend="dragEnd(event)"' : '' ?> 
                                class="group bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-700/60 hover:border-indigo-500/80 rounded-xl p-3 <?= !$is_comercial ? 'cursor-grab active:cursor-grabbing' : 'cursor-default' ?> hover:shadow-lg hover:shadow-indigo-500/10 transition-all duration-200 relative overflow-hidden flex flex-col <?= $deve_ocultar ? 'hidden' : '' ?>">
                            
                            <div class="lead-card-body flex-1 <?= $is_arquivada ? 'cursor-default' : 'cursor-pointer' ?>"<?= !$is_arquivada ? " onclick='abrirVerLead({$json_lead})'" : '' ?>>
                                <div class="flex justify-between items-start mb-2">
                                    <div class="pr-8 w-full">
                                        <h4 class="text-[13px] font-bold text-slate-800 dark:text-white truncate" title="<?= htmlspecialchars($linha['empresa'] ?: $linha['nome_contacto']) ?>"><?= htmlspecialchars($linha['empresa'] ?: $linha['nome_contacto']) ?></h4>
                                        <?php if($linha['empresa']): ?><p class="text-[10px] text-slate-600 dark:text-slate-400 font-mono mt-0.5 truncate"><?= htmlspecialchars($linha['nome_contacto']) ?></p><?php endif; ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 mb-2"><span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider border <?= $badge ?>"><?= htmlspecialchars($linha['prioridade'] ?: 'S/ Prioridade') ?></span></div>
                                <div class="pt-2 border-t border-slate-100 dark:border-slate-800/60">
                                    <div class="flex items-center gap-1.5 text-[10px] text-slate-500 dark:text-slate-400"><i data-lucide="calendar-clock" class="w-3 h-3 shrink-0"></i><span class="truncate">Últ. Contacto: <strong class="text-slate-700 dark:text-slate-300"><?= $ultima_data ?></strong></span></div>
                                </div>
                            </div>

                            <button type="button" onclick="toggleExpandirCard(event, 'details-<?= $linha['id_lead'] ?>')" class="absolute top-2.5 right-2.5 p-1 bg-slate-100 dark:bg-slate-800 hover:bg-indigo-100 dark:hover:bg-indigo-500/30 text-slate-500 hover:text-indigo-600 dark:text-slate-400 rounded-lg transition-colors z-10" title="Expandir Info">
                                <i data-lucide="chevron-down" class="w-3.5 h-3.5"></i>
                            </button>

                            <div id="details-<?= $linha['id_lead'] ?>" class="hidden mt-3 pt-3 border-t border-dashed border-slate-200 dark:border-slate-700 space-y-1.5 text-[10px] cursor-default">
                                <p class="text-slate-600 dark:text-slate-400"><strong class="text-slate-800 dark:text-slate-200">Email:</strong> <?= htmlspecialchars($linha['email']) ?></p>
                                <p class="text-slate-600 dark:text-slate-400"><strong class="text-slate-800 dark:text-slate-200">Tel:</strong> <?= htmlspecialchars($linha['telefone'] ?: '—') ?></p>
                                <p class="text-slate-600 dark:text-slate-400"><strong class="text-slate-800 dark:text-slate-200">Serviços:</strong> <?= htmlspecialchars($linha['servicos'] ?? '—') ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($is_arquivada): ?>
                        <div id="botao-arquivo-footer" class="contents">
                            <button onclick="abrirModal('modal-ver-arquivadas')" id="btn-arquivo-dinamico" class="w-full mt-1 py-2 text-[11px] font-bold text-indigo-500 bg-indigo-50 dark:bg-indigo-500/10 hover:bg-indigo-100 dark:hover:bg-indigo-500/20 rounded-xl transition-colors flex items-center justify-center gap-1.5 cursor-pointer <?= count($leads_coluna) === 0 ? 'hidden' : '' ?>">
                                <i data-lucide="folder-open" class="w-3 h-3"></i>
                                <span id="texto-btn-arquivo"><?= count($leads_coluna) > 3 ? 'Ver todas ('.count($leads_coluna).')' : 'Abrir Arquivo' ?></span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="w-full flex flex-col items-center justify-center py-20 text-slate-500 bg-white dark:bg-slate-900/40 rounded-2xl border border-slate-200 dark:border-slate-800/80"><i data-lucide="layout-dashboard" class="w-12 h-12 text-slate-600 mb-3"></i><span class="text-sm">Sem registos.</span></div>
    <?php endif; ?>
</div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar, .kanban-cards::-webkit-scrollbar { height: 8px; width: 8px; }
.custom-scrollbar::-webkit-scrollbar-track, .kanban-cards::-webkit-scrollbar-track { background: rgba(226, 232, 240, 0.8); border-radius: 8px; }
html.dark .custom-scrollbar::-webkit-scrollbar-track, html.dark .kanban-cards::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.4); }
.custom-scrollbar::-webkit-scrollbar-thumb, .kanban-cards::-webkit-scrollbar-thumb { background: rgba(99, 102, 241, 0.4); border-radius: 8px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover, .kanban-cards::-webkit-scrollbar-thumb:hover { background: rgba(99, 102, 241, 0.8); }
@keyframes modalIn { from { opacity: 0; transform: scale(0.96) translateY(8px); } to { opacity: 1; transform: scale(1) translateY(0); } }
</style>

<div id="modal-criar-lead" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto backdrop-blur-sm bg-black/75" onclick="fecharModalFora(event, 'modal-criar-lead')">
    <div class="relative bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-700/60 rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden my-auto" style="animation: modalIn .2s ease-out;">
        <div class="h-1 w-full bg-gradient-to-r from-indigo-600 to-cyan-400"></div>
        <div class="p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2"><i data-lucide="plus-circle" class="w-4 h-4 text-indigo-400"></i>Nova Lead</h3>
                <button onclick="fecharModal('modal-criar-lead')" class="text-slate-500 dark:text-slate-400 hover:text-slate-200 p-1 rounded-lg hover:bg-slate-100 dark:bg-slate-800"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <form action="views/leads_actions.php" method="POST" class="space-y-3">
                <input type="hidden" name="action" value="criar">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Nome <span class="text-rose-400">*</span></label><input type="text" name="nome" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500"></div>
                    <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Empresa <span class="text-rose-400">*</span></label><input type="text" name="empresa" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500"></div>
                    <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">E-mail <span class="text-rose-400">*</span></label><input type="email" name="email" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500"></div>
                    <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Telefone</label><input type="tel" name="telefone" pattern="[0-9+ ]*" oninput="this.value=this.value.replace(/[^0-9+ ]/g,'');" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500"></div>
                    <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Origem</label><select name="origem" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:border-indigo-500"><option value=""></option><?php foreach ($opcoes_dinamicas['origem'] as $opt) echo "<option value='".htmlspecialchars($opt)."'>".htmlspecialchars($opt)."</option>"; ?></select></div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Serviços</label>
                        <div class="relative" id="criar-servicos-container">
                            <button type="button" onclick="toggleCustomDropdown(event, 'criar-servicos-list')" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm flex justify-between items-center text-left text-slate-800 dark:text-white"><span id="criar-servicos-label" class="text-slate-400">Selecionar serviços...</span><i data-lucide="chevron-down" class="w-4 h-4 text-slate-500"></i></button>
                            <div id="criar-servicos-list" class="hidden absolute left-0 right-0 mt-1 max-h-48 overflow-y-auto bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-xl z-50 p-2 custom-scrollbar">
                                <?php foreach ($opcoes_dinamicas['servicos'] as $opt): ?>
                                    <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg cursor-pointer text-xs text-slate-800 dark:text-slate-200"><input type="checkbox" name="servicos[]" value="<?= htmlspecialchars($opt) ?>" onchange="atualizarLabelCustom('criar-servicos-list', 'criar-servicos-label')" class="accent-indigo-500 rounded border-slate-300 dark:border-slate-700 w-4 h-4 cursor-pointer"><span><?= htmlspecialchars($opt) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Estado</label><select name="estado" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:border-indigo-500"><?php foreach ($funil_estados as $ef) echo "<option value='$ef'>$ef</option>"; ?></select></div>
                    <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Prioridade</label><select name="prioridade" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:border-indigo-500"><option value=""></option><?php foreach ($opcoes_dinamicas['prioridade'] as $opt) echo "<option value='".htmlspecialchars($opt)."'>".htmlspecialchars($opt)."</option>"; ?></select></div>
                    <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Valor Comercial (€)</label><input type="number" step="0.01" name="valor_potencial" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500"></div>
                    <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Responsável</label><select name="id_responsavel" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:border-indigo-500"><option value="">Por atribuir</option><?php foreach ($comerciais as $c) echo "<option value='".(int)$c['id_utilizador']."'>".htmlspecialchars($c['nome'])."</option>"; ?></select></div>
                    
                    <div class="md:col-span-2"><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5">Observações</label><textarea name="notas" class="w-full h-24 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500 resize-none custom-scrollbar"></textarea></div>
                </div>
                <div class="flex gap-2 pt-1 mt-4">
                    <button type="button" onclick="fecharModal('modal-criar-lead')" class="flex-1 py-2.5 rounded-xl border border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400 text-xs font-medium hover:bg-slate-100 dark:hover:bg-slate-900 transition">Cancelar</button>
                    <button type="submit" class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs flex justify-center items-center gap-1.5 transition"><i data-lucide="save" class="w-3.5 h-3.5"></i> Guardar Lead</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modal-editar-lead" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto backdrop-blur-sm bg-black/75" onclick="fecharModalFora(event, 'modal-editar-lead')">
    <div class="relative bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-700/60 rounded-2xl w-full max-w-6xl shadow-2xl overflow-hidden my-auto" style="animation: modalIn .2s ease-out;">
        <div class="h-1 w-full bg-gradient-to-r from-indigo-600 to-cyan-400"></div>
        <div class="p-6 md:p-8 space-y-6">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2"><i data-lucide="pencil" class="w-4 h-4 text-indigo-400"></i>Editar Lead</h3>
                <button onclick="voltarAoModalVerLead('modal-editar-lead')" class="text-slate-500 dark:text-slate-400 hover:text-slate-200 p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <form action="views/leads_actions.php" method="POST" class="flex flex-col gap-6">
                <input type="hidden" name="action" value="editar"><input type="hidden" name="id_lead" id="edit-lead-id"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
                    <div class="lg:col-span-7 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Nome <span class="text-rose-400">*</span></label><input type="text" name="nome" id="edit-lead-nome" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500 transition"></div>
                        <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Empresa <span class="text-rose-400">*</span></label><input type="text" name="empresa" id="edit-lead-empresa" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500 transition"></div>
                        <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">E-mail <span class="text-rose-400">*</span></label><input type="email" name="email" id="edit-lead-email" required class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500 transition"></div>
                        <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Telemóvel</label><input type="tel" name="telefone" id="edit-lead-telefone" pattern="[0-9+ ]*" oninput="this.value=this.value.replace(/[^0-9+ ]/g,'');" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500 transition"></div>
                        <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Origem</label><select name="origem" id="edit-lead-origem" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:border-indigo-500 transition"><option value=""></option><?php foreach ($opcoes_dinamicas['origem'] as $opt): ?><option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option><?php endforeach; ?></select></div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Serviços</label>
                            <div class="relative" id="edit-servicos-container">
                                <button type="button" onclick="toggleCustomDropdown(event, 'edit-servicos-list')" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm flex justify-between items-center text-slate-800 dark:text-white"><span id="edit-servicos-label" class="text-slate-400">Selecionar serviços...</span><i data-lucide="chevron-down" class="w-4 h-4 text-slate-500"></i></button>
                                <div id="edit-servicos-list" class="hidden absolute left-0 right-0 mt-1 max-h-48 overflow-y-auto bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-xl z-50 p-2 custom-scrollbar">
                                    <?php foreach ($opcoes_dinamicas['servicos'] as $opt): ?>
                                        <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg cursor-pointer text-xs text-slate-800 dark:text-slate-200"><input type="checkbox" name="servicos[]" value="<?= htmlspecialchars($opt) ?>" onchange="atualizarLabelCustom('edit-servicos-list', 'edit-servicos-label')" class="accent-indigo-500 rounded border-slate-300 dark:border-slate-700 w-4 h-4 cursor-pointer"><span><?= htmlspecialchars($opt) ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Estado</label><select name="estado" id="edit-lead-estado" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:border-indigo-500 transition"><option value="Arquivada">Arquivada</option><?php foreach ($funil_estados as $ef) echo "<option value='$ef'>$ef</option>"; ?></select></div>
                        <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Prioridade</label><select name="prioridade" id="edit-lead-prioridade" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:border-indigo-500 transition"><option value=""></option><?php foreach ($opcoes_dinamicas['prioridade'] as $opt) echo "<option value='".htmlspecialchars($opt)."'>".htmlspecialchars($opt)."</option>"; ?></select></div>
                        <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Responsável</label><select name="id_responsavel" id="edit-lead-responsavel" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:border-indigo-500 transition"><option value="">Por atribuir</option><?php foreach ($comerciais as $c) echo "<option value='".(int)$c['id_utilizador']."'>".htmlspecialchars($c['nome'])."</option>"; ?></select></div>
                        <div><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Valor Potencial (€)</label><input type="number" step="0.01" name="valor_potencial" id="edit-lead-valor_potencial" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2.5 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500 transition"></div>
                    </div>
                    <div class="lg:col-span-5 flex flex-col"><label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">Notas / Observações</label><textarea name="notas" id="edit-lead-notas" class="w-full h-[400px] bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500 transition resize-none custom-scrollbar"></textarea></div>
                </div>
                <div class="flex gap-4 pt-4 border-t border-slate-200 dark:border-slate-800/60 mt-2">
                    <button type="button" onclick="voltarAoModalVerLead('modal-editar-lead')" class="w-1/2 py-3 rounded-xl border border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-900 transition">Cancelar</button>
                    <button type="submit" class="w-1/2 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm flex justify-center items-center gap-2 shadow-lg shadow-indigo-600/20 transition"><i data-lucide="save" class="w-4 h-4"></i> Guardar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modal-apagar-lead" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm bg-black/75" onclick="fecharModalFora(event, 'modal-apagar-lead')">
    <div class="relative bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-700/60 rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden" style="animation: modalIn .2s ease-out;">
        <div class="h-1 w-full bg-gradient-to-r from-rose-600 to-rose-400"></div>
        <div class="p-6 space-y-5">
            <div class="flex items-start gap-4">
                <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 p-2.5 rounded-xl shrink-0"><i data-lucide="trash-2" class="w-5 h-5 text-rose-500 dark:text-rose-400"></i></div>
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">Apagar lead</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">Tens a certeza que queres apagar a lead <strong id="apagar-lead-empresa-display" class="text-slate-800 dark:text-slate-200"></strong>? Esta ação não pode ser desfeita.</p>
                </div>
            </div>
            <form action="views/leads_actions.php" method="POST">
                <input type="hidden" name="action" value="apagar"><input type="hidden" name="id_lead" id="apagar-lead-id"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <div class="flex gap-2">
                    <button type="button" onclick="voltarAoModalVerLead('modal-apagar-lead')" class="flex-1 py-2.5 rounded-xl border border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400 text-xs font-medium hover:bg-slate-100 dark:hover:bg-slate-900 transition">Cancelar</button>
                    <button type="submit" class="flex-1 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Apagar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="modal-ver-lead" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto backdrop-blur-sm bg-black/75" onclick="fecharModalFora(event, 'modal-ver-lead')">
    <div class="relative bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-700/60 rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden my-auto flex flex-col max-h-[90vh]" style="animation: modalIn .2s ease-out;">
        <div class="h-1 w-full bg-gradient-to-r from-cyan-600 via-cyan-400 to-indigo-400 shrink-0"></div>

        <div class="flex items-center justify-between px-6 py-4 shrink-0 border-b border-slate-200 dark:border-slate-800">
            <div>
                <h3 id="view-header-title" class="text-lg font-black text-slate-700 dark:text-white"></h3>
                <p id="view-header-subtitle" class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5"></p>
            </div>
            <button onclick="fecharModal('modal-ver-lead')" class="text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <div class="flex gap-6 px-6 pt-2 mt-1 border-b border-slate-200 dark:border-slate-800 shrink-0">
            <button id="tab-btn-ficha" onclick="mudarTabLead('ficha')" class="pb-3 pt-1 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors flex items-center gap-2"><i data-lucide="file-text" class="w-4 h-4"></i> Ficha Técnica</button>
            <button id="tab-btn-historico" onclick="mudarTabLead('historico')" class="pb-3 pt-1 text-sm font-bold border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400 transition-colors flex items-center gap-2"><i data-lucide="history" class="w-4 h-4"></i> Histórico de Interações</button>
        </div>

        <div class="overflow-y-auto p-6 custom-scrollbar flex-1">
            
            <div id="tab-content-historico" class="block space-y-6">
                <!-- A condição PHP deve fechar logo aqui na linha seguinte ao bloco de registo -->
                <?php if ($_SESSION['user_perfil'] !== 'comercial'): ?>
                <div class="bg-slate-50 dark:bg-slate-900/50 border border-slate-300 dark:border-slate-800/60 rounded-xl p-4">
                    <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-3 flex items-center gap-1.5"><i data-lucide="message-square-plus" class="w-4 h-4 text-emerald-400"></i> Registar Interação</h4>
                    <form action="views/leads_actions.php" method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="adicionar_interacao"><input type="hidden" name="id_lead" id="ver-lead-interacao-id"><input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div class="md:col-span-1">
                                <select name="tipo" required class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:border-indigo-500 transition">
                                    <option value="">Tipo...</option><?php foreach ($opcoes_dinamicas['interacao'] as $opt): ?><option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-3 flex gap-2">
                                <input type="text" name="descricao" required placeholder="Resumo detalhado do contacto realizado..." class="w-full bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-sm text-slate-800 dark:text-white focus:outline-none focus:border-indigo-500 transition">
                                <button type="submit" class="shrink-0 bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-4 py-2 rounded-xl transition shadow-lg shadow-emerald-600/20">Registar</button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?> <!-- O PHP ENDIF FECHA AQUI! -->

                <div>
                    <h4 class="text-xs font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-4 flex items-center gap-1.5"><i data-lucide="list" class="w-4 h-4 text-slate-400"></i> Registos Cronológicos</h4>
                    <div id="historico-interacoes-container" class="space-y-4 relative before:absolute before:inset-y-0 before:left-3.5 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-800/80"></div>
                </div>
            </div>

            <div id="tab-content-ficha" class="hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6">
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3 border border-slate-200 dark:border-slate-800/80"><p class="text-[10px] text-slate-500 uppercase tracking-wider mb-0.5">Nome de Contacto</p><p id="view-nome" class="text-slate-700 dark:text-slate-200 font-bold text-sm"></p></div>
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3 border border-slate-200 dark:border-slate-800/80"><p class="text-[10px] text-slate-500 uppercase tracking-wider mb-0.5">Empresa / Organização</p><p id="view-empresa" class="text-slate-700 dark:text-slate-200 font-bold text-sm"></p></div>
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3 border border-slate-200 dark:border-slate-800/80"><p class="text-[10px] text-slate-500 uppercase tracking-wider mb-0.5">Endereço de E-mail</p><p id="view-email" class="text-slate-700 dark:text-slate-200 text-sm font-mono"></p></div>
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3 border border-slate-200 dark:border-slate-800/80"><p class="text-[10px] text-slate-500 uppercase tracking-wider mb-0.5">Telefone / Telemóvel</p><p id="view-telefone" class="text-slate-700 dark:text-slate-200 text-sm font-mono"></p></div>
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3 border border-slate-200 dark:border-slate-800/80"><p class="text-[10px] text-slate-500 uppercase tracking-wider mb-0.5">Origem da Lead</p><p id="view-origem" class="text-slate-700 dark:text-slate-200 text-sm"></p></div>
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3 border border-slate-200 dark:border-slate-800/80"><p class="text-[10px] text-slate-500 uppercase tracking-wider mb-0.5">Serviços Pretendidos</p><p id="view-servicos" class="text-slate-700 dark:text-slate-200 text-sm"></p></div>
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3 border border-slate-200 dark:border-slate-800/80"><p class="text-[10px] text-slate-500 uppercase tracking-wider mb-0.5">Estado Atual</p><p id="view-estado" class="text-slate-700 dark:text-slate-200 font-bold text-sm"></p></div>
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3 border border-slate-200 dark:border-slate-800/80"><p class="text-[10px] text-slate-500 uppercase tracking-wider mb-0.5">Nível de Prioridade</p><p id="view-prioridade" class="text-slate-700 dark:text-slate-200 font-bold text-sm"></p></div>
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3 border border-slate-200 dark:border-slate-800/80"><p class="text-[10px] text-slate-500 uppercase tracking-wider mb-0.5">Valor Comercial Potencial</p><p id="view-valor" class="text-emerald-500 font-bold text-sm"></p></div>
                    <div class="bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3 border border-slate-200 dark:border-slate-800/80"><p class="text-[10px] text-slate-500 uppercase tracking-wider mb-0.5">Gestor Comercial Responsável</p><p id="view-responsavel" class="text-slate-700 dark:text-slate-200 text-sm"></p></div>
                    <div class="md:col-span-2 bg-slate-50 dark:bg-slate-950/60 rounded-xl p-3 border border-slate-200 dark:border-slate-800/80"><p class="text-[10px] text-slate-500 uppercase tracking-wider mb-0.5">Observações Globais da Base</p><p id="view-observacoes" class="text-slate-800 dark:text-slate-300 whitespace-pre-line text-sm leading-relaxed"></p></div>
                </div>
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <?php if ($_SESSION['user_perfil'] !== 'comercial'): ?>
                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                            <button id="btn-ficha-editar" type="button" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition shadow-lg shadow-indigo-600/20 flex items-center gap-2"><i data-lucide="pencil" class="w-4 h-4 inline"></i> Editar Dados</button>
                            <button id="btn-ficha-apagar" type="button" class="bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition shadow-lg shadow-rose-600/20 flex items-center gap-2"><i data-lucide="trash-2" class="w-4 h-4 inline"></i> Eliminar Lead</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($_SESSION['user_perfil'] !== 'comercial'): ?>
<div id="modal-ver-arquivadas" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm bg-black/75" onclick="fecharModalFora(event, 'modal-ver-arquivadas')">
    <div class="relative bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-700/60 rounded-2xl w-full max-w-2xl shadow-2xl overflow-hidden my-auto flex flex-col max-h-[80vh]" style="animation: modalIn .2s ease-out;">
        <div class="h-1 w-full bg-gradient-to-r from-slate-600 to-slate-400 shrink-0"></div>
        <div class="p-6 shrink-0 border-b border-slate-200 dark:border-slate-800">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2"><i data-lucide="archive" class="w-5 h-5 text-slate-400"></i> Arquivo de Leads</h3>
                <button onclick="fecharModal('modal-ver-arquivadas')" class="text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
        </div>
        <div id="lista-arquivo-modal" class="p-6 overflow-y-auto space-y-3 custom-scrollbar flex-1">
            <?php if(empty($leads_agrupadas['Arquivada'])): ?>
                <div id="arquivo-modal-vazio" class="py-8 text-center text-slate-500 dark:text-slate-400"><i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 opacity-30"></i><p class="text-sm">Não existem registos arquivados.</p></div>
            <?php else: ?>
                <?php foreach($leads_agrupadas['Arquivada'] as $linha): ?>
                    <div id="arquivada-item-<?= $linha['id_lead'] ?>" class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-800 rounded-xl hover:border-indigo-500/50 transition-colors">
                        <div>
                            <h4 class="text-sm font-bold text-slate-800 dark:text-white"><?= htmlspecialchars($linha['empresa'] ?: $linha['nome_contacto']) ?></h4>
                            <div class="flex gap-3 text-xs text-slate-500 mt-1"><span><i data-lucide="mail" class="w-3 h-3 inline-block mr-0.5"></i> <?= htmlspecialchars($linha['email']) ?></span><span><i data-lucide="tag" class="w-3 h-3 inline-block mr-0.5"></i> <?= htmlspecialchars($linha['servicos'] ?? 'Geral') ?></span></div>
                        </div>
                        <button type="button" data-lead='<?= htmlspecialchars(json_encode($linha), ENT_QUOTES, "UTF-8") ?>' onclick="fecharModal('modal-ver-arquivadas'); abrirVerLead(JSON.parse(this.dataset.lead))" class="shrink-0 px-4 py-2 bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-600 dark:text-indigo-400 text-xs font-bold rounded-lg transition-colors cursor-pointer">Ver Ficha Lead</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Nova lógica de Dropdowns Custom
window.toggleCustomDropdown = function(event, listId) { event.stopPropagation(); document.getElementById(listId).classList.toggle('hidden'); };
window.atualizarLabelCustom = function(listId, labelId) {
    const cb = document.getElementById(listId).querySelectorAll('input[type="checkbox"]:checked');
    const lbl = document.getElementById(labelId);
    lbl.textContent = cb.length === 0 ? "Selecionar..." : Array.from(cb).map(c => c.value).join(', ');
};
document.addEventListener('click', e => {
    document.querySelectorAll('[id$="-servicos-list"]').forEach(el => {
        if (!el.parentElement.contains(e.target)) el.classList.add('hidden');
    });
});

// Fechar modais de forma robusta
function fecharModal(id) { document.getElementById(id).classList.add('hidden'); }

// Rasteia onde o mousedown começou para evitar fechar o modal ao arrastar de dentro para fora
let _modalMousedownTarget = null;
document.addEventListener('mousedown', function(e) { _modalMousedownTarget = e.target; });

function fecharModalFora(event, id) {
    // Só fecha se o mousedown E o click/mouseup ocorreram no backdrop
    const modal = document.getElementById(id);
    const isBackdrop = (t) => t === modal;
    if (isBackdrop(event.target) && isBackdrop(_modalMousedownTarget)) {
        fecharModal(id);
    }
}
function voltarAoModalVerLead(idFechar) {
    fecharModal(idFechar);
    abrirModal('modal-ver-lead'); // Reabre o modal, os dados continuam lá
}

function abrirModal(id) { document.getElementById(id).classList.remove('hidden'); }

// EXPANDIR CARTÃO NO KANBAN
window.toggleExpandirCard = function(event, detailsId) {
    event.stopPropagation(); // Previne que o clique dispare o abrirVerLead() do cartão inteiro
    const detailsDiv = document.getElementById(detailsId);
    const icon = event.currentTarget.querySelector('i');
    if (detailsDiv.classList.contains('hidden')) {
        detailsDiv.classList.remove('hidden');
        icon.setAttribute('data-lucide', 'chevron-up');
    } else {
        detailsDiv.classList.add('hidden');
        icon.setAttribute('data-lucide', 'chevron-down');
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
};

window.mudarTabLead = function(tab) {
    const els = { h: 'tab-content-historico', f: 'tab-content-ficha', bh: 'tab-btn-historico', bf: 'tab-btn-ficha' };
    const onClass = 'pb-3 pt-1 text-sm font-bold border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400 transition-colors flex items-center gap-2';
    const offClass = 'pb-3 pt-1 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition-colors flex items-center gap-2';
    
    document.getElementById(els.h).classList.toggle('hidden', tab !== 'historico');
    document.getElementById(els.f).classList.toggle('hidden', tab === 'historico');
    document.getElementById(els.bh).className = tab === 'historico' ? onClass : offClass;
    document.getElementById(els.bf).className = tab === 'ficha' ? onClass : offClass;
};

// ── FUNÇÃO JS ABRIR VER LEAD (COMPLETAMENTE RESTAURADA E FIXA) ──
function abrirVerLead(lead) {
    // 1. Títulos do Cabeçalho
    document.getElementById('view-header-title').innerText = lead.empresa || lead.nome_contacto;
    document.getElementById('view-header-subtitle').innerText = lead.empresa ? lead.nome_contacto : 'Cliente Particular';

    // 2. Preencher TODOS os campos da Ficha Técnica
    document.getElementById('view-nome').innerText        = lead.nome_contacto || '—';
    document.getElementById('view-empresa').innerText     = lead.empresa || 'Particular';
    document.getElementById('view-email').innerText       = lead.email || '—';
    document.getElementById('view-telefone').innerText    = lead.telefone || '—';
    document.getElementById('view-origem').innerText      = lead.origem || '—';
    document.getElementById('view-servicos').innerText    = lead.servicos || '—';
    document.getElementById('view-estado').innerText      = lead.estado || 'Nova';
    document.getElementById('view-prioridade').innerText  = lead.prioridade || 'Nenhuma';
    document.getElementById('view-valor').innerText       = lead.valor_potencial ? parseFloat(lead.valor_potencial).toLocaleString('pt-PT', { style: 'currency', currency: 'EUR' }) : '—';
    document.getElementById('view-responsavel').innerText = lead.nome_responsavel || 'Sem responsável atribuído';
    document.getElementById('view-observacoes').innerText = lead.observacoes || 'Sem notas associadas.';

    const inputInteracao = document.getElementById('ver-lead-interacao-id');
    if (inputInteracao) {
        inputInteracao.value = lead.id_lead;
    }

    // 3. Botões de ação da ficha técnica
        const btnEditar = document.getElementById('btn-ficha-editar');
        if (btnEditar) {
            btnEditar.onclick = function() { fecharModal('modal-ver-lead'); abrirEditarLead(lead); };
        }
        const btnApagar = document.getElementById('btn-ficha-apagar');
        if (btnApagar) {
            btnApagar.onclick = function() { fecharModal('modal-ver-lead'); confirmarApagarLead(lead.id_lead, lead.empresa || lead.nome_contacto); };
        }
    
    // 4. Preencher a timeline do HISTÓRICO DE INTERAÇÕES
    const container = document.getElementById('historico-interacoes-container');
    container.innerHTML = ''; 

    const historico = lead.historico_completo || [];

    if (historico.length === 0) {
        container.innerHTML = `
            <div class="text-center py-6 text-slate-500 dark:text-slate-400 text-xs italic flex flex-col items-center gap-2 relative z-10 bg-slate-50 dark:bg-slate-950/40 rounded-xl">
                <i data-lucide="info" class="w-5 h-5 opacity-50"></i> Nenhum contacto registado nesta lead.
            </div>`;
    } else {
        historico.forEach(inter => {
            let icon = 'message-square';
            let color = 'text-slate-600 dark:text-slate-400 bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-800';
            
            if (inter.tipo === 'Chamada') { icon = 'phone'; color = 'text-emerald-400 bg-white dark:bg-[#0b0f19] border-emerald-500/30'; }
            else if (inter.tipo === 'E-mail') { icon = 'mail'; color = 'text-cyan-400 bg-white dark:bg-[#0b0f19] border-cyan-500/30'; }
            else if (inter.tipo === 'Reunião') { icon = 'users'; color = 'text-amber-400 bg-white dark:bg-[#0b0f19] border-amber-500/30'; }
            else if (inter.tipo === 'Proposta') { icon = 'file-text'; color = 'text-indigo-400 bg-white dark:bg-[#0b0f19] border-indigo-500/30'; }

            let dataFormatada = inter.data_formatada || '—';

            const div = document.createElement('div');
            div.className = "relative pl-10 space-y-1.5 group";
            div.innerHTML = `
                <div class="absolute left-0 top-0.5 flex items-center justify-center w-7 h-7 rounded-full ring-4 ring-white dark:ring-[#0b0f19] z-10 border ${color}">
                    <i data-lucide="${icon}" class="w-3.5 h-3.5"></i>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">${inter.tipo}</span>
                    <span class="text-[10px] text-slate-500 dark:text-slate-400 font-mono bg-white dark:bg-slate-900 px-1.5 py-0.5 rounded">${dataFormatada}</span>
                </div>
                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-800/80 rounded-xl p-3 shadow-sm">${inter.descricao}</p>
                <span class="text-[9px] text-slate-600 block pl-1 font-medium">Registado por: ${inter.registado_por}</span>
            `;
            container.prepend(div);
        });
    }

    // 5. Força a aba Ficha Técnica a abrir por defeito e mostra o modal
    mudarTabLead('ficha');
    abrirModal('modal-ver-lead');
    
    // 6. ATIVAR OS ÍCONES RECÉM INJETADOS!
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function abrirEditarLead(lead) {
    document.getElementById('edit-lead-id').value = lead.id_lead;
    document.getElementById('edit-lead-nome').value = lead.nome_contacto;
    document.getElementById('edit-lead-empresa').value = lead.empresa;
    document.getElementById('edit-lead-email').value = lead.email;
    document.getElementById('edit-lead-telefone').value = lead.telefone;
    document.getElementById('edit-lead-origem').value = lead.origem;
    document.getElementById('edit-lead-estado').value = lead.estado || 'Nova';
    document.getElementById('edit-lead-prioridade').value = lead.prioridade;
    document.getElementById('edit-lead-valor_potencial').value = lead.valor_potencial;
    document.getElementById('edit-lead-responsavel').value = lead.id_responsavel ?? '';
    document.getElementById('edit-lead-notas').value = lead.observacoes || '';

    const servicosArr = lead.servicos ? lead.servicos.split(',').map(s => s.trim()) : [];
    document.querySelectorAll('#edit-servicos-list input').forEach(cb => cb.checked = servicosArr.includes(cb.value));
    atualizarLabelCustom('edit-servicos-list', 'edit-servicos-label');
    abrirModal('modal-editar-lead');
}

function confirmarApagarLead(id, empresa) {
    document.getElementById('apagar-lead-id').value = id;
    document.getElementById('apagar-lead-empresa-display').textContent = empresa;
    abrirModal('modal-apagar-lead');
}

// DRAG AND DROP - Atualiza Estado na Base de Dados
let kanbanDragActive = false;
let kanbanScrollRaf = null;

function obterLeadDoCard(card) {
    const raw = card.getAttribute('data-lead');
    if (!raw) return null;
    try { return JSON.parse(raw); } catch { return null; }
}

function configurarCliqueCard(card, estado, leadObj) {
    const body = card.querySelector('.lead-card-body');
    if (!body) return;
    body.removeAttribute('onclick');
    body.onclick = null;
    if (estado === 'Arquivada') {
        body.classList.remove('cursor-pointer');
        body.classList.add('cursor-default');
    } else {
        body.classList.add('cursor-pointer');
        body.classList.remove('cursor-default');
        body.onclick = (e) => {
            e.stopPropagation();
            if (leadObj) abrirVerLead(leadObj);
        };
    }
}

function criarItemArquivoModal(lead) {
    const div = document.createElement('div');
    div.id = `arquivada-item-${lead.id_lead}`;
    div.className = 'flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-800 rounded-xl hover:border-indigo-500/50 transition-colors';
    const titulo = lead.empresa || lead.nome_contacto || '—';
    const email = lead.email || '—';
    const servicos = lead.servicos || 'Geral';
    div.innerHTML = `
        <div>
            <h4 class="text-sm font-bold text-slate-800 dark:text-white"></h4>
            <div class="flex gap-3 text-xs text-slate-500 mt-1">
                <span><i data-lucide="mail" class="w-3 h-3 inline-block mr-0.5"></i> <span class="arquivo-email"></span></span>
                <span><i data-lucide="tag" class="w-3 h-3 inline-block mr-0.5"></i> <span class="arquivo-servicos"></span></span>
            </div>
        </div>
        <button type="button" class="shrink-0 px-4 py-2 bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-600 dark:text-indigo-400 text-xs font-bold rounded-lg transition-colors cursor-pointer">Ver Ficha Lead</button>`;
    div.querySelector('h4').textContent = titulo;
    div.querySelector('.arquivo-email').textContent = email;
    div.querySelector('.arquivo-servicos').textContent = servicos;
    div.querySelector('button').onclick = () => {
        fecharModal('modal-ver-arquivadas');
        abrirVerLead(lead);
    };
    return div;
}

function adicionarLeadAoArquivoModal(lead) {
    const lista = document.getElementById('lista-arquivo-modal');
    if (!lista || !lead) return;
    document.getElementById('arquivo-modal-vazio')?.remove();
    if (document.getElementById(`arquivada-item-${lead.id_lead}`)) return;
    lista.appendChild(criarItemArquivoModal(lead));
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function removerLeadDoArquivoModal(leadId) {
    document.getElementById(`arquivada-item-${leadId}`)?.remove();
    const lista = document.getElementById('lista-arquivo-modal');
    if (!lista) return;
    if (lista.querySelectorAll('[id^="arquivada-item-"]').length === 0) {
        lista.innerHTML = `<div id="arquivo-modal-vazio" class="py-8 text-center text-slate-500 dark:text-slate-400"><i data-lucide="inbox" class="w-10 h-10 mx-auto mb-3 opacity-30"></i><p class="text-sm">Não existem registos arquivados.</p></div>`;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

function obterContentorScrollKanban() {
    const board = document.getElementById('kanban-board');
    if (!board) return null;
    if (board.scrollWidth > board.clientWidth + 2) return board;
    let el = board.parentElement;
    while (el && el !== document.body) {
        const { overflowX } = getComputedStyle(el);
        if ((overflowX === 'auto' || overflowX === 'scroll') && el.scrollWidth > el.clientWidth + 2) {
            return el;
        }
        el = el.parentElement;
    }
    return board;
}

function obterLimitesScrollKanban() {
    const board = document.getElementById('kanban-board');
    const main = board?.closest('main');
    return (main || board)?.getBoundingClientRect() || { left: 0, right: window.innerWidth };
}

function scrollKanbanNearEdge(clientX) {
    const container = obterContentorScrollKanban();
    if (!container) return;
    const maxScroll = container.scrollWidth - container.clientWidth;
    if (maxScroll <= 1) return;

    const bounds = obterLimitesScrollKanban();
    const zona = 200;
    let velocidade = 0;

    if (clientX < bounds.left + zona) {
        velocidade = -Math.max(8, Math.round(((bounds.left + zona) - clientX) / zona * 28));
    } else if (clientX > bounds.right - zona) {
        velocidade = Math.max(8, Math.round((clientX - (bounds.right - zona)) / zona * 28));
    }

    if (velocidade !== 0) {
        container.scrollLeft += velocidade;
    }
}

function tickKanbanAutoScroll() {
    if (!kanbanDragActive) return;
    if (window._kanbanDragX != null) scrollKanbanNearEdge(window._kanbanDragX);
    kanbanScrollRaf = requestAnimationFrame(tickKanbanAutoScroll);
}

function registarPosicaoDrag(e) {
    if (!kanbanDragActive) return;
    e.preventDefault();
    if (e.clientX != null) window._kanbanDragX = e.clientX;
}

function registarPosicaoRato(e) {
    if (!kanbanDragActive) return;
    if (e.clientX != null) window._kanbanDragX = e.clientX;
}

function kanbanBoardDragOver(e) {
    registarPosicaoDrag(e);
}

function prepararScrollKanbanDrag() {
    const board = document.getElementById('kanban-board');
    if (!board) return;
    board.dataset.prevSnap = board.style.scrollSnapType || '';
    board.style.scrollSnapType = 'none';
    board.style.scrollBehavior = 'auto';
}

function restaurarScrollKanbanDrag() {
    const board = document.getElementById('kanban-board');
    if (!board) return;
    board.style.scrollSnapType = board.dataset.prevSnap || '';
    board.style.scrollBehavior = '';
}

function iniciarAutoScrollKanban() {
    kanbanDragActive = true;
    window._kanbanDragX = null;
    prepararScrollKanbanDrag();
    document.addEventListener('dragover', registarPosicaoDrag, true);
    document.addEventListener('drag', registarPosicaoDrag, true);
    document.addEventListener('mousemove', registarPosicaoRato, true);
    document.addEventListener('pointermove', registarPosicaoRato, true);
    if (!kanbanScrollRaf) kanbanScrollRaf = requestAnimationFrame(tickKanbanAutoScroll);
}

function pararAutoScrollKanban() {
    kanbanDragActive = false;
    document.removeEventListener('dragover', registarPosicaoDrag, true);
    document.removeEventListener('drag', registarPosicaoDrag, true);
    document.removeEventListener('mousemove', registarPosicaoRato, true);
    document.removeEventListener('pointermove', registarPosicaoRato, true);
    if (kanbanScrollRaf) {
        cancelAnimationFrame(kanbanScrollRaf);
        kanbanScrollRaf = null;
    }
    window._kanbanDragX = null;
    restaurarScrollKanbanDrag();
}

function drag(ev, leadId) {
    ev.stopPropagation();
    ev.dataTransfer.setData('cardId', ev.currentTarget.id);
    ev.dataTransfer.setData('leadId', leadId);
    ev.dataTransfer.effectAllowed = 'move';
    setTimeout(() => ev.currentTarget.classList.add('opacity-40', 'scale-95'), 0);
    window._kanbanDragX = ev.clientX;
    iniciarAutoScrollKanban();
}

function dragEnd(ev) {
    ev.currentTarget.classList.remove('opacity-40', 'scale-95');
    pararAutoScrollKanban();
}

function allowDrop(ev) {
    ev.preventDefault();
    ev.dataTransfer.dropEffect = 'move';
    registarPosicaoDrag(ev);
    const c = ev.currentTarget.querySelector('.kanban-cards');
    if (c) c.classList.add('bg-indigo-500/10', 'dark:bg-slate-800/40', 'ring-2', 'ring-indigo-500/50');
}

function dragLeave(ev) {
    const c = ev.currentTarget.querySelector('.kanban-cards');
    if (c && !ev.currentTarget.contains(ev.relatedTarget)) {
        c.classList.remove('bg-indigo-500/10', 'dark:bg-slate-800/40', 'ring-2', 'ring-indigo-500/50');
    }
}

// Nova função que reordena os cartões visualmente na coluna
function ordenarColuna(container) {
    const cards = Array.from(container.querySelectorAll('.group[draggable="true"]'));
    const pesos = { 'urgente': 1, 'alta': 2, 'média': 3, 'media': 3, 'baixa': 4 };

    cards.sort((a, b) => {
        // Vai buscar o texto da badge de prioridade dentro do cartão
        const spanA = a.querySelector('.uppercase.tracking-wider.border');
        const spanB = b.querySelector('.uppercase.tracking-wider.border');
        const valA = spanA ? spanA.textContent.trim().toLowerCase() : '';
        const valB = spanB ? spanB.textContent.trim().toLowerCase() : '';
        
        const pesoA = pesos[valA] || 5;
        const pesoB = pesos[valB] || 5;
        
        return pesoA - pesoB;
    });

    // Reanexa os cartões na nova ordem instantaneamente
    cards.forEach(c => container.appendChild(c));

    const footer = container.querySelector('#botao-arquivo-footer');
    if (footer) container.appendChild(footer);
}

function atualizarLimitesVisuais() {
    document.querySelectorAll('.kanban-cards').forEach(col => {
        const estado = col.getAttribute('data-estado');
        const isArquivada = (estado === 'Arquivada');

        // Seleciona exclusivamente os cartões de lead contidos nesta coluna
        const cards = col.querySelectorAll(':scope > [id^="lead-card-"]');
        const totalCards = cards.length;

        if (isArquivada) {
            // Nas arquivadas mantém-se o limite de 3 + botão "Ver todas"
            cards.forEach((card, index) => {
                if (index < 3) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        } else {
            // Nas restantes colunas mostram-se sempre todos os cards (a coluna tem scroll próprio)
            cards.forEach(card => card.classList.remove('hidden'));
        }

        // 1. Sincroniza dinamicamente o contador da Badge da coluna (h3 + span)
        const badge = col.parentElement.querySelector('h3 + span');
        if (badge) {
            badge.textContent = totalCards;
        }

        // 2. Atualiza o rodapé do arquivo
        if (isArquivada) {
            const btnArquivo = document.getElementById('btn-arquivo-dinamico');
            const textoBtn = document.getElementById('texto-btn-arquivo');
            if (btnArquivo) {
                if (totalCards === 0) {
                    btnArquivo.classList.add('hidden');
                } else {
                    btnArquivo.classList.remove('hidden');
                    btnArquivo.classList.add('cursor-pointer');
                    if (textoBtn) {
                        textoBtn.textContent = totalCards > 3 ? `Ver todas (${totalCards})` : 'Abrir Arquivo';
                    }
                }
            }
        }
    });
}

function drop(ev, novoEstado) {
    ev.preventDefault();
    const area = ev.currentTarget.querySelector('.kanban-cards');
    if (area) area.classList.remove('bg-indigo-500/10', 'dark:bg-slate-800/40', 'ring-2', 'ring-indigo-500/50');

    const cardId = ev.dataTransfer.getData('cardId');
    const leadId = ev.dataTransfer.getData('leadId');
    const card = document.getElementById(cardId);
    if (!card) return;

    const estadoAnterior = card.dataset.estado;
    if (estadoAnterior === novoEstado) return;

    card.dataset.estado = novoEstado;
    area.appendChild(card);

    let leadObj = obterLeadDoCard(card);
    if (leadObj) {
        leadObj.estado = novoEstado;
        card.setAttribute('data-lead', JSON.stringify(leadObj));
    }

    ordenarColuna(area);
    atualizarEstadoBackend(leadId, novoEstado);

    if (novoEstado === 'Arquivada') {
        configurarCliqueCard(card, 'Arquivada', leadObj);
        if (leadObj) adicionarLeadAoArquivoModal(leadObj);
    } else {
        if (estadoAnterior === 'Arquivada') removerLeadDoArquivoModal(leadId);
        configurarCliqueCard(card, novoEstado, leadObj);
    }

    atualizarLimitesVisuais();
}

function atualizarEstadoBackend(id, estado) {
    const formData = new FormData();
    formData.append('action', 'atualizar_estado_dragdrop');
    formData.append('id_lead', id);
    formData.append('estado', estado);
    formData.append('csrf_token', '<?= $csrf_token ?>');

    fetch('views/leads_actions.php', { 
        method: 'POST', 
        body: formData,
        credentials: 'same-origin' // <--- Adiciona esta linha
    })
    .then(async r => {
        const texto = await r.text();
        if (!r.ok || !texto.includes('OK')) {
            console.error('Falha na resposta do servidor:', texto);
            alert("Atenção: A base de dados não gravou o novo estado.\nVerifica o ficheiro leads_actions.php");
        } else {
            console.log('Estado guardado na BD com sucesso!');
        }
    })
    .catch(err => alert("Atenção: Erro de rede ou servidor não respondeu (" + err.message + ")"));
}

document.addEventListener('DOMContentLoaded', () => { if(typeof lucide !== 'undefined') lucide.createIcons(); });
</script>

<?php
if (isset($_GET['abrir_lead_id']) && isset($pdo)): 
    $id_alvo = (int)$_GET['abrir_lead_id'];
    try {
        $stmt_lead = $pdo->prepare("SELECT l.*, u.nome as nome_responsavel FROM leads l LEFT JOIN utilizadores u ON l.id_responsavel = u.id_utilizador WHERE l.id_lead = ?");
        $stmt_lead->execute([$id_alvo]);
        $lead_alvo = $stmt_lead->fetch(PDO::FETCH_ASSOC);
        
        if ($lead_alvo):
            $stmt_int = $pdo->prepare("SELECT i.tipo, i.descricao, DATE_FORMAT(i.data_registo, '%d/%m/%Y %H:%i') as data_formatada, COALESCE(u.nome, 'Sistema') as registado_por FROM interacoes i LEFT JOIN utilizadores u ON i.id_utilizador = u.id_utilizador WHERE i.id_lead = ? ORDER BY i.data_registo ASC");
            $stmt_int->execute([$id_alvo]);
            $lead_alvo['historico_completo'] = $stmt_int->fetchAll(PDO::FETCH_ASSOC);
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            const leadData = <?= json_encode($lead_alvo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            if (typeof abrirVerLead === 'function') {
                abrirVerLead(leadData);
                if (window.history && window.history.replaceState) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('abrir_lead_id');
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
</body>
</html>