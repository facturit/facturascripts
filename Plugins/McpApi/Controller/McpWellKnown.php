<?php
namespace FacturaScripts\Plugins\McpApi\Controller;

use FacturaScripts\Core\Contract\ControllerInterface;
use FacturaScripts\Core\Request;
use FacturaScripts\Core\Response;
use FacturaScripts\Dinamic\Lib\API\Mcp;

class McpWellKnown implements ControllerInterface
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
        $host = $this->request->host();
        $scheme = $this->detectScheme();
        $baseUrl = $host === '' ? '' : $scheme . '://' . $host;

        $manifest = [
            'name' => 'facturascripts-mcp',
            'version' => '2.0',
            'description' => 'Model Context Protocol bridge that documents and proxies the FacturaScripts REST API.',
            'protocol' => [
                'spec_version' => Mcp::SPEC_VERSION,
                'transport' => 'jsonrpc-2.0',
            ],
            'endpoints' => [
                'rpc' => $baseUrl . '/api/v3/mcp',
                'resources' => $baseUrl . '/api/v3/mcp/resources',
                'custom_resources' => $baseUrl . '/api/v3/mcp/custom',
                'oauth_authorize' => $baseUrl . '/mcp/oauth/authorize',
                'oauth_token' => $baseUrl . '/mcp/oauth/token',
            ],
            'authentication' => [
                'type' => 'oauth2',
                'authorization_header' => 'Authorization: Bearer <token>',
                'notes' => 'Complete the OAuth 2.0 authorization code flow to wrap a FacturaScripts API key into a one-hour Bearer token.',
            ],
            'capabilities' => [
                'session_ttl_seconds' => Mcp::SESSION_TTL,
                'rpc_methods' => [
                    'session/create',
                    'session/refresh',
                    'session/close',
                    'resources/list',
                    'resources/get',
                    'resources/schema',
                    'resources/query',
                    'resources/custom',
                    'tools/list',
                    'tools/call',
                ],
                'tools' => [
                    'model_query',
                    'model_mutation',
                ],
            ],
        ];

        $this->response->json($manifest);
    }

    private function detectScheme(): string
    {
        $forwarded = $this->request->header('X-Forwarded-Proto');
        if (is_string($forwarded) && $forwarded !== '') {
            $parts = explode(',', $forwarded);
            return strtolower(trim($parts[0]));
        }

        $https = $this->request->header('HTTPS');
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return 'https';
        }

        return 'http';
    }
}
