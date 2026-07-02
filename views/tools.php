<?php
// Segurança: só admins
if (!isset($_SESSION['user_id']) || $_SESSION['user_perfil'] !== 'admin') {
    echo '<div class="p-6 bg-rose-500/10 border border-rose-500/20 rounded-xl text-rose-400 text-sm">Acesso negado.</div>';
    return;
}

function garantirEstadosPredefinidos(PDO $pdo): void {
    $estados = [
        'Nova'              => 1,
        'Contactada'        => 2,
        'Proposta Enviada'  => 3,
        'Reunião Marcada'   => 4,
        'Em negociação'     => 5,
        'Ganha'             => 6,
        'Perdida'           => 7,
    ];
    foreach ($estados as $valor => $ordem) {
        $stmt = $pdo->prepare("SELECT id FROM crm_opcoes WHERE tipo = 'estado' AND valor = ?");
        $stmt->execute([$valor]);

        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO crm_opcoes (tipo, valor, ordem) VALUES ('estado', ?, ?)")->execute([$valor, $ordem]);
        }
    }
}

function contarUsoOpcao(PDO $pdo, string $tipo, string $valor): array {
    $leads = 0;
    $interacoes = 0;

    switch ($tipo) {
        case 'estado':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE estado = ?");
            $stmt->execute([$valor]);
            $leads = (int) $stmt->fetchColumn();
            break;
        case 'prioridade':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE prioridade = ?");
            $stmt->execute([$valor]);
            $leads = (int) $stmt->fetchColumn();
            break;
        case 'origem':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE origem = ?");
            $stmt->execute([$valor]);
            $leads = (int) $stmt->fetchColumn();
            break;
        case 'servicos':
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM leads WHERE servicos = ? OR servicos LIKE ? OR servicos LIKE ? OR servicos LIKE ?"
            );
            $stmt->execute([$valor, $valor . ',%', '%, ' . $valor . ',%', '%, ' . $valor]);
            $leads = (int) $stmt->fetchColumn();
            break;
        case 'interacao':
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM interacoes WHERE tipo = ?");
            $stmt->execute([$valor]);
            $interacoes = (int) $stmt->fetchColumn();
            break;
    }

    return ['leads' => $leads, 'interacoes' => $interacoes];
}

function limparUsoOpcao(PDO $pdo, string $tipo, string $valor): int {
    switch ($tipo) {
        case 'estado':
            $stmt = $pdo->prepare("UPDATE leads SET estado = 'Arquivada' WHERE estado = ?");
            $stmt->execute([$valor]);
            return $stmt->rowCount();
        case 'prioridade':
            $stmt = $pdo->prepare("UPDATE leads SET prioridade = '' WHERE prioridade = ?");
            $stmt->execute([$valor]);
            return $stmt->rowCount();
        case 'origem':
            $stmt = $pdo->prepare("UPDATE leads SET origem = '' WHERE origem = ?");
            $stmt->execute([$valor]);
            return $stmt->rowCount();
        case 'servicos':
            $stmt = $pdo->query("SELECT id_lead, servicos FROM leads WHERE servicos IS NOT NULL AND servicos != ''");
            $afetadas = 0;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $partes = array_map('trim', explode(',', $row['servicos']));
                $novas = array_values(array_filter($partes, fn($p) => $p !== $valor));
                if (count($novas) !== count($partes)) {
                    $pdo->prepare("UPDATE leads SET servicos = ? WHERE id_lead = ?")
                        ->execute([implode(', ', $novas), $row['id_lead']]);
                    $afetadas++;
                }
            }
            return $afetadas;
        case 'interacao':
            return 0;
        default:
            return 0;
    }
}

garantirEstadosPredefinidos($pdo);

// Ler mensagens da sessão
$mensagem      = $_SESSION['mensagem']      ?? '';
$tipo_mensagem = $_SESSION['tipo_mensagem'] ?? '';
unset($_SESSION['mensagem'], $_SESSION['tipo_mensagem']);

