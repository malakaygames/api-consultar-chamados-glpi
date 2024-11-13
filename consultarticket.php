<?php
/**
 * Função para iniciar sessão na API do GLPI
 */
function iniciarSessaoGLPI($glpi_url, $app_token, $user_token) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$glpi_url/initSession");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "App-Token: $app_token",
        "Authorization: user_token $user_token",
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    return isset($responseData['session_token']) ? $responseData['session_token'] : null;
}

/**
 * Função para obter detalhes do chamado
 */
function obterChamadoGLPI($glpi_url, $app_token, $session_token, $ticket_id) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$glpi_url/Ticket/$ticket_id?expand_dropdowns=true");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "App-Token: $app_token",
        "Session-Token: $session_token"  // Usar session_token em vez de Authorization
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Função para obter campos personalizados do ticket
 */
function obterCamposPersonalizados($glpi_url, $app_token, $session_token, $ticket_id) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$glpi_url/PluginFieldsTicketcampo?searchText[items_id]=$ticket_id");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "App-Token: $app_token",
        "Session-Token: $session_token"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    if (isset($data[0])) {
        return [
            'telefone' => $data[0]['telefonefield'] ?? '',
            'setor' => $data[0]['setorfield'] ?? '',
            'requerente' => $data[0]['requerentefield'] ?? '',
            'unidade' => $data[0]['unidadefield'] ?? ''
        ];
    }
    return null;
}

/**
 * Função para obter acompanhamentos do chamado
 */
function obterFollowUpsGLPI($glpi_url, $app_token, $session_token, $ticket_id) {
    $ch = curl_init();
    $url = "$glpi_url/Ticket/$ticket_id/ITILFollowup?expand_dropdowns=true";
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "App-Token: $app_token",
        "Session-Token: $session_token"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $followUps = json_decode($response, true);
    
    if (empty($followUps) || !is_array($followUps)) {
        return [];
    }
    
    foreach ($followUps as &$followUp) {
        if (!empty($followUp['users_id'])) {
            $followUp['users_id_editor_name'] = $followUp['users_id'];
        }
    }
    
    // Inverte a ordem dos comentários (mais recentes primeiro)
    return array_reverse($followUps);
}

/**
 * Função para obter validações do ticket
 */
function obterValidacoesTicket($glpi_url, $app_token, $session_token, $ticket_id) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$glpi_url/Ticket/$ticket_id?expand_dropdowns=true");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "App-Token: $app_token",
        "Session-Token: $session_token"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $ticketData = json_decode($response, true);
        
        if (!isset($ticketData['global_validation']) || $ticketData['global_validation'] === null || $ticketData['global_validation'] === 0) {
            return null;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$glpi_url/Ticket/$ticket_id/TicketValidation?expand_dropdowns=true");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "App-Token: $app_token",
            "Session-Token: $session_token"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $validationsResponse = curl_exec($ch);
        curl_close($ch);
        
        $validationsData = json_decode($validationsResponse, true);

        if ($_SESSION['debug_mode']) {
            error_log("Ticket Data: " . print_r($ticketData, true));
            error_log("Validations Data: " . print_r($validationsData, true));
        }
        
        return [
            'validation_status' => $ticketData['global_validation'],
            'validations' => is_array($validationsData) ? $validationsData : []
        ];
    }
    
    return null;
}

/**
 * Função para adicionar resposta ao ticket
 */
