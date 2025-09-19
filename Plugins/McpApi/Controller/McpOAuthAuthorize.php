<?php
namespace FacturaScripts\Plugins\McpApi\Controller;

use FacturaScripts\Core\Contract\ControllerInterface;
use FacturaScripts\Core\Request;
use FacturaScripts\Core\Response;
use FacturaScripts\Plugins\McpApi\Lib\OAuth\TokenBroker;

class McpOAuthAuthorize implements ControllerInterface
{
    private Request $request;
    private Response $response;

    public function __construct(string $className, string $url = '')
    {
        $this->request = Request::createFromGlobals();
        $this->response = new Response();
    }

    public function getPageData(): array
    {
        return [];
    }

    public function run(): void
    {
        if ($this->request->isMethod(Request::METHOD_GET)) {
            $this->handleGet();
            return;
        }

        if ($this->request->isMethod(Request::METHOD_POST)) {
            $this->handlePost();
            return;
        }

        $this->response->setHttpCode(Response::HTTP_METHOD_NOT_ALLOWED);
        $this->response->setContent('Method not allowed');
        $this->response->send();
    }

    private function handleGet(string $error = ''): void
    {
        $clientId = $this->request->query('client_id', '');
        $redirectUri = $this->request->query('redirect_uri', '');
        $responseType = $this->request->query('response_type', 'code');
        $state = $this->request->query('state', '');
        $scope = $this->request->query('scope', '');
        $codeChallenge = $this->request->query('code_challenge', '');
        $codeMethod = $this->request->query('code_challenge_method', '');

        if ($clientId === '' || $redirectUri === '' || $responseType !== 'code') {
            $this->response->setHttpCode(Response::HTTP_BAD_REQUEST);
            $this->response->setContent('Missing or invalid OAuth parameters.');
            $this->response->send();
            return;
        }

        $this->renderForm([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => $scope,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeMethod,
        ], $error);
    }

    private function handlePost(): void
    {
        $clientId = $this->request->request->get('client_id', '');
        $redirectUri = $this->request->request->get('redirect_uri', '');
        $state = $this->request->request->get('state', '');
        $scope = $this->request->request->get('scope', '');
        $codeChallenge = $this->request->request->get('code_challenge', '');
        $codeMethod = $this->request->request->get('code_challenge_method', '');
        $apiKey = $this->request->request->get('api_key', '');

        if ($clientId === '' || $redirectUri === '') {
            $this->response->setHttpCode(Response::HTTP_BAD_REQUEST);
            $this->response->setContent('Missing OAuth parameters.');
            $this->response->send();
            return;
        }

        $identity = TokenBroker::validateApiKey($apiKey);
        if (null === $identity) {
            $this->handleGet('La clave API no es válida o está desactivada.');
            return;
        }

        if (!filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            $this->response->setHttpCode(Response::HTTP_BAD_REQUEST);
            $this->response->setContent('redirect_uri must be an absolute URL.');
            $this->response->send();
            return;
        }

        $scopes = array_values(array_filter(preg_split('/\s+/', (string)$scope)));
        $code = TokenBroker::issueAuthorizationCode($clientId, $identity, $redirectUri, $codeChallenge, $codeMethod, $scopes);

        $params = ['code' => $code];
        if ($state !== '') {
            $params['state'] = $state;
        }
        if (!empty($scopes)) {
            $params['scope'] = implode(' ', $scopes);
        }

        $separator = str_contains($redirectUri, '?') ? '&' : '?';
        $location = $redirectUri . $separator . http_build_query($params);

        $this->response->setHttpCode(302);
        $this->response->headers->set('Location', $location);
        $this->response->send();
    }

    private function renderForm(array $data, string $error): void
    {
        $scopeHint = $data['scope'] === '' ? 'Todas las operaciones permitidas por tu clave API.' : htmlspecialchars($data['scope'], ENT_QUOTES, 'UTF-8');
        $errorHtml = $error === '' ? '' : '<p class="error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';

        $html = '<!DOCTYPE html>'
            . '<html lang="es">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<title>Autorizar acceso MCP</title>'
            . '<style>'
            . 'body{font-family:system-ui, sans-serif;max-width:640px;margin:40px auto;padding:0 16px;color:#1f2933;}'
            . 'h1{font-size:1.8rem;margin-bottom:0.5rem;}'
            . 'p{line-height:1.5rem;}'
            . 'form{margin-top:1.5rem;padding:1.5rem;border:1px solid #d9e2ec;border-radius:8px;}'
            . 'label{display:block;font-weight:600;margin-bottom:0.5rem;}'
            . 'input[type="text"],input[type="password"]{width:100%;padding:0.6rem;border:1px solid #bcccdc;border-radius:6px;}'
            . '.error{color:#b91c1c;background:#fee2e2;padding:0.75rem;border-radius:6px;}'
            . 'button{margin-top:1rem;background:#2563eb;color:#fff;padding:0.75rem 1.5rem;border:none;border-radius:6px;font-size:1rem;cursor:pointer;}'
            . 'button:hover{background:#1d4ed8;}'
            . '</style>'
            . '</head>'
            . '<body>'
            . '<h1>Conectar FacturaScripts con un agente MCP</h1>'
            . '<p>Introduce la clave API que quieras delegar. Se generará un token temporal compatible con OAuth 2.0 para que el agente pueda llamar a la API `/api/v3/mcp`.</p>'
            . '<p><strong>Cliente:</strong> ' . htmlspecialchars($data['client_id'], ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><strong>Ámbito solicitado:</strong> ' . $scopeHint . '</p>'
            . $errorHtml
            . '<form method="post">'
            . '<input type="hidden" name="client_id" value="' . htmlspecialchars($data['client_id'], ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="hidden" name="redirect_uri" value="' . htmlspecialchars($data['redirect_uri'], ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="hidden" name="state" value="' . htmlspecialchars($data['state'], ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="hidden" name="scope" value="' . htmlspecialchars($data['scope'], ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="hidden" name="code_challenge" value="' . htmlspecialchars($data['code_challenge'], ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="hidden" name="code_challenge_method" value="' . htmlspecialchars($data['code_challenge_method'], ENT_QUOTES, 'UTF-8') . '">'
            . '<label for="api_key">Clave API de FacturaScripts</label>'
            . '<input type="password" id="api_key" name="api_key" required placeholder="Introduce tu clave API">'
            . '<button type="submit">Autorizar</button>'
            . '</form>'
            . '<p style="margin-top:1.5rem;font-size:0.9rem;color:#52606d;">Puedes crear nuevas claves API desde Administrador → Usuarios → API.</p>'
            . '</body>'
            . '</html>';

        $this->response->setContent($html);
        $this->response->send();
    }
}