// Lidar com submissões (Adicionar, Apagar e Reordenar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verificar uso antes de apagar (AJAX)
    if (($_POST['action'] ?? '') === 'check_usage') {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');

        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT tipo, valor FROM crm_opcoes WHERE id = ?");
        $stmt->execute([$id]);
        $opcao = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$opcao) {
            echo json_encode(['status' => 'error', 'message' => 'Opção não encontrada.']);
            exit;
        }

        $uso = contarUsoOpcao($pdo, $opcao['tipo'], $opcao['valor']);
        echo json_encode([
            'status'      => 'success',
            'tipo'        => $opcao['tipo'],
            'valor'       => $opcao['valor'],
            'leads'       => $uso['leads'],
            'interacoes'  => $uso['interacoes'],
        ]);
        exit;
    }

    // 1. ADICIONAR OPÇÃO
    if (($_POST['action'] ?? '') === 'add') {
        $tipo  = $_POST['tipo']  ?? '';
        $valor = trim($_POST['valor'] ?? '');
        if (!empty($tipo) && !empty($valor)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO crm_opcoes (tipo, valor, ordem) VALUES (?, ?, 999)");
                $stmt->execute([$tipo, $valor]);
                $_SESSION['mensagem']      = "Opção adicionada com sucesso!";
                $_SESSION['tipo_mensagem'] = "success";
            } catch (PDOException $e) {
                $_SESSION['mensagem']      = "Erro: " . $e->getMessage();
                $_SESSION['tipo_mensagem'] = "danger";
            }
        }
        echo '<script>window.location.href="index.php?v=tools";</script>';
        exit;
    }

    // 2. APAGAR OPÇÃO
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt_opcao = $pdo->prepare("SELECT tipo, valor FROM crm_opcoes WHERE id = ?");
                $stmt_opcao->execute([$id]);
                $opcao = $stmt_opcao->fetch(PDO::FETCH_ASSOC);

                if ($opcao) {
                    $uso = contarUsoOpcao($pdo, $opcao['tipo'], $opcao['valor']);
                    $bloqueia = ($opcao['tipo'] !== 'interacao') && ($uso['leads'] > 0 || $uso['interacoes'] > 0);

                    if ($bloqueia) {
                        $partes = [];
                        if ($uso['leads'] > 0)      $partes[] = "{$uso['leads']} lead(s)";
                        if ($uso['interacoes'] > 0) $partes[] = "{$uso['interacoes']} interação(ões)";
                        $_SESSION['mensagem']      = "Não é possível eliminar \"{$opcao['valor']}\": ainda existem " . implode(' e ', $partes) . " a usar esta opção.";
                        $_SESSION['tipo_mensagem'] = "danger";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM crm_opcoes WHERE id = ?");
                        $stmt->execute([$id]);
                        $_SESSION['mensagem']      = "Opção removida com sucesso!";
                        $_SESSION['tipo_mensagem'] = "success";
                    }
                }
            } catch (PDOException $e) {
                $_SESSION['mensagem']      = "Erro ao remover: " . $e->getMessage();
                $_SESSION['tipo_mensagem'] = "danger";
            }
        }
        echo '<script>window.location.href="index.php?v=tools";</script>';
        exit;
    }

    // 3. ATUALIZAR ORDEM VIA DRAG & DROP (Chamada AJAX)
    if (($_POST['action'] ?? '') === 'update_order') {
        if (ob_get_length()) ob_clean();

        header('Content-Type: application/json');
        $order = json_decode($_POST['order'] ?? '[]', true);

        if (is_array($order) && !empty($order)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE crm_opcoes SET ordem = ? WHERE id = ?");
                foreach ($order as $index => $id) {
                    $stmt->execute([$index + 1, (int) $id]);
                }
                $pdo->commit();
                echo json_encode(['status' => 'success']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
        }
        exit;
    }
}

// Agrupar as opções existentes por tipo (Ordenadas por 'ordem' e depois por 'valor')
$categorias = [
    'origem'     => ['titulo' => 'Origens das Leads', 'icon' => 'compass', 'items' => []],
    'servicos'  => ['titulo' => 'Serviços', 'icon' => 'target', 'items' => []],
    'estado'     => ['titulo' => 'Estados das Leads', 'icon' => 'git-commit', 'items' => []],
    'prioridade' => ['titulo' => 'Níveis de Prioridade', 'icon' => 'alert-circle', 'items' => []],
];

// NOVA: 2ª Categoria - Gestão de Interações
$categorias_interacoes = [
    'interacao' => ['titulo' => 'Tipos de Interação', 'icon' => 'message-square', 'items' => []]
];

