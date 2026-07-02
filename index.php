<?php
// Início obrigatório do motor de sessões
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start();

// BARREIRA DE SEGURANÇA: Se não existir sessão válida, bloqueia e redireciona imediatamente
if (!isset($_SESSION['user_id'])) {
    header("Location: views/login.php");
    exit;
}

//Liga a base de dados globalmente para todo o site
require_once __DIR__ . '/config/database.php';

$stmt_check = $pdo->prepare("SELECT id_utilizador, nome, perfil FROM utilizadores WHERE id_utilizador = ?");
$stmt_check->execute([(int)$_SESSION['user_id']]);
$user_atual = $stmt_check->fetch();
if ($user_atual) {
    $_SESSION['user_id']     = (int)$user_atual['id_utilizador'];
    $_SESSION['user_nome']   = $user_atual['nome'];
    $_SESSION['user_perfil'] = $user_atual['perfil'];
}

// Capturar flash messages ANTES de qualquer output
$flash_erro   = $_SESSION['erro']    ?? null;  unset($_SESSION['erro']);
$flash_sucesso = $_SESSION['sucesso'] ?? null;  unset($_SESSION['sucesso']);

// Injeção da Peça 1: Cabeçalho Geral (Tags de abertura HTML e CDNs)
require_once 'includes/header.php';
?>

<?php if ($flash_erro): ?>
<div id="toast-erro"
     class="fixed top-5 right-5 z-[9999] flex items-center gap-3 bg-[#1a0a0a] border border-rose-500/30 text-rose-300 px-4 py-3 rounded-xl shadow-2xl shadow-black/50 text-sm font-medium max-w-sm"
     style="animation: toastIn 0.3s ease-out;">
    <span class="bg-rose-500/15 p-1.5 rounded-lg shrink-0">
        <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
    </span>
    <span class="flex-1"><?= htmlspecialchars($flash_erro) ?></span>
    <button onclick="dismissToast('toast-erro')" class="text-rose-500/60 hover:text-rose-300 transition shrink-0 ml-1">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
    </button>
</div>
<?php endif; ?>

<?php if ($flash_sucesso): ?>
<div id="toast-sucesso"
     class="fixed top-5 right-5 z-[9999] flex items-center gap-3 bg-[#071210] border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-xl shadow-2xl shadow-black/50 text-sm font-medium max-w-sm"
     style="animation: toastIn 0.3s ease-out;">
    <span class="bg-emerald-500/15 p-1.5 rounded-lg shrink-0">
        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
    </span>
    <span class="flex-1"><?= htmlspecialchars($flash_sucesso) ?></span>
    <button onclick="dismissToast('toast-sucesso')" class="text-emerald-500/60 hover:text-emerald-300 transition shrink-0 ml-1">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
    </button>
</div>
<?php endif; ?>

<style>
@keyframes toastIn {
    from { opacity: 0; transform: translateX(20px) scale(0.97); }
    to   { opacity: 1; transform: translateX(0)    scale(1);    }
}
@keyframes toastOut {
    from { opacity: 1; transform: translateX(0)    scale(1);    }
    to   { opacity: 0; transform: translateX(20px) scale(0.97); }
}

/* Transição global suave para o modo claro/escuro */
html {
    transition: background-color 0.3s ease, color 0.3s ease;
}
</style>

<script>
function dismissToast(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.animation = 'toastOut 0.25s ease-in forwards';
    setTimeout(() => el.remove(), 260);
}
// Auto-dismiss após 4 segundos
document.addEventListener('DOMContentLoaded', function () {
    ['toast-erro', 'toast-sucesso'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) setTimeout(() => dismissToast(id), 4000);
    });
});
</script>