function adicionarRespostaTicket($glpi_url, $app_token, $session_token, $ticket_id, $content, $nome_usuario, $setor, $contato) {
    // Prepara o conteúdo da resposta com as informações do usuário de forma organizada
    $fullContent = "O Usuário <b>$nome_usuario</b> do setor $setor com o contato $contato, comentou via api de consulta:\n";
    $fullContent .= "<b>$content</b>";
    
    $data = [
        'input' => [
            'tickets_id' => intval($ticket_id),
            'content' => $fullContent
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$glpi_url/Ticket/$ticket_id/TicketFollowup");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "App-Token: $app_token",
        "Session-Token: $session_token"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    curl_close($ch);
    
    if ($http_code !== 201 && $http_code !== 200) {
        return [
            'error' => true,
            'message' => "Erro ao adicionar resposta (HTTP $http_code)",
            'details' => $curl_error,
            'response' => $response
        ];
    }
    
    return ['error' => false, 'response' => json_decode($response, true)];
}

// Configurações iniciais e sessão
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php-error.log');

session_start();
$_SESSION['debug_mode'] = true;

$cookieLifetime = 300;

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $cookieLifetime) {
    session_unset();
    session_destroy();
}
$_SESSION['LAST_ACTIVITY'] = time();

if (!isset($_SESSION['authenticated'])) {
    header('Location: /index.php');
    exit();
}

// Configurações da API do GLPI
$glpi_url = 'http://endereco do seu glpi/apirest.php';
$app_token = 'o token gerado pelo glpi';
$user_token = 'o token de usuario com permissao para api';


// Mapeamentos de status
$statusMapping = [
    1 => ['label' => 'Novo', 'class' => 'success'],
    2 => ['label' => 'Em atendimento', 'class' => 'warning'],
    3 => ['label' => 'Em atendimento - Planejado', 'class' => 'warning'],
    4 => ['label' => 'Pendente', 'class' => 'secondary'],
    5 => ['label' => 'Solucionado', 'class' => 'dark'],
    6 => ['label' => 'Fechado', 'class' => 'dark']
];

$validationStatusMapping = [
    'NONE' => ['id' => 0, 'label' => 'Recusado', 'class' => 'danger'],
    'WAITING' => ['id' => 1, 'label' => 'Não requer aprovação', 'class' => 'secondary'],
    'ACCEPTED' => ['id' => 2, 'label' => 'Esperando por validação', 'class' => 'warning'],
    'REFUSED' => ['id' => 3, 'label' => 'Aprovado', 'class' => 'success']
];

// Inicialização de variáveis
$ticketDetails = null;
$customFields = null;
$followUps = [];
$validacoes = null;
$errorMessage = '';
$currentTicketId = '';

// Processamento da consulta do ticket
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['ticket_id'])) {
        $session_token = iniciarSessaoGLPI($glpi_url, $app_token, $user_token);
        $currentTicketId = filter_var($_POST['ticket_id'], FILTER_SANITIZE_NUMBER_INT);
        
        if ($session_token) {
            $ticketDetails = obterChamadoGLPI($glpi_url, $app_token, $session_token, $currentTicketId);
            $customFields = obterCamposPersonalizados($glpi_url, $app_token, $session_token, $currentTicketId);
            $followUps = obterFollowUpsGLPI($glpi_url, $app_token, $session_token, $currentTicketId);
            $validacoes = obterValidacoesTicket($glpi_url, $app_token, $session_token, $currentTicketId);

            if (!isset($ticketDetails['id'])) {
                $errorMessage = '<div class="alert alert-danger" role="alert">
                    <h4 class="alert-heading">Chamado não encontrado!</h4>
                    <p>O número do chamado informado não existe ou você não tem permissão para visualizá-lo.</p>
                    <hr>
                    <p class="mb-0">Por favor, verifique o número e tente novamente.</p>
                </div>';
            }
        } else {
            $errorMessage = '<div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">Erro de conexão!</h4>
                <p>Não foi possível conectar ao sistema GLPI.</p>
                <hr>
                <p class="mb-0">Por favor, tente novamente mais tarde.</p>
            </div>';
        }
    }
    
    // Processamento da resposta ao ticket
    if (isset($_POST['respond']) && isset($_POST['response']) && isset($_POST['nome_usuario']) && isset($_POST['setor']) && isset($_POST['contato'])) {
        $session_token = iniciarSessaoGLPI($glpi_url, $app_token, $user_token);
        
        // Validação dos campos
        $nome_usuario = trim($_POST['nome_usuario']);
        $setor = trim($_POST['setor']);
        $contato = trim($_POST['contato']);
        
        // Validações
        $errors = [];
        
        if (empty($nome_usuario) || strlen($nome_usuario) > 20 || !preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ]+\s+[A-Za-zÀ-ÖØ-öø-ÿ]+$/', $nome_usuario)) {
            $errors[] = "Por favor, insira seu nome e sobrenome (máximo 20 caracteres).";
        }
        
        if (empty($setor) || strlen($setor) > 50) {
            $errors[] = "Por favor, informe seu setor (máximo 50 caracteres).";
        }
        
        if (empty($contato) || strlen($contato) > 15 || !preg_match('/^[\d\s()-]+$/', $contato)) {
            $errors[] = "Por favor, informe um número de contato válido.";
        }
            
        if (!empty($errors)) {
            $errorMessage = '<div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">Erro nos dados informados!</h4>
                <ul class="mb-0">';
            foreach ($errors as $error) {
                $errorMessage .= "<li>$error</li>";
            }
            $errorMessage .= '</ul></div>';
        } else if ($session_token) {
            $response = adicionarRespostaTicket(
                $glpi_url,
                $app_token,
                $session_token,
                $_POST['ticket_id'],
                $_POST['response'],
                $nome_usuario,
                $setor,
                $contato
            );
            
            if ($response['error']) {
                $errorMessage = '<div class="alert alert-danger" role="alert">
                    <h4 class="alert-heading">Erro ao adicionar resposta!</h4>
                    <p>' . htmlspecialchars($response['message']) . '</p>
                </div>';
            } else {
                // Recarrega os dados do ticket para mostrar a nova resposta
                $ticketDetails = obterChamadoGLPI($glpi_url, $app_token, $session_token, $_POST['ticket_id']);
                $followUps = obterFollowUpsGLPI($glpi_url, $app_token, $session_token, $_POST['ticket_id']);
                $errorMessage = '<div class="alert alert-success" role="alert">
                    <h4 class="alert-heading">Resposta adicionada com sucesso!</h4>
                </div>';
            }
        }
    }
}