// O query existente precisa agora de distribuir também pela nova categoria
$stmt = $pdo->query("SELECT * FROM crm_opcoes ORDER BY ordem ASC, valor ASC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Contagem de uso (leads/interações) para mostrar diretamente na lista, sem precisar de abrir o modal
    $uso = contarUsoOpcao($pdo, $row['tipo'], $row['valor']);
    $row['uso_total'] = $uso['leads'] + $uso['interacoes'];

    if (isset($categorias[$row['tipo']])) {
        $categorias[$row['tipo']]['items'][] = $row;
    } elseif (isset($categorias_interacoes[$row['tipo']])) {
        $categorias_interacoes[$row['tipo']]['items'][] = $row;
    }
}
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<div class="w-full">
    <div class="mb-8">
        <h1 class="text-2xl font-black tracking-tight bg-gradient-to-r from-indigo-400 to-cyan-400 bg-clip-text text-transparent">Configurações do Sistema</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">Gerencia e reordena os campos dinâmicos dos formulários e do pipeline.</p>
    </div>

    <?php if (!empty($mensagem)): ?>
        <div id="alerta-tools" class="mb-6 p-4 rounded-xl text-sm border <?php echo $tipo_mensagem === 'success' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-rose-500/10 border-rose-500/20 text-rose-400'; ?>">
            <div class="flex items-center gap-2">
                <i data-lucide="<?php echo $tipo_mensagem === 'success' ? 'check-circle' : 'alert-triangle'; ?>" class="w-4 h-4"></i>
                <span><?= htmlspecialchars($mensagem) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($categorias as $key => $cat): ?>
            <div class="bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-300 dark:border-slate-800/80 rounded-2xl p-5 backdrop-blur-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2.5 pb-3 border-b border-slate-300 dark:border-slate-800/60 mb-4">
                        <div class="p-2 bg-indigo-600/10 rounded-lg text-indigo-400">
                            <i data-lucide="<?= $cat['icon'] ?>" class="w-4 h-4"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200"><?= $cat['titulo'] ?></h3>
                    </div>

                    <ul class="sortable-list space-y-2 mb-4 min-h-[50px]" data-tipo="<?= $key ?>">
                        <?php if (empty($cat['items'])): ?>
                            <div class="text-xs text-slate-500 dark:text-slate-400 italic p-3 border border-dashed border-slate-300 dark:border-slate-800 rounded-xl text-center de-passagem">
                                Nenhuma opção configurada.
                            </div>
                        <?php else: ?>
                            <?php foreach ($cat['items'] as $item): ?>
                                <li data-id="<?= $item['id'] ?>" class="flex justify-between items-center p-3 bg-slate-50 dark:bg-slate-950/60 border border-slate-300 dark:border-slate-800/60 hover:border-slate-400 dark:hover:border-slate-700/80 rounded-xl text-xs transition group">
                                    <div class="flex items-center gap-3">
                                        <i data-lucide="grip-vertical" class="w-4 h-4 text-slate-600 cursor-grab active:cursor-grabbing hover:text-slate-600 dark:text-slate-400 transition"></i>
                                        <span class="text-slate-700 dark:text-slate-300 font-medium"><?= htmlspecialchars($item['valor']) ?></span>
                                        <?php if ($item['uso_total'] > 0): ?>
                                            <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded-md" title="Em utilização"><?= $item['uso_total'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <form action="" method="POST" class="form-apagar-opcao">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="p-1.5 text-slate-500 dark:text-slate-400 hover:text-rose-400 hover:bg-rose-500/5 rounded-lg transition opacity-0 group-hover:opacity-100">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <form action="" method="POST" class="flex gap-2 mt-2 pt-3 border-t border-slate-300 dark:border-slate-800/40">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="tipo" value="<?= $key ?>">
                    
                    <input type="text" name="valor" placeholder="Nova opção..." required 
                           class="flex-1 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 transition">
                    
                    <button type="submit" class="p-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl transition flex items-center justify-center shadow-lg shadow-indigo-600/10">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="border-t border-slate-300 dark:border-slate-800/60 my-10"></div>
        <div class="mb-6">
            <h2 class="text-2xl font-black tracking-tight bg-gradient-to-r from-emerald-400 to-cyan-400 bg-clip-text text-transparent">
                Gestão de Interações
            </h2>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                Gerencia, adiciona e reordena os tipos de contacto e interações com as leads.
            </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
        <?php foreach ($categorias_interacoes as $key => $cat): ?>
            <div class="bg-white dark:bg-slate-900/50 border border-slate-200 dark:border-slate-300 dark:border-slate-800/80 rounded-2xl p-5 backdrop-blur-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2.5 pb-3 border-b border-slate-300 dark:border-slate-800/60 mb-4">
                        <div class="p-2 bg-emerald-600/10 rounded-lg text-emerald-400">
                            <i data-lucide="<?= $cat['icon'] ?>" class="w-4 h-4"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-700 dark:text-slate-200"><?= $cat['titulo'] ?></h3>
                    </div>

                    <ul class="sortable-list space-y-2 mb-4 min-h-[50px]" data-tipo="<?= $key ?>">
                        <?php if (empty($cat['items'])): ?>
                            <div class="text-xs text-slate-500 dark:text-slate-400 italic p-3 border border-dashed border-slate-300 dark:border-slate-800 rounded-xl text-center">
                                Ex: Chamada, E-mail, Reunião, Proposta...
                            </div>
                        <?php else: ?>
                            <?php foreach ($cat['items'] as $item): ?>
                                <li data-id="<?= $item['id'] ?>" class="flex justify-between items-center p-3 bg-slate-50 dark:bg-slate-950/60 border border-slate-300 dark:border-slate-800/60 hover:border-slate-400 dark:hover:border-slate-700/80 rounded-xl text-xs transition group">
                                    <div class="flex items-center gap-3">
                                        <i data-lucide="grip-vertical" class="w-4 h-4 text-slate-600 cursor-grab active:cursor-grabbing hover:text-slate-600 dark:text-slate-400 transition"></i>
                                        <span class="text-slate-700 dark:text-slate-300 font-medium"><?= htmlspecialchars($item['valor']) ?></span>
                                        <?php if ($item['uso_total'] > 0): ?>
                                            <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-1.5 py-0.5 rounded-md" title="Em utilização"><?= $item['uso_total'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <form action="" method="POST" class="form-apagar-opcao">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="p-1.5 text-slate-500 dark:text-slate-400 hover:text-rose-400 hover:bg-rose-500/5 rounded-lg transition opacity-0 group-hover:opacity-100">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <form action="" method="POST" class="flex gap-2 mt-2 pt-3 border-t border-slate-300 dark:border-slate-800/40">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="tipo" value="<?= $key ?>">
                    
                    <input type="text" name="valor" placeholder="Nova interação (ex: Whatsapp)..." required 
                           class="flex-1 bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-emerald-500 transition">
                    
                    <button type="submit" class="p-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl transition flex items-center justify-center shadow-lg shadow-emerald-600/10">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

