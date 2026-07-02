<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: ../views/login.php");
    exit;
}

date_default_timezone_set('Europe/Lisbon'); // Definir o fuso horário para Portugal

if (isset($conn) && !isset($pdo)) {
    $pdo = $conn;
}

// --- VARIÁVEIS E FUNIL DE ESTADOS ---
$total_leads        = 0;
$leads_ganhas       = 0;
$leads_perdidas     = 0;
$propostas_enviadas = 0;
$taxa_conversao     = 0;
$sem_acompanhamento = 0;
$por_estado         = [];
$por_origem         = [];
$lista_quentes      = [];
$lista_tarefas_hoje = [];

$pipeline_funil = ['Nova' => 0];

$data_atual = date('Y-m-d H:i:s');

// --- FILTROS DE DATA ---
$periodo     = $_GET['periodo'] ?? 'mes_anterior';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim    = $_GET['data_fim'] ?? '';

switch ($periodo) {
    case 'este_mes':
        $data_inicio = date('Y-m-01');
        $data_fim    = date('Y-m-t');
        break;
    case 'mes_anterior':
        $data_inicio = date('Y-m-d', strtotime('first day of last month'));
        $data_fim    = date('Y-m-d', strtotime('last day of last month'));
        break;
    case 'ultimos_30_dias':
        $data_inicio = date('Y-m-d', strtotime('-30 days'));
        $data_fim    = date('Y-m-d');
        break;
    case 'ultimos_6_meses':
        $data_inicio = date('Y-m-d', strtotime('-6 months'));
        $data_fim    = date('Y-m-d');
        break;
    case 'este_ano':
        $data_inicio = date('Y-01-01');
        $data_fim    = date('Y-12-31');
        break;
    case 'ultimo_ano':
        $data_inicio = date('Y-m-d', strtotime('-1 year'));
        $data_fim    = date('Y-m-d');
        break;
    case 'ultimos_2_anos':
        $data_inicio = date('Y-m-d', strtotime('-2 years'));
        $data_fim    = date('Y-m-d');
        break;
    case 'tudo':
        $data_inicio = '';
        $data_fim    = '';
        break;
    case 'personalizado':
        break;
}

$erro_data = null;

// Verifica se a data de início é maior que a de fim
if (!empty($data_inicio) && !empty($data_fim) && $data_inicio > $data_fim) {
    $erro_data = "A data de início não pode ser posterior à data de fim. Por favor, verifique os filtros.";
}

$where_clauses = [];
$params        = [];

if (!$erro_data) {
    if (!empty($data_inicio)) {
        if (empty($data_fim)) {
            $data_fim = date('Y-m-d');
        }
        $where_clauses[] = "data_criacao BETWEEN :data_inicio AND :data_fim";
        $params[':data_inicio'] = $data_inicio . ' 00:00:00';
        $params[':data_fim']    = $data_fim . ' 23:59:59';
    } elseif (!empty($data_fim)) {
        $where_clauses[] = "data_criacao <= :data_fim";
        $params[':data_fim'] = $data_fim . ' 23:59:59';
    }
}

function aplicarFiltroPeriodo($baseSql, $where_clauses, $suffix = '') {
    if (!empty($where_clauses)) {
        if (stripos($baseSql, 'WHERE') !== false) {
            return $baseSql . " AND " . implode(' AND ', $where_clauses) . " " . $suffix;
        } else {
            return $baseSql . " WHERE " . implode(' AND ', $where_clauses) . " " . $suffix;
        }
    }
    return $baseSql . " " . $suffix;
}