<div class="flex flex-col md:flex-row h-screen w-full overflow-hidden">

    <?php require_once 'includes/sidebar.php'; ?>

    <main class="flex-1 p-6 md:p-8 overflow-y-auto bg-slate-100 dark:bg-[#070a13] min-w-0 transition-colors duration-300">
        <?php
        $view = $_GET['v'] ?? 'dashboard';

        switch ($view) {
            case 'leads':
                if (file_exists('views/leads.php')) {
                    // Recolha dos filtros da URL
                    $f_nome        = trim($_GET['f_nome']        ?? '');
                    $f_estado      = trim($_GET['f_estado']      ?? '');
                    $f_prioridade  = trim($_GET['f_prioridade']  ?? '');
                    $f_origem      = trim($_GET['f_origem']      ?? '');
                    $f_servico     = trim($_GET['f_servico']     ?? '');
                    $f_responsavel = trim($_GET['f_responsavel'] ?? '');
                    $f_data_de     = trim($_GET['f_data_de']     ?? '');
                    $f_data_ate    = trim($_GET['f_data_ate']    ?? '');
                    $f_pesquisa    = trim($_GET['f_pesquisa']    ?? '');

                    // Construção dinâmica da query
                    $where  = ['1=1'];
                    $params = [];

                    if ($f_nome)        { $where[] = "(leads.nome_contacto LIKE ? OR leads.empresa LIKE ?)"; $params[] = "%$f_nome%"; $params[] = "%$f_nome%"; }
                    if ($f_estado)      { $where[] = "leads.estado = ?";               $params[] = $f_estado; }
                    if ($f_prioridade)  { $where[] = "leads.prioridade = ?";           $params[] = $f_prioridade; }
                    if ($f_origem)      { $where[] = "leads.origem = ?";               $params[] = $f_origem; }
                    if ($f_servico)     { $where[] = "leads.servicos = ?";    $params[] = $f_servico; }
                    if ($f_responsavel) { $where[] = "leads.id_responsavel = ?";       $params[] = (int)$f_responsavel; }
                    if ($f_pesquisa) {
                        $p = "%{$f_pesquisa}%";
                        $where[] = "(
                            leads.nome_contacto    LIKE ? OR
                            leads.empresa          LIKE ? OR
                            leads.telefone         LIKE ? OR
                            leads.email            LIKE ? OR
                            leads.observacoes      LIKE ? OR
                            leads.servicos LIKE ? OR
                            leads.origem           LIKE ? OR
                            leads.estado           LIKE ? OR
                            leads.id_lead          = ?
                        )";
                        $params[] = $p; $params[] = $p; $params[] = $p;
                        $params[] = $p; $params[] = $p; $params[] = $p;
                        $params[] = $p; $params[] = $p;
                        $params[] = is_numeric($f_pesquisa) ? (int)$f_pesquisa : -1;
                    }

                    $sql = "
                        SELECT leads.*, utilizadores.nome AS nome_responsavel
                        FROM leads
                        LEFT JOIN utilizadores ON leads.id_responsavel = utilizadores.id_utilizador
                        WHERE " . implode(' AND ', $where) . "
                        ORDER BY CASE 
                            WHEN LOWER(leads.estado) IN ('nova', 'novo') THEN 1
                            WHEN LOWER(leads.estado) IN ('contactada', 'contactado') THEN 2
                            WHEN LOWER(leads.estado) IN ('reunião marcada') THEN 3
                            WHEN LOWER(leads.estado) IN ('proposta enviada') THEN 4
                            WHEN LOWER(leads.estado) IN ('em negociação') THEN 5
                            WHEN LOWER(leads.estado) IN ('ganha', 'ganho', 'perdida', 'perdido') THEN 6
                            ELSE 7
                        END ASC, leads.id_lead DESC
                    ";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $resultados    = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $totalRegistos = count($resultados);
                    require_once 'views/leads.php';
                }
                break;

            case 'dashboard':
            default:
                if (file_exists('views/dashboard.php')) {
                    require_once 'views/dashboard.php';
                } else {
                    echo '<div class="p-6 bg-white dark:bg-slate-900 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-slate-500 dark:text-slate-400 text-sm italic">O módulo "views/dashboard.php" aguarda desenvolvimento por parte da equipa.</div>';
                }
                break;

            case 'lead_add':
                if (file_exists('views/lead_create.php')) {
                    require_once 'views/lead_create.php';
                } else {
                    echo '<div class="p-6 bg-white dark:bg-slate-900 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-slate-500 dark:text-slate-400 text-sm italic">O módulo "views/lead_create.php" aguarda desenvolvimento por parte da equipa.</div>';
                }
                break;

            case 'tarefas':
                if (file_exists('views/tarefas.php')) {
                    require_once 'views/tarefas.php';
                } else {
                    echo '<div class="p-6 bg-white dark:bg-slate-900 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-slate-500 dark:text-slate-400 text-sm italic">O módulo "views/tarefas.php" aguarda desenvolvimento por parte da equipa.</div>';
                }
                break;

            case 'users':
                if (file_exists('views/users_manage.php')) {
                    require_once 'views/users_manage.php';
                } else {
                    echo '<div class="p-6 bg-white dark:bg-slate-900 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-slate-500 dark:text-slate-400 text-sm italic">O módulo "views/users.php" aguarda desenvolvimento por parte da equipa.</div>';
                }
                break;

            case 'tools':
                if (file_exists('views/tools.php')) {
                    require_once 'views/tools.php';
                } else {
                    echo '<div class="p-6 bg-white dark:bg-slate-900 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-slate-500 dark:text-slate-400 text-sm italic">O módulo "views/tools.php" aguarda desenvolvimento por parte da equipa.</div>';
                }
                break;

            case 'reports':
                if (file_exists('views/reports.php')) {
                    require_once 'views/reports.php';
                } else {
                    echo '<div class="p-6 bg-white dark:bg-slate-900 rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-slate-500 dark:text-slate-400 text-sm italic">O módulo "views/reports.php" aguarda desenvolvimento por parte da equipa.</div>';
                }
                break;
        }
        ?>
    </main>

</div>

<script>lucide.createIcons();</script>
</body>
</html>