<style>
    @keyframes modalIn { 
        from { opacity: 0; transform: scale(0.96) translateY(8px); } 
        to { opacity: 1; transform: scale(1) translateY(0); } 
    }
</style>
<!-- Modal de Confirmação para Apagar Opção -->
<div id="modal-apagar-opcao" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm bg-black/75" onclick="fecharModalForaOpcao(event, 'modal-apagar-opcao')">
    <div class="relative bg-white dark:bg-[#0b0f19] border border-slate-200 dark:border-slate-700/60 rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden" style="animation: modalIn .2s ease-out;">
        <div id="modal-apagar-opcao-barra" class="h-1 w-full bg-gradient-to-r from-rose-600 to-rose-400"></div>
        <div class="p-6 space-y-5">
            <div class="flex items-start gap-4">
                <div id="modal-apagar-opcao-icone-wrap" class="bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 p-2.5 rounded-xl shrink-0">
                    <i id="modal-apagar-opcao-icone" data-lucide="trash-2" class="w-5 h-5 text-rose-500 dark:text-rose-400"></i>
                </div>
                <div>
                    <h3 id="modal-apagar-opcao-titulo" class="text-sm font-bold text-slate-800 dark:text-white">Apagar opção</h3>
                    <div id="modal-apagar-opcao-msg" class="text-xs text-slate-600 dark:text-slate-400 mt-2">
                        <!-- O texto e avisos serão injetados aqui via JS -->
                    </div>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="button" onclick="fecharModalOpcao('modal-apagar-opcao')" class="flex-1 py-2.5 rounded-xl border border-slate-300 dark:border-slate-800 text-slate-600 dark:text-slate-400 text-xs font-medium hover:bg-slate-100 dark:hover:bg-slate-900 transition">Cancelar</button>
                <button type="button" id="btn-confirmar-apagar-opcao" class="flex-1 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Apagar
                </button>
                <a href="#" id="btn-ver-leads-opcao" class="hidden flex-1 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-white font-bold text-xs flex items-center justify-center gap-1.5 transition">
                    <i data-lucide="search" class="w-3.5 h-3.5"></i> Ver Leads
                </a>
            </div>
        </div>
    </div>