if (isset($pdo)) {
    try {
        // Métricas / KPIs Principais
        $stmt = $pdo->prepare(aplicarFiltroPeriodo("SELECT COUNT(*) FROM leads WHERE LOWER(estado) IN ('ganha', 'ganho')", $where_clauses));
        $stmt->execute($params);
        $leads_ganhas = $stmt->fetchColumn();

        $stmt = $pdo->prepare(aplicarFiltroPeriodo("SELECT COUNT(*) FROM leads", $where_clauses));
        $stmt->execute($params);
        $total_leads = $stmt->fetchColumn();

        $stmt = $pdo->prepare(aplicarFiltroPeriodo("SELECT COUNT(*) FROM leads WHERE LOWER(estado) IN ('perdida', 'perdido')", $where_clauses));
        $stmt->execute($params);
        $leads_perdidas = $stmt->fetchColumn();

        $stmt = $pdo->prepare(aplicarFiltroPeriodo("SELECT COUNT(*) FROM leads WHERE LOWER(estado) = 'proposta enviada'", $where_clauses));
        $stmt->execute($params);
        $propostas_enviadas = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM leads WHERE id_responsavel IS NULL AND LOWER(estado) NOT IN ('ganha','perdida','ganho','perdido')");
        $sem_acompanhamento = $stmt->fetchColumn();

        if ($total_leads > 0) {
            $taxa_conversao = round(($leads_ganhas / $total_leads) * 100, 1);
        }

        $stmt_estados_cfg = $pdo->query("SELECT valor FROM crm_opcoes WHERE tipo = 'estado' ORDER BY ordem ASC, id ASC");
        $estados_configurados = $stmt_estados_cfg->fetchAll(PDO::FETCH_COLUMN);
        if (empty($estados_configurados)) {
            $estados_configurados = ['Nova'];
        }
        $pipeline_funil = array_fill_keys($estados_configurados, 0);

        // Distribuição por Estado (Gráfico de Barras)
        $stmt_estado = $pdo->prepare(aplicarFiltroPeriodo("SELECT estado, COUNT(*) as total FROM leads", $where_clauses, "GROUP BY estado"));
        $stmt_estado->execute($params);
        $por_estado  = $stmt_estado->fetchAll(PDO::FETCH_ASSOC);

        // Preenchimento do Pipeline Dinâmico
        foreach ($por_estado as $est) {
            $nome_estado = trim($est['estado']);
            foreach ($pipeline_funil as $etapa => $qtd) {
                if (mb_strtolower($nome_estado, 'UTF-8') === mb_strtolower($etapa, 'UTF-8')) {
                    $pipeline_funil[$etapa] += (int)$est['total'];
                    break;
                }
            }
        }

        // Distribuição por Origem (Gráfico Circular)
        $stmt_origens_cfg = $pdo->query("SELECT valor FROM crm_opcoes WHERE tipo = 'origem' ORDER BY ordem ASC, id ASC");
        $contagem_origem  = array_fill_keys($stmt_origens_cfg->fetchAll(PDO::FETCH_COLUMN), 0);

        $stmt_origem = $pdo->prepare(aplicarFiltroPeriodo("SELECT origem, COUNT(*) as total FROM leads", $where_clauses, "GROUP BY origem"));
        $stmt_origem->execute($params);
        foreach ($stmt_origem->fetchAll(PDO::FETCH_ASSOC) as $linha_origem) {
            $nome = trim($linha_origem['origem']);
            if (!isset($contagem_origem[$nome])) {
                $contagem_origem[$nome] = 0;
            }
            $contagem_origem[$nome] += (int)$linha_origem['total'];
        }
        arsort($contagem_origem); // mantém o gráfico ordenado da origem com mais leads para a com menos

        $por_origem = [];
        foreach ($contagem_origem as $nome => $total) {
            $por_origem[] = ['origem' => $nome, 'total' => $total];
        }

        // Oportunidades Quentes
        $stmt_q = $pdo->prepare(aplicarFiltroPeriodo(
            "SELECT id_lead, nome_contacto, empresa, valor_potencial, prioridade, estado 
            FROM leads 
            WHERE LOWER(prioridade) IN ('alta', 'urgente') 
            AND LOWER(estado) NOT IN ('ganha', 'perdida', 'ganho', 'perdido', 'arquivada')", 
            $where_clauses, 
            "ORDER BY prioridade DESC, valor_potencial DESC"
        ));
        $stmt_q->execute($params);
        $lista_quentes = $stmt_q->fetchAll(PDO::FETCH_ASSOC);

        // O que Fazer Hoje
        $data_hoje_fim = date('Y-m-d 23:59:59');
        $stmt_t = $pdo->prepare("SELECT t.*, l.nome_contacto, l.empresa 
                                 FROM tarefas t 
                                 INNER JOIN leads l ON t.id_lead = l.id_lead 
                                 WHERE t.estado = 'Pendente' 
                                   AND t.data_limite <= :hoje_fim 
                                 ORDER BY t.data_limite ASC");
        $stmt_t->execute([':hoje_fim' => $data_hoje_fim]);
        $lista_tarefas_hoje = $stmt_t->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        echo "<div class='p-4 mb-6 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40 rounded-xl border border-red-200 dark:border-red-900/50'>
            <strong>Erro do Sistema:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

$estados_labels = json_encode(array_keys($pipeline_funil));
$estados_data   = json_encode(array_values($pipeline_funil));
$paleta_base = ['#6366f1', '#0ea5e9', '#14b8a6', '#eab308', '#f97316', '#22c55e', '#f43f5e', '#a855f7', '#ec4899', '#84cc16'];
$estados_cores = [];
foreach (array_keys($pipeline_funil) as $i => $etapa) {
    $estados_cores[] = $paleta_base[$i % count($paleta_base)];
}
$estados_cores = json_encode($estados_cores);

$origens_labels = json_encode(array_column($por_origem, 'origem'));
$origens_data   = json_encode(array_column($por_origem, 'total'));
$origens_cores = [];
foreach (array_column($por_origem, 'origem') as $i => $nome) {
    $origens_cores[] = $paleta_base[$i % count($paleta_base)];
}
$origens_cores = json_encode($origens_cores);
?>

<div class="mb-8 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4">
    <div>
        <h1 class="text-2xl font-black tracking-tight" style="background: linear-gradient(to right, #818cf8, #22d3ee); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
            Dashboard
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Acompanhe o desempenho do seu negócio em tempo real.</p>
    </div>

    <form method="GET" id="form-filtros" class="flex flex-wrap items-center gap-3 bg-white dark:bg-slate-900/40 p-3 rounded-xl border border-slate-200 dark:border-slate-800/80 shadow-sm w-full lg:w-auto backdrop-blur-sm">
        <?php if (isset($_GET['v'])): ?>
            <input type="hidden" name="v" value="<?= htmlspecialchars($_GET['v']) ?>">
        <?php endif; ?>

        <div class="flex items-center gap-2">
            <label class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-wider">Período</label>
            <select id="periodo" name="periodo" onchange="atualizarInterfacePeriodo(true)" class="text-xs bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800/80 rounded-lg px-2.5 py-1.5 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-medium cursor-pointer transition-all">
                <option value="este_mes" <?= $periodo === 'este_mes' ? 'selected' : '' ?>>Este Mês</option>
                <option value="mes_anterior" <?= $periodo === 'mes_anterior' ? 'selected' : '' ?>>Mês Passado</option>
                <option value="ultimos_30_dias" <?= $periodo === 'ultimos_30_dias' ? 'selected' : '' ?>>Últimos 30 dias</option>
                <option value="ultimos_6_meses" <?= $periodo === 'ultimos_6_meses' ? 'selected' : '' ?>>Últimos 6 meses</option>
                <option value="este_ano" <?= $periodo === 'este_ano' ? 'selected' : '' ?>>Este Ano</option>
                <option value="ultimo_ano" <?= $periodo === 'ultimo_ano' ? 'selected' : '' ?>>Último Ano (1 ano)</option>
                <option value="ultimos_2_anos" <?= $periodo === 'ultimos_2_anos' ? 'selected' : '' ?>>Últimos 2 Anos</option>
                <option value="tudo" <?= $periodo === 'tudo' ? 'selected' : '' ?>>Sempre (Ver Tudo)</option>
                <option value="personalizado" <?= $periodo === 'personalizado' ? 'selected' : '' ?>>Personalizado</option>
            </select>
        </div>

        <div id="container-datas-manuais" class="flex flex-wrap items-center gap-3 transition-all duration-200">
            <div class="flex items-center gap-2">
                <label class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-wider">De</label>
                <input type="date" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" class="text-xs bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800/80 rounded-lg px-2.5 py-1.5 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:opacity-50">
            </div>

            <div class="flex items-center gap-2">
                <label class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-wider">Até</label>
                <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" min="<?= htmlspecialchars($data_inicio) ?>" class="text-xs bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800/80 rounded-lg px-2.5 py-1.5 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:opacity-50">
            </div>
        </div>

        <div class="flex items-center gap-2 ml-auto lg:ml-0">
            <button type="submit" id="btn-filtrar" class="hidden text-xs font-bold uppercase tracking-wider bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-1.5 rounded-lg transition shadow-sm animate-fade-in">
                Filtrar
            </button>
        </div>
    </form>
</div>

<?php if (!empty($erro_data)): ?>
    <div class="p-4 mb-6 text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/40 rounded-xl border border-red-200 dark:border-red-900/50 flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
        <span><strong>Erro de Filtro:</strong> <?= htmlspecialchars($erro_data) ?></span>
    </div>
<?php endif; ?>

<div class="grid grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4 mb-6">
    <div class="p-4 bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-xl flex flex-col gap-2 backdrop-blur-sm shadow-xl shadow-slate-200/50 dark:shadow-black/5">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Total Leads</p>
            <div class="p-1.5 bg-blue-50 dark:bg-blue-500/10 rounded-lg text-blue-600 dark:text-blue-400"><i data-lucide="layers" class="w-4 h-4"></i></div>
        </div>
        <h3 class="text-3xl font-black text-blue-600 dark:text-blue-400"><?= (int)$total_leads ?></h3>
    </div>

    <div class="p-4 bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-xl flex flex-col gap-2 backdrop-blur-sm shadow-xl shadow-slate-200/50 dark:shadow-black/5">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Ganhas</p>
            <div class="p-1.5 bg-emerald-50 dark:bg-emerald-500/10 rounded-lg text-emerald-600 dark:text-emerald-400"><i data-lucide="trophy" class="w-4 h-4"></i></div>
        </div>
        <h3 class="text-3xl font-black text-emerald-600 dark:text-emerald-400"><?= (int)$leads_ganhas ?></h3>
    </div>

    <div class="p-4 bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-xl flex flex-col gap-2 backdrop-blur-sm shadow-xl shadow-slate-200/50 dark:shadow-black/5">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Perdidas</p>
            <div class="p-1.5 bg-rose-50 dark:bg-rose-500/10 rounded-lg text-rose-600 dark:text-rose-400"><i data-lucide="x-circle" class="w-4 h-4"></i></div>
        </div>
        <h3 class="text-3xl font-black text-rose-600 dark:text-rose-400"><?= (int)$leads_perdidas ?></h3>
    </div>

    <div class="p-4 bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-xl flex flex-col gap-2 backdrop-blur-sm shadow-xl shadow-slate-200/50 dark:shadow-black/5">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Propostas</p>
            <div class="p-1.5 bg-cyan-50 dark:bg-cyan-500/10 rounded-lg text-cyan-600 dark:text-cyan-400"><i data-lucide="file-text" class="w-4 h-4"></i></div>
        </div>
        <h3 class="text-3xl font-black text-cyan-600 dark:text-cyan-400"><?= (int)$propostas_enviadas ?></h3>
    </div>

    <div class="p-4 bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-xl flex flex-col gap-2 backdrop-blur-sm shadow-xl shadow-slate-200/50 dark:shadow-black/5">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Conversão</p>
            <div class="p-1.5 bg-violet-50 dark:bg-violet-500/10 rounded-lg text-violet-600 dark:text-violet-400"><i data-lucide="trending-up" class="w-4 h-4"></i></div>
        </div>
        <h3 class="text-3xl font-black text-violet-600 dark:text-violet-400"><?= $taxa_conversao ?>%</h3>
    </div>

    <div class="p-4 bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-xl flex flex-col gap-2 backdrop-blur-sm shadow-xl shadow-slate-200/50 dark:shadow-black/5">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Sem Gestor</p>
            <div class="p-1.5 bg-amber-50 dark:bg-amber-500/10 rounded-lg text-amber-600 dark:text-amber-400"><i data-lucide="user-x" class="w-4 h-4"></i></div>
        </div>
        <h3 class="text-3xl font-black text-amber-600 dark:text-amber-400"><?= (int)$sem_acompanhamento ?></h3>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="p-5 bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-xl backdrop-blur-sm shadow-sm">
        <h3 class="text-sm font-bold text-slate-500 dark:text-white mb-4 flex items-center gap-2">
            <i data-lucide="bar-chart-2" class="w-4 h-4 text-indigo-500 dark:text-indigo-400"></i> Leads por Estado
        </h3>
        <div class="relative w-full h-[200px]">
            <canvas id="grafico_estado"></canvas>
        </div>
    </div>

    <div class="p-5 bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-xl backdrop-blur-sm shadow-sm">
        <h3 class="text-sm font-bold text-slate-500 dark:text-white mb-4 flex items-center gap-2">
            <i data-lucide="pie-chart" class="w-4 h-4 text-cyan-500 dark:text-cyan-400"></i> Leads por Origem
        </h3>
        <?php if (empty($por_origem)): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400 italic text-center py-8">Sem dados para representação gráfica neste período.</p>
        <?php else: ?>
            <canvas id="grafico_origem" style="max-height: 200px;"></canvas>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="p-5 bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-xl backdrop-blur-sm shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-slate-500 dark:text-white mb-4 flex items-center gap-2">
                <i data-lucide="flame" class="w-4 h-4 text-orange-500"></i> Oportunidades Quentes
            </h3>
            <a href="?v=leads" class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium transition flex items-center gap-1 bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/60 px-2.5 py-1 rounded-xl shadow-sm">
                Ver Leads <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-indigo-500 dark:text-indigo-400"></i>
            </a>
        </div>
        <div class="space-y-3">
            <?php if (empty($lista_quentes)): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400 italic text-center py-6">Nenhuma lead de alta prioridade encontrada neste período.</p>
            <?php else: ?>
                <?php foreach ($lista_quentes as $q): ?>
                    <a href="?v=leads&abrir_lead_id=<?= (int)$q['id_lead'] ?>"
                       class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800/50 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-950/70 hover:border-indigo-400/60 dark:hover:border-indigo-500/50 transition cursor-pointer">
                        <div class="min-w-0 flex-1 pr-2">
                            <p class="text-sm font-semibold text-slate-800 dark:text-white truncate"><?= htmlspecialchars($q['empresa'] ?: 'Contacto Direto') ?></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                                <?= htmlspecialchars($q['nome_contacto'] ?: 'Registo Individual') ?> • 
                                <span class="text-indigo-600 dark:text-indigo-400 font-medium"><?= htmlspecialchars($q['estado']) ?></span>
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                <?= $q['valor_potencial'] ? '€' . number_format($q['valor_potencial'], 2, ',', '.') : '—' ?>
                            </p>
                            <span class="inline-block text-[9px] font-black uppercase px-2 py-0.5 rounded <?= strtolower($q['prioridade']) === 'urgente' ? 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/20 dark:text-rose-400 border dark:border-rose-500/30 animate-pulse' : 'bg-orange-50 text-orange-600 border-orange-200 dark:bg-orange-500/20 dark:text-orange-400 border dark:border-orange-500/30' ?>">
                                <?= htmlspecialchars($q['prioridade']) ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="p-5 bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800/80 rounded-xl backdrop-blur-sm shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-bold text-slate-500 dark:text-white mb-4 flex items-center gap-2">
                <i data-lucide="calendar-clock" class="w-4 h-4 text-amber-500 dark:text-amber-400"></i> O que fazer hoje
            </h3>
            <a href="?v=tarefas" class="text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium transition flex items-center gap-1 bg-slate-100 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700/60 px-2.5 py-1 rounded-xl shadow-sm">
                Ver Tarefas <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-amber-500 dark:text-amber-400"></i>
            </a>
        </div>
        <div class="space-y-3">
            <?php if (empty($lista_tarefas_hoje)): ?>
                <p class="text-sm text-slate-500 dark:text-slate-400 italic text-center py-6">Excelente! Não tens tarefas pendentes para o dia de hoje.</p>
            <?php else: ?>
                <?php foreach ($lista_tarefas_hoje as $th): 
                    $atrasada = ($th['data_limite'] < $data_atual);
                ?>
                    <a href="?v=tarefas&abrir_tarefa_id=<?= (int)$th['id_tarefa'] ?>"
                       class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-950/40 border border-slate-200 dark:border-slate-800/50 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-950/70 hover:border-indigo-400/60 dark:hover:border-indigo-500/50 transition cursor-pointer">
                        <div class="min-w-0 flex-1 pr-3">
                            <p class="text-sm font-semibold text-slate-800 dark:text-white truncate"><?= htmlspecialchars($th['empresa']) ?></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate"><?= htmlspecialchars($th['nome_contacto']) ?></p> 
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs font-mono <?= $atrasada ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-slate-500 dark:text-slate-400' ?>">
                                <?= date('d/m H:i', strtotime($th['data_limite'])) ?>
                            </p>

                            <span class="inline-block text-[9px] font-bold uppercase px-1.5 py-0.5 rounded <?= $atrasada ? 'bg-rose-50 text-rose-600 border-rose-200 dark:bg-rose-500/10 dark:text-rose-400 border dark:border-rose-500/20 animate-pulse' : 'bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 border dark:border-blue-500/20' ?>">
                                <?= $atrasada ? 'Atrasada' : 'Pendente' ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    
const getChartGridColor = () => {
    return document.documentElement.classList.contains('dark') ? 'rgba(148,163,184,0.12)' : 'rgba(0,0,0,0.08)';
};
const getChartBorderColor = () => {
    return document.documentElement.classList.contains('dark') ? '#0f172a' : '#ffffff';
};

const ctxEstado = document.getElementById('grafico_estado');
const ctxOrigem = document.getElementById('grafico_origem');

const graficoEstado = new Chart(ctxEstado, {
    type: 'bar',
    data: {
        labels: <?= $estados_labels ?>,
        datasets: [{
            data: <?= $estados_data ?>,
            backgroundColor: <?= $estados_cores ?>,
            borderRadius: 8,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color: '#94a3b8', font: { size: 12 } }, grid: { color: getChartGridColor() } },
            y: { ticks: { color: '#94a3b8', font: { size: 12 }, stepSize: 1 }, grid: { color: getChartGridColor() }, beginAtZero: true }
        }
    }
});

let graficoOrigem = null;
<?php if (!empty($por_origem)): ?>
graficoOrigem = new Chart(ctxOrigem, {
    type: 'doughnut',
    data: {
        labels: <?= $origens_labels ?>,
        datasets: [{
            data: <?= $origens_data ?>,
            backgroundColor: <?= $origens_cores ?>,
            borderColor: getChartBorderColor(),
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { color: '#94a3b8', font: { size: 12, family: 'monospace' }, padding: 18 }
            }
        }
    }
});
<?php endif; ?>

function atualizarGraficosTema() {
    if (graficoOrigem) graficoOrigem.data.datasets[0].borderColor = getChartBorderColor();
    graficoEstado.options.scales.x.grid.color = getChartGridColor();
    graficoEstado.options.scales.y.grid.color = getChartGridColor();
    
    if (graficoOrigem) graficoOrigem.update('none');
    graficoEstado.update('none');
}

const observer = new MutationObserver(() => atualizarGraficosTema());
observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

const inputInicio = document.getElementById('data_inicio');
const inputFim = document.getElementById('data_fim');

if (inputInicio && inputFim) {
    function atualizarRegrasDataFim() {
        inputFim.min = inputInicio.value;
        
        if (inputFim.value && inputFim.value < inputInicio.value) {
            inputFim.value = inputInicio.value;
        }
    }
    
    inputInicio.addEventListener('change', atualizarRegrasDataFim);
}

function atualizarInterfacePeriodo(deveSubmeter = false) {
    const selectPeriodo = document.getElementById('periodo');
    const inputInic = document.getElementById('data_inicio');
    const inputF = document.getElementById('data_fim');
    const containerDatas = document.getElementById('container-datas-manuais');
    const btnFiltrar = document.getElementById('btn-filtrar');
    
    if (selectPeriodo.value === 'personalizado') {
        inputInic.disabled = false;
        inputF.disabled = false;
        containerDatas.style.opacity = '1';
        containerDatas.style.pointerEvents = 'auto';
        btnFiltrar.classList.remove('hidden');
    } else {
        inputInic.disabled = true;
        inputF.disabled = true;
        containerDatas.style.opacity = '0.3';
        containerDatas.style.pointerEvents = 'none';
        btnFiltrar.classList.add('hidden');
        
        if (deveSubmeter) {
            document.body.style.opacity = '0.5';
            document.body.style.transition = 'opacity 0.15s ease';
            
            document.getElementById('form-filtros').submit();
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    atualizarInterfacePeriodo(false);
});

// ── PERSISTÊNCIA DO FILTRO DE PERÍODO
(function () {
    const CHAVE_PERIODO     = 'dashboard_periodo';
    const CHAVE_DATA_INICIO = 'dashboard_data_inicio';
    const CHAVE_DATA_FIM    = 'dashboard_data_fim';

    const urlAtual = new URL(window.location.href);
    const temPeriodoNaUrl = urlAtual.searchParams.has('periodo');

    if (!temPeriodoNaUrl) {
        const periodoGuardado = localStorage.getItem(CHAVE_PERIODO);
        if (periodoGuardado) {
            urlAtual.searchParams.set('periodo', periodoGuardado);
            if (periodoGuardado === 'personalizado') {
                const diGuardado = localStorage.getItem(CHAVE_DATA_INICIO);
                const dfGuardado = localStorage.getItem(CHAVE_DATA_FIM);
                if (diGuardado) urlAtual.searchParams.set('data_inicio', diGuardado);
                if (dfGuardado) urlAtual.searchParams.set('data_fim', dfGuardado);
            }
            window.location.replace(urlAtual.href);
            return;
        }
    } else {
        const periodoAtualUrl = urlAtual.searchParams.get('periodo');
        localStorage.setItem(CHAVE_PERIODO, periodoAtualUrl);
        if (periodoAtualUrl === 'personalizado') {
            const diUrl = urlAtual.searchParams.get('data_inicio') || '';
            const dfUrl = urlAtual.searchParams.get('data_fim') || '';
            localStorage.setItem(CHAVE_DATA_INICIO, diUrl);
            localStorage.setItem(CHAVE_DATA_FIM, dfUrl);
        }
    }
})();
</script>