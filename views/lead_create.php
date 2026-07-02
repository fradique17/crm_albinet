<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: ../views/login.php");
    exit;
}

global $pdo;

// Gerar token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$mensagem = "";
$tipo_mensagem = "";

$opcoes_dinamicas = [
    'origem' => [],
    'servicos' => [],
    'estado' => [],
    'prioridade' => []
];

try {
    if (isset($pdo)) {
        // Guardas o resultado em $stmt
        $stmt = $pdo->query("SELECT * FROM crm_opcoes ORDER BY ordem ASC, id ASC");
        
        // Carrega as opções dinâmicas da tabela crm_opcoes
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 
            if (array_key_exists($row['tipo'], $opcoes_dinamicas)) {
                $opcoes_dinamicas[$row['tipo']][] = $row['valor'];
            }
        }

        // Carrega os utilizadores da tabela "utilizadores"
        $stmt_user = $pdo->query("SELECT id_utilizador, nome FROM utilizadores ORDER BY id_utilizador ASC");
        while ($row_user = $stmt_user->fetch(PDO::FETCH_ASSOC)) {
            $lista_utilizadores[] = $row_user;
        }

    } else {
        $mensagem = "Erro do Sistema: A ligação à Base de Dados (\$pdo) não foi encontrada no topo da página.";
        $tipo_mensagem = "danger";
    }
} catch (PDOException $e) {
    $mensagem = "Erro ao carregar os campos: " . $e->getMessage();
    $tipo_mensagem = "danger";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $mensagem = "Ação não autorizada. Token de segurança inválido. Recarrega a página e tenta novamente.";
        $tipo_mensagem = "danger";
    } else {
    try {
        $nome       = $_POST['nome'] ?? '';
        $empresa    = $_POST['empresa'] ?? '';
        $email      = $_POST['email'] ?? '';
        $telefone   = $_POST['telefone'] ?? '';
        $origem     = $_POST['origem'] ?? '';
        $servicos  = $_POST['servicos'] ?? '';
        $estado     = $_POST['estado'] ?? '';
        $prioridade = $_POST['prioridade'] ?? '';
        $notas      = $_POST['notas'] ?? '';
        $rgpd       = isset($_POST['rgpd_consentimento']) ? 'Sim' : 'Não';
        
        $valor_potencial = (isset($_POST['valor_potencial']) && trim($_POST['valor_potencial']) !== '') ? $_POST['valor_potencial'] : null;
        
        if (isset($_POST['responsavel']) && $_POST['responsavel'] !== '') {
            $id_responsavel = $_POST['responsavel'];
        } else {
            $id_responsavel = null;
        }

        $stmt = $pdo->prepare("
            INSERT INTO leads
            (nome_contacto, empresa, telefone, email, origem, servicos, estado, prioridade, id_responsavel, observacoes, rgpd_consentimento, rgpd_data_consentimento, rgpd_origem_consentimento, valor_potencial)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Formulário CRM', ?)
        ");
        
        $stmt->execute([$nome, $empresa, $telefone, $email, $origem, $servicos, $estado, $prioridade, $id_responsavel, $notas, $rgpd, $valor_potencial]);

        // Guardar a mensagem numa variável de sessão
        $_SESSION['mensagem_alerta'] = "Lead da empresa '$empresa' criada com sucesso!";
        $_SESSION['tipo_alerta'] = "success";

        // Forçar o browser a redirecionar para a mesma página, limpando o histórico do POST
        header("Location: index.php?v=lead_add");
        exit;

    } catch (PDOException $e) {
        // Em caso de erro, NÃO redirecionamos para o utilizador não perder os dados que já escreveu no formulário
        error_log('Erro ao guardar lead (lead_create.php): ' . $e->getMessage());
        $mensagem = "Erro ao guardar a lead. Verifica os dados e tenta novamente.";
        $tipo_mensagem = "danger";
    }
    }
}

