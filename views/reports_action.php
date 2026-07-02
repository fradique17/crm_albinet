<?php
session_start();

// Segurança: garantir que o utilizador está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// IMPORTANTE: Inclui aqui o ficheiro de ligação à tua Base de Dados!
// Ajusta o caminho conforme o nome do teu ficheiro de configuração.
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export') {
    
    $tipo_relatorio = $_POST['tipo_relatorio'] ?? '';
    $data_inicio    = $_POST['data_inicio'] ?? date('01-m-Y');
    $data_fim       = $_POST['data_fim'] ?? date('d-m-Y');
    
// FIX DAS HORAS: Forçar o fuso horário de Portugal Continental
    date_default_timezone_set('Europe/Lisbon');

    // Nome do ficheiro dinâmico com a hora já corrigida
    $filename = "relatorio_{$tipo_relatorio}_" . date('Ymd_His') . ".csv";
    
    // Cabeçalhos para forçar download do CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Abrir o output stream
    $output = fopen('php://output', 'w');
    
    // Adicionar BOM para o Excel ler o UTF-8 corretamente
    fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));

    // Título do relatório dentro do CSV (também com a hora corrigida)
    fputcsv($output, ["Relatório gerado a: " . date('Y-m-d H:i:s')], ';');
    fputcsv($output, ["Período: $data_inicio a $data_fim"], ';');
    fputcsv($output, [], ';'); // Linha em branco

    try {
        switch ($tipo_relatorio) {
            case 'performance_comercial':
                fputcsv($output, ['Comercial', 'Total Leads', 'Ganhas', 'Perdidas', 'Valor Potencial Gerado (€)'], ';');
                $sql = "SELECT u.nome as comercial, COUNT(l.id_lead) as total,
                        SUM(CASE WHEN l.estado = 'Ganha' THEN 1 ELSE 0 END) as ganhas,
                        SUM(CASE WHEN l.estado = 'Perdida' THEN 1 ELSE 0 END) as perdidas,
                        SUM(l.valor_potencial) as valor_total
                        FROM leads l
                        LEFT JOIN utilizadores u ON l.id_responsavel = u.id_utilizador
                        GROUP BY u.id_utilizador";
                $stmt = $pdo->query($sql);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, [$row['comercial'] ?? 'Sem Responsável', $row['total'], $row['ganhas'], $row['perdidas'], round($row['valor_total'] ?? 0, 2)], ';');
                }
                break;

            case 'conversao_origem':
                fputcsv($output, ['Origem da Lead', 'Total Leads', 'Ganhas', 'Taxa de Conversão (%)'], ';');
                $sql = "SELECT origem, COUNT(id_lead) as total,
                        SUM(CASE WHEN estado = 'Ganha' THEN 1 ELSE 0 END) as ganhas
                        FROM leads 
                        GROUP BY origem";
                $stmt = $pdo->query($sql);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $taxa = $row['total'] > 0 ? round(($row['ganhas'] / $row['total']) * 100, 2) : 0;
                    fputcsv($output, [$row['origem'] ?: 'Não definida', $row['total'], $row['ganhas'], $taxa . '%'], ';');
                }
                break;
                
            case 'valor_estimado':
                fputcsv($output, ['Estado', 'Número de Leads', 'Valor Total Estimado (€)', 'Valor Médio por Lead (€)'], ';');
                $sql = "SELECT estado, COUNT(id_lead) as total, SUM(valor_potencial) as valor_total, AVG(valor_potencial) as valor_medio
                        FROM leads GROUP BY estado";
                $stmt = $pdo->query($sql);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, [$row['estado'], $row['total'], round($row['valor_total'] ?? 0, 2), round($row['valor_medio'] ?? 0, 2)], ';');
                }
                break;

            case 'leads_periodo':
                fputcsv($output, ['Data de Registo', 'Número de Leads'], ';');
                // Assume que usamos a rgpd_data_consentimento ou muda para a tua coluna de data
                $sql = "SELECT rgpd_data_consentimento as data_registo, COUNT(id_lead) as total 
                        FROM leads 
                        WHERE rgpd_data_consentimento BETWEEN ? AND ? 
                        GROUP BY rgpd_data_consentimento 
                        ORDER BY rgpd_data_consentimento DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data_inicio, $data_fim]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, [$row['data_registo'], $row['total']], ';');
                }
                break;

            case 'motivos_perda':
                fputcsv($output, ['Observações (Notas de Perda)', 'Total de Leads'], ';');
                $sql = "SELECT observacoes, COUNT(id_lead) as total 
                        FROM leads 
                        WHERE estado = 'Perdida' 
                        GROUP BY observacoes";
                $stmt = $pdo->query($sql);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, [trim($row['observacoes']) ?: 'Sem motivo registado', $row['total']], ';');
                }
                break;

            case 'leads_campanha':
                fputcsv($output, ['Serviços (Campanha)', 'Total Leads', 'Ganhas', 'Valor Potencial (€)'], ';');
                $sql = "SELECT servicos, COUNT(id_lead) as total,
                        SUM(CASE WHEN estado = 'Ganha' THEN 1 ELSE 0 END) as ganhas,
                        SUM(valor_potencial) as valor_total
                        FROM leads 
                        GROUP BY servicos";
                $stmt = $pdo->query($sql);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    fputcsv($output, [$row['servicos'] ?: 'Geral', $row['total'], $row['ganhas'], round($row['valor_total'] ?? 0, 2)], ';');
                }
                break;
                
            default:
                fputcsv($output, ['Erro: Tipo de relatório não reconhecido.'], ';');
                break;
        }
    } catch (PDOException $e) {
        fputcsv($output, ['Erro ao gerar dados: ' . $e->getMessage()], ';');
    }

    fclose($output);
    exit; // Termina o script aqui para não imprimir o HTML no ficheiro CSV
}
?>