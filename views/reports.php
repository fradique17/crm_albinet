<?php
// Segurança: garantir que o utilizador está logado
if (!isset($_SESSION['user_id'])) {
    // header("Location: ../views/login.php");
    // exit;
}       
?>

<link rel="stylesheet" href="/crm_albinet/assets/css/reports.css">

<div class="w-full">
    <div class="mb-8">
        <h1 class="text-2xl font-black tracking-tight bg-gradient-to-r from-indigo-400 to-cyan-400 bg-clip-text text-transparent">Centro de Relatórios</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">Extrai dados estruturados em CSV para análise de performance, campanhas e conversões.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        
        <?php
        // Array com a configuração de cada relatório para gerar os cards dinamicamente
        $relatorios = [
            [
                'id' => 'performance_comercial',
                'icone' => 'users',
                'titulo' => 'Performance Comercial',
                'desc' => 'Métricas de sucesso, leads ganhas e perdidas por cada membro da equipa.',
                'cor' => 'indigo'
            ],
            [
                'id' => 'conversao_origem',
                'icone' => 'pie-chart',
                'titulo' => 'Conversão por Origem',
                'desc' => 'Analisa que canais de captação (Site, Redes Sociais, etc) geram mais clientes.',
                'cor' => 'cyan'
            ],
            [
                'id' => 'valor_estimado',
                'icone' => 'euro',
                'titulo' => 'Valor do Negócio',
                'desc' => 'Pipeline de faturação baseada no valor estimado das leads em cada estado.',
                'cor' => 'emerald'
            ],
            [
                'id' => 'leads_periodo',
                'icone' => 'calendar-days',
                'titulo' => 'Leads por Período',
                'desc' => 'Volume de novas leads geradas ao longo do tempo (diário, semanal, mensal).',
                'cor' => 'blue'
            ],
            [
                'id' => 'motivos_perda',
                'icone' => 'trending-down',
                'titulo' => 'Motivos de Perda',
                'desc' => 'Justificações principais pelas quais os negócios não foram fechados.',
                'cor' => 'rose'
            ],
            [
                'id' => 'leads_campanha',
                'icone' => 'megaphone',
                'titulo' => 'Leads por Campanha',
                'desc' => 'Retorno sobre o investimento (ROI) de campanhas de marketing específicas.',
                'cor' => 'amber'
            ]
        ];
        ?>

        <?php foreach ($relatorios as $rel): ?>
        <div class="report-card bg-white dark:bg-slate-900/50 border border-slate-200 border-slate-300 dark:border-slate-800/80 rounded-2xl p-5 backdrop-blur-xl flex flex-col justify-between transition-all duration-300 group">
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2.5 bg-<?= $rel['cor'] ?>-500/10 rounded-xl text-<?= $rel['cor'] ?>-400 transition-colors group-hover:bg-<?= $rel['cor'] ?>-500/20">
                        <i data-lucide="<?= $rel['icone'] ?>" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200"><?= $rel['titulo'] ?></h3>
                </div>
                <p class="text-xs text-slate-600 dark:text-slate-400 mb-6 leading-relaxed min-h-[40px]">
                    <?= $rel['desc'] ?>
                </p>
            </div>

            <form action="views/reports_action.php" method="POST" class="mt-auto border-t border-slate-300 dark:border-slate-800/60 pt-4">
                <input type="hidden" name="action" value="export">
                <input type="hidden" name="tipo_relatorio" value="<?= $rel['id'] ?>">
                
                <div class="grid grid-cols-2 gap-2 mb-4">
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 dark:text-slate-400 mb-1">Início</label>
                        <input type="date" name="data_inicio" value="<?= date('Y-m-01') ?>" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg px-2 py-1.5 text-xs text-slate-700 dark:text-slate-300 focus:outline-none focus:border-indigo-500 transition">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider font-semibold text-slate-500 dark:text-slate-400 mb-1">Fim</label>
                        <input type="date" name="data_fim" value="<?= date('Y-m-t') ?>" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 rounded-lg px-2 py-1.5 text-xs text-slate-700 dark:text-slate-300 focus:outline-none focus:border-indigo-500 transition">
                    </div>
                </div>

                <button type="submit" class="w-full py-2.5 bg-slate-200 dark:bg-slate-800 hover:bg-<?= $rel['cor'] ?>-600 dark:hover:bg-<?= $rel['cor'] ?>-600 text-slate-700 dark:text-slate-300 hover:text-white dark:hover:text-white rounded-xl text-xs font-semibold transition-all duration-300 flex items-center justify-center gap-2 shadow-lg hover:shadow-<?= $rel['cor'] ?>-500/25 dark:hover:shadow-<?= $rel['cor'] ?>-500/25">
                    <i data-lucide="download" class="w-3.5 h-3.5"></i> Exportar CSV
                </button>
            </form>

        </div>
        <?php endforeach; ?>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Inicializar os ícones do Lucide
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }

    // Validação dinâmica de datas para todos os relatórios
    const formsRelatorios = document.querySelectorAll('form[action="views/reports_action.php"]');
    
    formsRelatorios.forEach(form => {
        const inputInicio = form.querySelector('input[name="data_inicio"]');
        const inputFim = form.querySelector('input[name="data_fim"]');
        
        function atualizarRegrasDataFim() {
            // Define o mínimo do calendário do "Fim" para não permitir escolher dias antes do "Início"
            inputFim.min = inputInicio.value;
            
            // Se a data de fim que lá estiver for anterior à nova data de início, ajusta o valor do Fim automaticamente
            if (inputFim.value && inputFim.value < inputInicio.value) {
                inputFim.value = inputInicio.value;
            }
        }
        
        // Corre a verificação logo ao carregar a página (para aplicar nas datas default)
        atualizarRegrasDataFim();
        
        // Fica à escuta de qualquer alteração na Data de Início
        inputInicio.addEventListener('change', atualizarRegrasDataFim);
    });
});
</script>