// Verifica se existe uma mensagem guardada na sessão (vinda do redirect de sucesso)
if (isset($_SESSION['mensagem_alerta'])) {
    $mensagem = $_SESSION['mensagem_alerta'];
    $tipo_mensagem = $_SESSION['tipo_alerta'];
    
    // Apaga a mensagem da sessão para que não volte a aparecer no próximo reload
    unset($_SESSION['mensagem_alerta']);
    unset($_SESSION['tipo_alerta']);
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8" />
  <title>CRM Albinet – Nova Lead</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/crm_albinet/assets/css/lead_create.css">
</head>
<body>

  <main class="main">
    
    <div class="card">
      <div class="card-header">
        <div class="card-title">Informações da Lead</div>
        <div class="card-subtitle">Preenche os dados do novo potencial cliente.
        <span style="color:Tomato;">Os campos marcados com * são obrigatórios.</span>
      </div></div>

      <?php if (!empty($mensagem)): ?>
      <div id="mensagem-alerta" class="alert alert-<?php echo $tipo_mensagem; ?>">
      <i class="fa <?php echo $tipo_mensagem == 'success' ? 'fa-check-circle' : 'fa-circle-exclamation'; ?>"></i>
      <?php echo $mensagem; ?>
      </div>
      <?php endif; ?>

      <form action="" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Nome e Apelido <span class="req">*</span></label>
            <input type="text" name="nome" class="form-control" placeholder="Ex: João Silva" required>
          </div>

          <div class="form-group">
            <label class="form-label">Empresa <span class="req">*</span></label>
            <input type="text" name="empresa" class="form-control" placeholder="Ex: Tech Solutions Lda" required>
          </div>

          <div class="form-group">
            <label class="form-label">E-mail <span class="req">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="joao@empresa.pt" required>
          </div>

          <div class="form-group">
            <label class="form-label">Telefone / Telemóvel</label>
            <input type="tel" name="telefone" class="form-control" placeholder="+351 912 345 678" pattern="[0-9+ ]*" oninput="this.value=this.value.replace(/[^0-9+ ]/g,'');">
          </div>

          <div class="form-group">
          <label class="form-label">Origem da Lead</label>
          <select name="origem" class="form-control">
          <?php foreach ($opcoes_dinamicas['origem'] as $opt): ?> 
          <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
          <?php endforeach; ?>
          </select>
          </div>

          <div class="form-group">
          <label class="form-label">servicos Principal</label>
          <select name="servicos" class="form-control">
          <?php foreach ($opcoes_dinamicas['servicos'] as $opt): ?>
          <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
          <?php endforeach; ?>
          </select>
          </div>

          <div class="form-group">
          <label class="form-label">Estado da Lead</label>
          <select name="estado" class="form-control">
          <?php foreach ($opcoes_dinamicas['estado'] as $opt): ?>
          <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
          <?php endforeach; ?>
          </select>
          </div>

          <div class="form-group">
            <label class="form-label">Grau de Prioridade</label>
            <select name="prioridade" class="form-control">
              <?php foreach ($opcoes_dinamicas['prioridade'] as $opt): ?>
              <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Valor Potencial Estimado (€)</label>
            <input type="number" step="0.01" name="valor_potencial" class="form-control" placeholder="Ex: 5000.00">
          </div>

          <div class="form-group">
            <label class="form-label">Responsável Comercial</label>
            <select name="responsavel" class="form-control">
              <option value="">Aberto</option>
              <?php foreach ($lista_utilizadores as $user): ?>
                <option value="<?php echo htmlspecialchars($user['id_utilizador']); ?>" <?php echo ($user['id_utilizador'] == $_SESSION['user_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($user['nome']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group full-width">
            <label class="form-label">Notas Internas</label>
            <textarea name="notas" class="form-control" placeholder="Adiciona contexto útil (pedidos do cliente, prazo de decisão...)"></textarea>
          </div>

          <div class="form-group full-width">
            <div class="checkbox-group">
              <input type="checkbox" name="rgpd_consentimento" id="rgpd" required>
              <label for="rgpd" class="checkbox-text">
                <strong>Consentimento RGPD <span class="req" style="color:Tomato;">*</span></strong><br>
                Confirmo que o cliente deu consentimento explícito para a recolha e tratamento destes dados para fins comerciais, de acordo com o Regulamento Geral de Proteção de Dados (RGPD).
              </label>
            </div>
          </div>
        </div>

        <div class="form-actions" style="display: flex; justify-content: space-between; align-items: center;">
          <button type="reset" class="btn btn-secondary"><i class="fa fa-trash" style="margin-right: 6px;"></i> Limpar </button>
          <button type="submit" class="btn btn-primary"><i class="fa fa-save" style="margin-right: 6px;"></i> Guardar Lead</button>
        </div>

      </form>
    </div>

  </main>

<script>
  // Espera que a página carregue completamente
  document.addEventListener('DOMContentLoaded', function() {
    
    // Seleciona o alerta pelo ID
    const alerta = document.getElementById('mensagem-alerta');
    
    // Verifica se o alerta existe na página
    if (alerta) {
      // Define um temporizador para 5 segundos (5000 milissegundos)
      setTimeout(() => {
        // Passo 1: Aplica uma transição CSS para o elemento ficar transparente suavemente
        alerta.style.transition = 'opacity 0.5s ease';
        alerta.style.opacity = '0';
        
        // Passo 2: Remove o elemento do HTML após o fade-out terminar (500ms depois)
        setTimeout(() => {
          alerta.remove();
        }, 500);
        
      }, 3000);
    }
  });
</script>

</body>
</html>