function limparConteudoHTML($content) {
    return strip_tags($content);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Tickets - GLPI</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { 
            background-color: #f4f6f9; 
        }
        .container-custom {
            max-width: 800px;
            margin: 0 auto;
            padding-top: 30px;
        }
        .ticket-card {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .followup-card {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 8px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .custom-fields {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        .custom-fields p {
            margin-bottom: 0.5rem;
        }
        .custom-fields p:last-child {
            margin-bottom: 0;
        }
        .ticket-content {
            white-space: pre-wrap;
            word-break: break-word;
        }
        .ticket-content img {
            max-width: 100%;
            height: auto;
        }
        .ticket-content a {
            color: #007bff;
            text-decoration: none;
        }
        .ticket-content a:hover {
            text-decoration: underline;
        }
        .followup-content {
            white-space: pre-wrap;
            word-break: break-word;
            margin-top: 10px;
            padding: 10px;
            background-color: white;
            border-radius: 4px;
            text-indent: 0 !important;
            margin-left: 0 !important;
            padding-left: 10px !important;
            line-height: normal;
        }
        .ticket-content, .followup-content {
            white-space: pre-wrap;
            word-break: break-word;
        }
        .ticket-content img, .followup-content img {
            max-width: 100%;
            height: auto;
            margin: 10px 0;
        }
        .ticket-content a, .followup-content a {
            color: #007bff;
            text-decoration: none;
        }
        .ticket-content a:hover, .followup-content a:hover {
            text-decoration: underline;
        }
        .ticket-content p, .followup-content p {
            margin-bottom: 1rem;
        }
        .validation-detail {
            border-left: 3px solid #007bff;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
        }
        .ticket-header .badge {
            background-color: var(--badge-color, #6c757d);
            font-size: 0.9em;
            padding: 8px 12px;
            border-radius: 4px;
        }
        .ticket-header .badge-success {
            --badge-color: #28a745;
        }
        .ticket-header .badge-warning {
            --badge-color: #ffc107;
            color: #000;
        }
        .ticket-header .badge-danger {
            --badge-color: #dc3545;
        }
        .ticket-header .badge-secondary {
            --badge-color: #6c757d;
        }
        .collapse-btn {
            padding: 0;
            color: #6c757d;
            text-decoration: none;
        }
        .collapse-btn:hover {
            color: #343a40;
            text-decoration: none;
        }
        .collapse-btn[aria-expanded="false"] .fas.fa-chevron-up {
            transform: rotate(180deg);
        }
        .collapse-btn .fas.fa-chevron-up {
            transition: transform 0.2s ease-in-out;
        }
        .card-header {
            background-color: rgba(0, 0, 0, 0.03);
        }
    </style>
</head>
<body>
    <div class="container container-custom">
        <div class="text-center mb-4">
            <img src="" alt="Logo" class="img-fluid mb-4" style="max-width: 300px;">
            
            <form method="POST" class="form-row justify-content-center">
                <div class="col-auto">
                    <input type="text" name="ticket_id" class="form-control" 
                           placeholder="Nº do Ticket" required
                           pattern="\d{1,10}" title="Digite um número válido">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Pesquisar
                    </button>
                </div>
            </form>
        </div>

        <?php if ($errorMessage): ?>
            <?php echo $errorMessage; ?>
        <?php endif; ?>

        <?php if ($ticketDetails && isset($ticketDetails['id'])): ?>
            <div class="card ticket-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Chamado #<?php echo $ticketDetails['id']; ?></h4>
                    <span class="badge badge-<?php echo $statusMapping[$ticketDetails['status']]['class']; ?>">
                        <?php echo $statusMapping[$ticketDetails['status']]['label']; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Título:</strong> <?php echo htmlspecialchars($ticketDetails['name']); ?></p>
                            <p><strong>Solicitante:</strong> <?php echo htmlspecialchars($customFields['requerente'] ?? 'Não informado'); ?></p>
                            <p><strong>Data de Abertura:</strong> <?php echo date('d/m/Y H:i', strtotime($ticketDetails['date'])); ?></p>
                            <?php if ($customFields): ?>
    <div class="custom-fields mt-3">
        <?php if (!empty($customFields['telefone'])): ?>
            <p><strong>Telefone:</strong> 
                <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9]/', '', $customFields['telefone'])); ?>">
                    <?php echo htmlspecialchars($customFields['telefone']); ?>
                </a>
            </p>
        <?php endif; ?>
        <?php if (!empty($customFields['setor'])): ?>
            <p><strong>Setor:</strong> <?php echo htmlspecialchars($customFields['setor']); ?></p>
        <?php endif; ?>
        <?php if (!empty($customFields['unidade'])): ?>
            <p><strong>Unidade:</strong> <?php echo htmlspecialchars($customFields['unidade']); ?></p>
        <?php endif; ?>
    </div>
<?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Descrição</h6>
                            <div class="ticket-content">
                                <?php 
                                $content = $ticketDetails['content'];
                                $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                echo $content;
                                ?>
                            </div>
                        </div>
                    </div>
                    <br>
                    <?php if ($validacoes !== null): ?>
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">Status de Aprovação do Ticket</h4>
                            <div>
                                <?php
                                $validationStatus = $validacoes['validation_status'];
                                $statusInfo = array_values(array_filter($validationStatusMapping, function($status) use ($validationStatus) {
                                    return $status['id'] === $validationStatus;
                                }))[0] ?? $validationStatusMapping['NONE'];
                                ?>
                                <span class="badge badge-<?php echo $statusInfo['class']; ?>">
                                    <?php echo $statusInfo['label']; ?>
                                </span>
                                <button class="btn btn-link btn-sm ml-2 collapse-btn" type="button" data-toggle="collapse" data-target="#statusAprovacao" aria-expanded="true">
                                    <i class="fas fa-chevron-up"></i>
                                </button>
                            </div>
                        </div>
                        <div class="collapse" id="statusAprovacao">
                            <div class="card-body">
                                <!-- Conteúdo do status de aprovação -->
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Formulário de Resposta -->
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Adicionar Acompanhamentos</h4>
        <button class="btn btn-link btn-sm collapse-btn" type="button" data-toggle="collapse" data-target="#formResposta" aria-expanded="true">
            <i class="fas fa-chevron-up"></i>
        </button>
    </div>
    <div class="collapse" id="formResposta">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="ticket_id" value="<?php echo htmlspecialchars($ticketDetails['id']); ?>">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="nome_usuario">Nome e Sobrenome:</label>
                            <input type="text" 
                                   name="nome_usuario" 
                                   id="nome_usuario" 
                                   class="form-control" 
                                   maxlength="20" 
                                   pattern="^[A-Za-zÀ-ÖØ-öø-ÿ]+\s+[A-Za-zÀ-ÖØ-öø-ÿ]+$"
                                   title="Digite seu nome e sobrenome (ex: João Silva)"
                                   placeholder="Ex: João Silva"
                                   required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="setor">Setor:</label>
                            <input type="text" 
                                   name="setor" 
                                   id="setor" 
                                   class="form-control" 
                                   maxlength="50" 
                                   placeholder="Ex: TI"
                                   required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="contato">Contato:</label>
                            <input type="text" 
                                   name="contato" 
                                   id="contato" 
                                   class="form-control" 
                                   maxlength="15" 
                                   pattern="[\d\s()-]+"
                                   title="Digite apenas números, espaços, parênteses e hífen"
                                   placeholder="Ex: (11) 98765-4321"
                                   required>
                        </div>
                    </div>
                </div>
                <div class="form-group mt-3">
                    <label for="response">Sua resposta:</label>
                    <textarea name="response" id="response" class="form-control" rows="4" required></textarea>
                </div>
                <button type="submit" name="respond" class="btn btn-primary">
                    <i class="fas fa-reply"></i> Enviar Resposta
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Acompanhamentos -->
<?php if (!empty($followUps)): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Listar Acompanhamentos</h4>
            <button class="btn btn-link btn-sm collapse-btn" type="button" data-toggle="collapse" data-target="#acompanhamentos" aria-expanded="true">
                <i class="fas fa-chevron-up"></i>
            </button>
        </div>
        <div class="collapse" id="acompanhamentos">
            <div class="card-body p-0">
                <?php foreach ($followUps as $followUp): ?>
                    <div class="followup-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="text-primary font-weight-bold">
                                    <?php 
                                    if (!empty($followUp['users_id'])) {
                                        echo htmlspecialchars($followUp['users_id']);
                                    } else {
                                        echo htmlspecialchars('Usuário');
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="text-muted small">
                                <?php echo date('d/m/Y H:i:s', strtotime($followUp['date_creation'])); ?>
                            </div>
                        </div>
                        <div class="followup-content" style="text-indent: 0; margin-left: 0;">
                            <?php 
                            if (isset($followUp['content'])) {
                                $content = $followUp['content'];
                                $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                $content = preg_replace('/<\/?p>/', '', $content);
                                $content = ltrim($content);
                                $content = trim($content);
                                $content = preg_replace('/^\n+/', '', $content);
                                $content = preg_replace('/^\s+/', '', $content);
                                echo '<span style="display: inline-block; text-indent: 0; margin: 0; padding: 0;">' . $content . '</span>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info text-center mt-4">
        <i class="fas fa-info-circle"></i> Nenhum acompanhamento encontrado para este chamado.
    </div>
<?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    // Limpar estados salvos quando pesquisar novo ticket
    document.querySelector('form').addEventListener('submit', function() {
        localStorage.removeItem('panelStates');

        document.getElementById('nome_usuario').addEventListener('input', function(e) {
            let value = e.target.value;
            value = value.replace(/\s+/g, ' ');
            value = value.trim();
            e.target.value = value;
        });

        // Validação do contato
        document.getElementById('contato').addEventListener('input', function(e) {
            let value = e.target.value;
            value = value.replace(/[^\d\s()-]/g, '');
            e.target.value = value;
        });

        // Controle dos botões de collapse
        document.querySelectorAll('.collapse-btn').forEach(button => {
            button.addEventListener('click', function() {
                const icon = this.querySelector('i');
                icon.style.transform = this.getAttribute('aria-expanded') === 'true' 
                    ? 'rotate(180deg)' 
                    : 'rotate(0deg)';
            });
        });

        // Função para salvar estado dos painéis
        function savePanelStates() {
            const states = {};
            document.querySelectorAll('.collapse').forEach(panel => {
                states[panel.id] = panel.classList.contains('show');
            });
            localStorage.setItem('panelStates', JSON.stringify(states));
        }

        // Função para restaurar estado dos painéis
        function restorePanelStates() {
            const states = JSON.parse(localStorage.getItem('panelStates') || '{}');
            Object.entries(states).forEach(([id, isOpen]) => {
                const panel = document.getElementById(id);
                if (panel) {
                    if (isOpen) {
                        panel.classList.add('show');
                    } else {
                        panel.classList.remove('show');
                    }
                    const btn = document.querySelector(`[data-target="#${id}"]`);
                    if (btn) {
                        btn.setAttribute('aria-expanded', isOpen);
                        const icon = btn.querySelector('i');
                        icon.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
                    }
                }
            });
        }

        // Adicionar evento para salvar estados quando mudar
        document.querySelectorAll('.collapse').forEach(panel => {
            panel.addEventListener('shown.bs.collapse', savePanelStates);
            panel.addEventListener('hidden.bs.collapse', savePanelStates);
        });

        // Restaurar estados quando a página carregar
        restorePanelStates();
    });
    </script>
</body>
</html>