</div>
<script>
    const LABELS_TIPO_OPCAO = {
        origem: 'origem',
        servicos: 'serviço',
        estado: 'estado',
        prioridade: 'prioridade',
        interacao: 'tipo de interação'
    };

    let formParaApagar = null;

    function fecharModalOpcao(id) {
        document.getElementById(id).classList.add('hidden');
        formParaApagar = null;
    }

    function fecharModalForaOpcao(event, id) {
        const modal = document.getElementById(id);
        if (event.target === modal || event.target.classList.contains('backdrop-blur-sm') || event.target.classList.contains('bg-black/75')) {
            fecharModalOpcao(id);
        }
    }

    async function abrirModalApagarOpcao(form) {
        const id = form.querySelector('input[name="id"]')?.value;
        if (!id) return;

        formParaApagar = form;

        let data = { valor: 'esta opção', tipo: '', leads: 0, interacoes: 0 };
        try {
            const res = await fetch('index.php?v=tools', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'check_usage', id })
            });
            const json = await res.json();
            if (json.status === 'success') data = json;
        } catch (e) {
            console.error(e);
        }

        const tipoLabel = LABELS_TIPO_OPCAO[data.tipo] || 'atributo';
        const avisos = [];

        if (data.leads > 0) avisos.push(`Existem <strong>${data.leads} lead(s)</strong> com esta opção.`);
        if (data.interacoes > 0) avisos.push(`Existem <strong>${data.interacoes} interação(ões)</strong> com este tipo.`);

        const bloqueado = avisos.length > 0 && data.tipo !== 'interacao';
        let htmlMsg = '';

        // Elementos que mudam de aparência consoante o estado bloqueado/livre
        const btnConfirmar  = document.getElementById('btn-confirmar-apagar-opcao');
        const btnVerLeads   = document.getElementById('btn-ver-leads-opcao');
        const barra         = document.getElementById('modal-apagar-opcao-barra');
        const iconeWrap     = document.getElementById('modal-apagar-opcao-icone-wrap');
        const titulo        = document.getElementById('modal-apagar-opcao-titulo');

        function definirIcone(nomeIcone, classeIcone) {
            iconeWrap.innerHTML = `<i id="modal-apagar-opcao-icone" data-lucide="${nomeIcone}" class="${classeIcone}"></i>`;
        }

        // Mapa entre o tipo de opção e o parâmetro de filtro usado em leads.php
        const FILTRO_LEADS_POR_TIPO = {
            estado:     'f_estado',
            prioridade: 'f_prioridade',
            origem:     'f_origem',
            servicos:   'f_servico',
        };
        const filtroParam = FILTRO_LEADS_POR_TIPO[data.tipo];

        if (bloqueado) {
            htmlMsg += `<div class="space-y-2">`;
            avisos.forEach(aviso => htmlMsg += `<p class="flex items-start gap-1.5"><i data-lucide="alert-triangle" class="w-3.5 h-3.5 shrink-0 mt-0.5 text-amber-500 dark:text-amber-400"></i><span>${aviso}</span></p>`);
            htmlMsg += `</div>`;

            barra.className = 'h-1 w-full bg-gradient-to-r from-amber-500 to-amber-300';
            iconeWrap.className = 'bg-amber-50 dark:bg-amber-500/10 border border-amber-100 dark:border-amber-500/20 p-2.5 rounded-xl shrink-0';
            definirIcone('alert-triangle', 'w-5 h-5 text-amber-500 dark:text-amber-400');
            titulo.textContent = 'Opção em utilização';

            btnConfirmar.classList.add('hidden');
            if (filtroParam && data.leads > 0) {
                btnVerLeads.href = `index.php?v=leads&${filtroParam}=${encodeURIComponent(data.valor)}`;
                btnVerLeads.classList.remove('hidden');
            } else {
                btnVerLeads.classList.add('hidden');
            }
        } else {
            htmlMsg += `<p>Tens a certeza que pretendes eliminar a opção <strong class="text-slate-800 dark:text-slate-200">"${data.valor}"</strong>?</p>`;

            if (data.tipo === 'interacao' && data.interacoes > 0) {
                htmlMsg += `<div class="mt-3 p-2 bg-slate-500/10 border border-slate-500/20 rounded-xl text-slate-600 dark:text-slate-400 flex items-start gap-1.5"><i data-lucide="info" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i><span>O histórico não será afetado.</span></div>`;
            }

            barra.className = 'h-1 w-full bg-gradient-to-r from-rose-600 to-rose-400';
            iconeWrap.className = 'bg-rose-50 dark:bg-rose-500/10 border border-rose-100 dark:border-rose-500/20 p-2.5 rounded-xl shrink-0';
            definirIcone('trash-2', 'w-5 h-5 text-rose-500 dark:text-rose-400');
            titulo.textContent = 'Apagar opção';

            btnConfirmar.classList.remove('hidden');
            btnVerLeads.classList.add('hidden');
        }

        // Injeta o HTML e ativa os ícones
        document.getElementById('modal-apagar-opcao-msg').innerHTML = htmlMsg;
        if (typeof lucide !== 'undefined') lucide.createIcons();
        
        // Exibe o Modal
        document.getElementById('modal-apagar-opcao').classList.remove('hidden');
    }

    // Interceta o clique na reciclagem e abre o modal em vez do alerta do browser
    document.querySelectorAll('.form-apagar-opcao').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            abrirModalApagarOpcao(this);
        });
    });

    // Processa a submissão real do formulário quando o botão "Apagar" do modal é clicado
    document.getElementById('btn-confirmar-apagar-opcao').addEventListener('click', function() {
        if (formParaApagar && !this.disabled) {
            HTMLFormElement.prototype.submit.call(formParaApagar);
        }
    });

    // Restaurar o foco na caixa que estavas a editar
    const lastTipo = sessionStorage.getItem('lastEditedTipo');
    if (lastTipo) {
        sessionStorage.removeItem('lastEditedTipo'); // Limpa logo a memória
        
        // Dá um pequeno atraso de 150ms para garantir que o layout renderizou antes de deslizar
        setTimeout(() => {
            const targetList = document.querySelector(`ul[data-tipo="${lastTipo}"]`);
            if (targetList) {
                // Desliza suavemente até a caixa ficar no centro do ecrã
                targetList.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 150);
    }

    // Detetar em que caixa estás a mexer quando clicas em Adicionar ou Apagar
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            let tipoParaGuardar = null;
            
            // Se for o formulário de Adicionar, pega no input escondido "tipo"
            const inputTipo = this.querySelector('input[name="tipo"]');
            if (inputTipo) {
                tipoParaGuardar = inputTipo.value;
            } else {
                // Se for o botão de Apagar, procura a tabela "pai" para saber onde estavas
                const ulPai = this.closest('ul[data-tipo]');
                if (ulPai) {
                    tipoParaGuardar = ulPai.dataset.tipo;
                }
            }

            if (tipoParaGuardar) {
                sessionStorage.setItem('lastEditedTipo', tipoParaGuardar);
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
    // Auto-esconder o alerta de feedback após 4 segundos
    const alerta = document.getElementById('alerta-tools');
    if (alerta) {
        setTimeout(() => {
            alerta.style.transition = 'opacity 0.5s ease';
            alerta.style.opacity = '0';
            setTimeout(() => alerta.remove(), 500);
        }, 4000);
    }

    // Inicializar o SortableJS para cada lista (.sortable-list)
    document.querySelectorAll('.sortable-list').forEach(lista => {
        new Sortable(lista, {
            animation: 180,
            handle: '[data-lucide="grip-vertical"]', // O item só arrasta se clicares no ícone dos pontinhos
            ghostClass: 'bg-indigo-600/5',
            chosenClass: 'border-indigo-500/30',
            onEnd: function (evt) {
                // Mapeia todos os data-id da lista na nova ordem
                const idsOrdenados = [...evt.to.querySelectorAll('li[data-id]')].map(li => li.dataset.id);
                
                // Envia a nova ordenação ao servidor via POST
                fetch('index.php?v=tools', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'update_order',
                        order: JSON.stringify(idsOrdenados)
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        console.log('Ordem guardada com sucesso!');
                    } else {
                        console.error('Erro do servidor:', data.message);
                    }
                })
                .catch(err => console.error('Erro na submissão do Drag&Drop:', err));
            }
        });
    });

    // Recarregar os ícones do Lucide
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }
});

// Quando se volta a esta página através do botão "Recuar" do browser (ex: depois de "Ver Leads"),
// o browser pode mostrar uma versão em cache (bfcache) sem disparar DOMContentLoaded.
// Forçamos um reload para que as contagens de uso e a lista fiquem atualizadas.
window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});

</script>