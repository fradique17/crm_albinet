<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';

// Bloqueio de segurança (se não estiver logado, atira para o index)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

// Validação do token CSRF (cobre todas as ações deste ficheiro, incluindo o drag-and-drop)
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Ação não autorizada. Token de segurança inválido.');
}

// ------------------------------------------------------------------
// GRAVAR O ARRASTO DO KANBAN (DRAG AND DROP) - Global Catch
// ------------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'atualizar_estado_dragdrop') {
    $id = (int)$_POST['id_lead'];
    $novo_estado = $_POST['estado'];

    try {
        $stmt = $pdo->prepare("UPDATE leads SET estado = ? WHERE id_lead = ?");
        if ($stmt->execute([$novo_estado, $id])) {
            exit('OK');
        } else {
            http_response_code(500);
            exit('Erro ao atualizar base de dados.');
        }
    } catch (PDOException $e) {
        error_log('Erro ao atualizar estado da lead (dragdrop): ' . $e->getMessage());
        http_response_code(500);
        exit('Erro ao atualizar. Tenta novamente.');
    }
}
// ------------------------------------------------------------------

$action = $_POST['action'] ?? '';

switch ($action) {

    // ── REGISTAR NOVA INTERAÇÃO ──────────────────────────────────
    case 'adicionar_interacao':
    case 'guardar_interacao':
        $id_lead   = (int)($_POST['id_lead'] ?? 0);
        $tipo      = trim($_POST['tipo'] ?? '');
        $descricao = trim($_POST['resumo'] ?? $_POST['descricao'] ?? '');
        $id_user   = $_SESSION['user_id'] ?? null; 

        if ($id_lead > 0 && !empty($tipo) && !empty($descricao) && $id_user) {
            
            // Regista apenas a interação na BD
            $sql = "INSERT INTO interacoes (id_lead, id_utilizador, tipo, descricao, data_registo) 
                    VALUES (?, ?, ?, ?, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_lead, $id_user, $tipo, $descricao]);

            // Fetch lead name for the notification
            $stmt_nome = $pdo->prepare("SELECT COALESCE(NULLIF(empresa,''), nome_contacto) as nome FROM leads WHERE id_lead = ?");
            $stmt_nome->execute([$id_lead]);
            $nome_lead = $stmt_nome->fetchColumn() ?: 'Lead';
            $_SESSION['flash_msg'] = 'interacao_salva';
            $_SESSION['flash_nome'] = $nome_lead;
            header("Location: ../index.php?v=leads");
            exit;
        } else {
            $_SESSION['flash_msg'] = 'erro_interacao';
            header("Location: ../index.php?v=leads");
            exit;
        }

    // ── CRIAR LEAD ───────────────────────────────────────────────
    case 'criar':
        $nome            = trim($_POST['nome'] ?? '');
        $empresa         = trim($_POST['empresa'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $telefone        = trim($_POST['telefone'] ?? '');
        $origem          = $_POST['origem'] ?? '';
        
        // Se existirem serviços selecionados, junta-os com uma vírgula
        $servicos = isset($_POST['servicos']) && is_array($_POST['servicos']) ? implode(', ', $_POST['servicos']) : '';
        
        $estado          = $_POST['estado'] ?? 'Nova';
        $prioridade      = $_POST['prioridade'] ?? '';
        $notas           = trim($_POST['notas'] ?? '');

        $valor_potencial = (trim($_POST['valor_potencial'] ?? '') !== '') ? (float)$_POST['valor_potencial'] : null;
        $id_responsavel  = (!empty($_POST['id_responsavel']) && (int)$_POST['id_responsavel'] > 0) ? (int)$_POST['id_responsavel'] : null;

        if (empty($nome) && empty($empresa)) {
            $_SESSION['flash_msg'] = 'erro_validacao';
            header("Location: ../index.php?v=leads");
            exit;
        }

        // 1. Gerar o token para o RGPD
        $token_rgpd = bin2hex(random_bytes(32));

        // 2. Inserir a Lead (Resolvido o bug: apenas usamos a coluna `servicos` no plural)
        $stmt = $pdo->prepare("
            INSERT INTO leads
                (nome_contacto, empresa, telefone, email, origem, servicos,
                 estado, prioridade, id_responsavel, observacoes,
                 token_rgpd, estado_rgpd, valor_potencial)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendente', ?)
        ");
        
        $stmt->execute([
            $nome, $empresa, $telefone, $email, $origem, $servicos,
            $estado, $prioridade, $id_responsavel, $notas,
            $token_rgpd, $valor_potencial
        ]);

        // 3. Sistema de Envio de E-mail (No formato simples em texto, igual à recuperação de password)
        if ($email) {
            $link_aceitar = "http://localhost/crm_albinet/views/gerir_rgpd.php?token=" . $token_rgpd . "&acao=aceitar";
            $link_recusar = "http://localhost/crm_albinet/views/gerir_rgpd.php?token=" . $token_rgpd . "&acao=recusar";

            $pasta_logs = __DIR__ . '/../logs_email/';
            if (!file_exists($pasta_logs)) {
                mkdir($pasta_logs, 0777, true);
            }

            $conteudo_email = "==================================================\n";
            $conteudo_email .= "DE: CRM Albinet <suporte@albinet.pt>\n";
            $conteudo_email .= "PARA: " . $email . "\n";
            $conteudo_email .= "ASSUNTO: Aceitacao de Termos RGPD - CRM Albinet\n";
            $conteudo_email .= "==================================================\n";
            $conteudo_email .= "Olá " . $nome . ",\n\n";
            $conteudo_email .= "Foste adicionado à nossa base de dados de contactos.\n";
            $conteudo_email .= "Para cumprirmos as normas do RGPD, precisamos do teu consentimento.\n\n";
            $conteudo_email .= "-> Para ACEITAR e continuar na nossa base de dados, clica aqui:\n";
            $conteudo_email .= $link_aceitar . "\n\n";
            $conteudo_email .= "-> Para RECUSAR e remover permanentemente os teus dados, clica aqui:\n";
            $conteudo_email .= $link_recusar . "\n\n";
            $conteudo_email .= "==================================================\n\n";

            file_put_contents($pasta_logs . 'emails_rgpd_enviados.txt', $conteudo_email, FILE_APPEND);
        }

        // Get the newly created lead name
        $id_nova_lead = $pdo->lastInsertId();
        $stmt_nome = $pdo->prepare("SELECT COALESCE(NULLIF(empresa,''), nome_contacto) as nome FROM leads WHERE id_lead = ?");
        $stmt_nome->execute([$id_nova_lead]);
        $nome_lead = $stmt_nome->fetchColumn() ?: 'Lead';
        $_SESSION['flash_msg'] = 'criado';
        $_SESSION['flash_nome'] = $nome_lead;
        header("Location: ../index.php?v=leads");
        exit;

    // ── EDITAR LEAD ──────────────────────────────────────────
    case 'editar':
        $id_lead         = (int)($_POST['id_lead'] ?? 0);
        $nome            = trim($_POST['nome'] ?? '');
        $empresa         = trim($_POST['empresa'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $telefone        = trim($_POST['telefone'] ?? '');
        $origem          = $_POST['origem'] ?? '';
        $servicos        = isset($_POST['servicos']) && is_array($_POST['servicos']) ? implode(', ', $_POST['servicos']) : '';
        $estado          = $_POST['estado'] ?? '';
        $prioridade      = $_POST['prioridade'] ?? '';
        $notas           = trim($_POST['notas'] ?? $_POST['notes'] ?? '');
        
        $valor_potencial = (trim($_POST['valor_potencial'] ?? '') !== '') ? (float)$_POST['valor_potencial'] : null;
        $id_responsavel  = (!empty($_POST['id_responsavel']) && (int)$_POST['id_responsavel'] > 0) ? (int)$_POST['id_responsavel'] : null;

        if (empty($nome) && empty($empresa)) {
            $_SESSION['flash_msg'] = 'erro_validacao';
            header("Location: ../index.php?v=leads");
            exit;
        }

        // Resolvido o bug: usamos também apenas `servicos` no plural
        $stmt = $pdo->prepare("
            UPDATE leads SET 
                nome_contacto    = ?, 
                empresa          = ?, 
                telefone         = ?, 
                email            = ?, 
                origem           = ?, 
                servicos         = ?, 
                estado           = ?, 
                prioridade       = ?, 
                observacoes      = ?,
                valor_potencial  = ?,
                id_responsavel   = ?
            WHERE id_lead = ?
        ");

        $stmt->execute([
            $nome, $empresa, $telefone, $email,
            $origem, $servicos, $estado, $prioridade,
            $notas, $valor_potencial, $id_responsavel, $id_lead
        ]);

        $stmt_nome = $pdo->prepare("SELECT COALESCE(NULLIF(empresa,''), nome_contacto) as nome FROM leads WHERE id_lead = ?");
        $stmt_nome->execute([$id_lead]);
        $nome_lead = $stmt_nome->fetchColumn() ?: 'Lead';
        $_SESSION['flash_msg'] = 'editado';
        $_SESSION['flash_nome'] = $nome_lead;
        header("Location: ../index.php?v=leads");
        exit;

    // ── APAGAR LEAD ─────────────────────────────────────────
    case 'apagar':
        $id_lead = (int)($_POST['id_lead'] ?? 0);

        // Fetch name before deleting
        $stmt_nome = $pdo->prepare("SELECT COALESCE(NULLIF(empresa,''), nome_contacto) as nome FROM leads WHERE id_lead = ?");
        $stmt_nome->execute([$id_lead]);
        $nome_lead = $stmt_nome->fetchColumn() ?: 'Lead';

        $stmt = $pdo->prepare("DELETE FROM leads WHERE id_lead = ?");
        $stmt->execute([$id_lead]);

        $_SESSION['flash_msg'] = 'apagado';
        $_SESSION['flash_nome'] = $nome_lead;
        header("Location: ../index.php?v=leads");
        exit;

    // ── DEFAULT (REDIRECIONAR) ──────────────────────────────
    default:
        header("Location: ../index.php?v=leads");
        exit;
}