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
        "Authorization: user_token $user_token"
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
        "Session-Token: $session_token"
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
    
    return $followUps;
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
        
        // Verifica se existe global_validation no ticket
        if (!isset($ticketData['global_validation']) || $ticketData['global_validation'] === null || $ticketData['global_validation'] === 0) {
            return null; // Retorna null quando não há pedido de aprovação
        }

        // Busca as validações do ticket
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
?>

<?php
// Ativar a exibição de erros para depuração
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Log de erros em um arquivo
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php-error.log');

session_start();
$_SESSION['debug_mode'] = true;

// Define a duração da sessão (300 segundos)
$cookieLifetime = 300;

// Verifica se a sessão já foi iniciada e se a expiração ocorreu
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $cookieLifetime) {
    session_unset();
    session_destroy();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Verificação de autenticação
if (!isset($_SESSION['authenticated'])) {
    header('Location: /index.php');
    exit();
}

// Configurações da API do GLPI
$glpi_url = 'http://endereco do seu glpi/apirest.php';
$app_token = 'token do seu glpi';
$user_token = 'token do usuario da api';

// Mapeamento de status do ticket
$statusMapping = [
    1 => ['label' => 'Novo', 'class' => 'success'],
    2 => ['label' => 'Em atendimento', 'class' => 'warning'],
    3 => ['label' => 'Em atendimento - Planejado', 'class' => 'warning'],
    4 => ['label' => 'Pendente', 'class' => 'secondary'],
    5 => ['label' => 'Solucionado', 'class' => 'dark'],
    6 => ['label' => 'Cancelado', 'class' => 'danger']
];

// Mapeamento dos status de validação
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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ticket_id'])) {
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

function limparConteudoHTML($content) {
    return strip_tags($content);
}
?>

<?php
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
        .ticket-header {
            background-color: #007bff;
            color: white;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 1rem;
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
            border-left: 4px solid #007bff;
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
    </style>
</head>
<body>
    <div class="container container-custom">
        <div class="text-center mb-4">
            <img src="https://i.imgur.com/kOwxvUW.png" alt="Logo" class="img-fluid mb-4" style="max-width: 300px;">
            
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
                <div class="ticket-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Chamado #<?php echo $ticketDetails['id']; ?></h4>
                    <span class="badge badge-<?php echo $statusMapping[$ticketDetails['status']]['class']; ?>">
                        <?php echo $statusMapping[$ticketDetails['status']]['label']; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted">Detalhes do Chamado</h6>
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

                    <?php if ($validacoes !== null): ?>
                        <div class="ticket-header d-flex justify-content-between align-items-center mt-4">
                            <h4 class="mb-0">Status de Aprovação do Ticket</h4>
                            <?php
                            $validationStatus = $validacoes['validation_status'];
                            $statusInfo = array_values(array_filter($validationStatusMapping, function($status) use ($validationStatus) {
                                return $status['id'] === $validationStatus;
                            }))[0] ?? $validationStatusMapping['NONE'];
                            ?>
                            <span class="badge badge-<?php echo $statusInfo['class']; ?>">
                                <?php echo $statusInfo['label']; ?>
                            </span>
                        </div>
                       


                    <?php endif; ?>

                    <?php if (!empty($followUps)): ?>
                        <div class="ticket-header d-flex justify-content-between align-items-center mt-4">
                            <h4 class="mb-0">Acompanhamentos</h4>
                        </div>
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
                                <div class="followup-content">
                                    <?php 
                                    if (isset($followUp['content'])) {
                                        $content = $followUp['content'];
                                        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                        $content = preg_replace('/<\/?p>/', '', $content);
                                        echo $content;
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
</body>
</html>
