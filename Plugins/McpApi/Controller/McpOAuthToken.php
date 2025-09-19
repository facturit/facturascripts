<?php
namespace FacturaScripts\Plugins\McpApi\Controller;

use FacturaScripts\Core\Contract\ControllerInterface;
use FacturaScripts\Core\Request;
use FacturaScripts\Core\Response;
use FacturaScripts\Plugins\McpApi\Lib\OAuth\TokenBroker;

class McpOAuthToken implements ControllerInterface
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
        if (!$this->request->isMethod(Request::METHOD_POST)) {
            $this->response->setHttpCode(Response::HTTP_METHOD_NOT_ALLOWED);
            $this->response->setContent('Method not allowed');
            $this->response->send();
            return;
        }

        $grantType = $this->request->request->get('grant_type', '');
        if ($grantType !== 'authorization_code') {
            $this->respondError('unsupported_grant_type', 'Only the authorization_code grant is available.');
            return;
        }

        $code = $this->request->request->get('code', '');
        $clientId = $this->request->request->get('client_id', '');
        $redirectUri = $this->request->request->get('redirect_uri', '');
        $codeVerifier = $this->request->request->get('code_verifier');

        if ($code === '' || $clientId === '' || $redirectUri === '') {
            $this->respondError('invalid_request', 'code, client_id and redirect_uri are required.');
            return;
        }

        $token = TokenBroker::exchangeCode($code, $clientId, $redirectUri, $codeVerifier === '' ? null : $codeVerifier);
        if (null === $token) {
            $this->respondError('invalid_grant', 'The authorization code is invalid or has expired.');
            return;
        }

        $this->response->headers->set('Cache-Control', 'no-store');
        $this->response->headers->set('Pragma', 'no-cache');
        $this->response->json($token);
    }

    private function respondError(string $error, string $description): void
    {
        $this->response->setHttpCode(Response::HTTP_BAD_REQUEST);
        $this->response->json([
            'error' => $error,
            'error_description' => $description,
        ]);
    }
}
