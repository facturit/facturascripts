<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2025 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Dinamic\Lib\API;

use Exception;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\MiniLog;
use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Controller\ApiRoot;
use FacturaScripts\Core\Lib\API\APIModel;
use FacturaScripts\Core\Lib\API\Base\APIResourceClass;
use FacturaScripts\Core\Response;
use FacturaScripts\Core\Template\ApiController;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\McpApi\Lib\Mcp\RpcException;

/**
 * MCP discovery endpoint for the FacturaScripts REST API.
 */
class Mcp extends APIResourceClass
{
    private const RESOURCE_NAME = 'mcp';
    public const SPEC_VERSION = '1.0.0';
    private const JSON_RPC_VERSION = '2.0';
    public const SESSION_TTL = 3600;
    private const MAX_QUERY_LIMIT = 200;
    private const ERROR_PARSE_ERROR = -32700;
    private const ERROR_INVALID_REQUEST = -32600;
    private const ERROR_METHOD_NOT_FOUND = -32601;
    private const ERROR_INVALID_PARAMS = -32602;
    private const ERROR_INTERNAL_ERROR = -32603;
    private const ERROR_RESOURCE_NOT_FOUND = -32004;
    private const ERROR_OPERATION_FAILED = -32010;
    private const HTTP_NO_CONTENT = 204;

    /** @var array|null */
    private $cachedResources;

    public function getResources(): array
    {
        return [self::RESOURCE_NAME => $this->setResource('Mcp')];
    }

    public function doGET(): bool
    {
        if (empty($this->params)) {
            $this->returnResult($this->buildIndexDocument());
            return true;
        }

        $section = array_shift($this->params);
        switch ($section) {
            case 'resources':
                return $this->respondWithResources();

            case 'custom':
                $this->returnResult(['resources' => $this->buildCustomResources()]);
                return true;

            default:
                $this->setError(Tools::trans('record-not-found'), null, Response::HTTP_NOT_FOUND);
                return false;
        }
    }

    public function doPOST(): bool
    {
        $rawInput = trim($this->request->rawInput());
        if ($rawInput === '') {
            $response = $this->buildRpcError(null, self::ERROR_PARSE_ERROR, 'Empty request body.');
            $this->response->json($response);
            return true;
        }

        $payload = json_decode($rawInput, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            $response = $this->buildRpcError(null, self::ERROR_PARSE_ERROR, 'Invalid JSON payload: ' . json_last_error_msg());
            $this->response->json($response);
            return true;
        }

        if (!is_array($payload)) {
            $response = $this->buildRpcError(null, self::ERROR_INVALID_REQUEST, 'Request payload must be a JSON object or array.');
            $this->response->json($response);
            return true;
        }

        $result = $this->handleJsonRpcPayload($payload);
        if (null === $result) {
            $this->response
                ->setHttpCode(self::HTTP_NO_CONTENT)
                ->setContent('');
            $this->response->send();
            return true;
        }

        $this->response->json($result);
        return true;
    }

    private function buildCustomResources(): array
    {
        $resources = [];
        foreach (ApiRoot::getCustomResources() as $resourceName) {
            if ($resourceName === self::RESOURCE_NAME) {
                continue;
            }

            $resources[] = [
                'name' => $resourceName,
                'endpoint' => $this->getAbsoluteUrl($this->buildEndpoint($resourceName)),
                'notes' => 'Recurso especial gestionado por un controlador Api*. Revisa el código del controlador correspondiente para conocer parámetros y comportamiento.',
            ];
        }

        return $resources;
    }

    private function buildIndexDocument(): array
    {
        $basePath = $this->getBasePath();
        $resources = $this->getDiscoverableResources();

        return [
            'name' => 'facturascripts-mcp',
            'description' => 'Punto de descubrimiento MCP que describe la API REST de FacturaScripts para agentes de IA.',
            'spec_version' => self::SPEC_VERSION,
            'api_version' => ApiController::API_VERSION,
            'base_path' => $basePath,
            'base_url' => $this->getAbsoluteUrl($basePath),
            'resource_count' => count($resources),
            'rpc_endpoint' => [
                'url' => $this->getAbsoluteUrl($basePath . '/' . self::RESOURCE_NAME),
                'transport' => 'jsonrpc-2.0',
                'notes' => 'Envía peticiones JSON-RPC 2.0 para acceder a métodos como session/create, resources/query o tools/call.',
            ],
            'links' => [
                [
                    'rel' => 'resources',
                    'href' => $this->getAbsoluteUrl($basePath . '/' . self::RESOURCE_NAME . '/resources'),
                    'description' => 'Listado de recursos de modelo y sus operaciones.',
                ],
                [
                    'rel' => 'custom-resources',
                    'href' => $this->getAbsoluteUrl($basePath . '/' . self::RESOURCE_NAME . '/custom'),
                    'description' => 'Recursos API adicionales gestionados por controladores específicos.',
                ],
            ],
            'authentication' => [
                'type' => 'api-key',
                'header' => 'X-Auth-Token',
                'alternate_header' => 'Token',
                'notes' => 'Genera una clave en Administrador > Usuarios > API y envíala en la cabecera X-Auth-Token. También se acepta el encabezado Token.',
            ],
            'usage' => [
                'filtering' => 'Utiliza filter[campo]=valor para filtrar resultados. Añade sufijos _like, _gt, _gte, _lt, _lte, _neq, _null o _notnull para operadores avanzados.',
                'sorting' => 'Ordena con sort[campo]=ASC o DESC.',
                'pagination' => 'Controla la paginación con limit (por defecto 50) y offset.',
                'operation' => 'Combina filtros con operation[campo]=OR cuando necesites condiciones OR. Por defecto se utiliza AND.',
                'rpc_methods' => [
                    'session/create',
                    'session/refresh',
                    'resources/list',
                    'resources/get',
                    'resources/query',
                    'tools/list',
                    'tools/call'
                ],
            ],
        ];
    }

    private function buildEndpoint(string $resourceName): string
    {
        return $this->getBasePath() . '/' . $resourceName;
    }

    private function buildModelOperations(string $resourceName, string $modelName): array
    {
        $primaryKey = $this->resolvePrimaryKey($modelName) ?? 'id';
        $endpoint = $this->getAbsoluteUrl($this->buildEndpoint($resourceName));
        $rpcEndpoint = $this->getAbsoluteUrl($this->buildEndpoint(self::RESOURCE_NAME));

        return [
            [
                'name' => 'list',
                'method' => 'GET',
                'path' => $endpoint,
                'description' => 'Lista registros del recurso.',
                'query' => $this->getQueryDocumentation(),
            ],
            [
                'name' => 'retrieve',
                'method' => 'GET',
                'path' => $endpoint . '/{' . $primaryKey . '}',
                'description' => 'Obtiene un registro concreto por su identificador.',
            ],
            [
                'name' => 'schema',
                'method' => 'GET',
                'path' => $endpoint . '/schema',
                'description' => 'Devuelve la definición de campos del recurso.',
            ],
            [
                'name' => 'create',
                'method' => 'POST',
                'path' => $endpoint,
                'description' => 'Crea un nuevo registro. Envía los campos del modelo como JSON o formulario.',
            ],
            [
                'name' => 'update',
                'method' => 'PUT',
                'path' => $endpoint . '/{' . $primaryKey . '}',
                'description' => 'Actualiza un registro existente. Incluye el identificador en la ruta o en el cuerpo.',
            ],
            [
                'name' => 'delete',
                'method' => 'DELETE',
                'path' => $endpoint . '/{' . $primaryKey . '}',
                'description' => 'Elimina un registro existente.',
            ],
            [
                'name' => 'rpc-query',
                'method' => 'RPC',
                'path' => $rpcEndpoint,
                'description' => 'Invoca JSON-RPC resources/query con {"resource": "' . $resourceName . '"}.',
            ],
        ];
    }

    private function buildModelSchema(string $modelName): array
    {
        $class = '\\FacturaScripts\\Dinamic\\Model\\' . $modelName;
        if (!class_exists($class)) {
            return [];
        }

        try {
            $model = new $class();
        } catch (Exception $exception) {
            Tools::log()->warning('Unable to instantiate model for MCP schema: ' . $modelName . ' -> ' . $exception->getMessage());
            return [];
        }

        $schema = [];
        foreach ($model->getModelFields() as $fieldName => $definition) {
            $schema[$fieldName] = [
                'type' => $definition['type'],
                'length' => $definition['length'] ?? null,
                'nullable' => (bool)($definition['is_nullable'] ?? false),
                'default' => $definition['default'],
            ];
        }

        return $schema;
    }

    private function buildResourceDefinition(string $resourceName, array $info, bool $includeSchemaFields = true): ?array
    {
        $type = $this->resolveResourceType($info['API']);
        $endpoint = $this->getAbsoluteUrl($this->buildEndpoint($resourceName));

        $definition = [
            'name' => $resourceName,
            'type' => $type,
            'endpoint' => $endpoint,
            'provider' => $info['API'],
        ];

        if ($type === 'model') {
            $definition['model'] = $info['Name'];
            $definition['primary_key'] = $this->resolvePrimaryKey($info['Name']);
            $definition['operations'] = $this->buildModelOperations($resourceName, $info['Name']);
            $definition['schema'] = [
                'url' => $this->getAbsoluteUrl($this->buildEndpoint($resourceName) . '/schema'),
            ];

            if ($includeSchemaFields) {
                $definition['schema']['fields'] = $this->buildModelSchema($info['Name']);
            }
        } else {
            $definition['operations'] = $this->buildCustomOperations($info['API'], $resourceName);
        }

        return $definition;
    }

    private function buildResourcesSummary(): array
    {
        $summary = [];
        foreach ($this->getDiscoverableResources() as $resourceName => $info) {
            $definition = $this->buildResourceDefinition($resourceName, $info, false);
            if (null === $definition) {
                continue;
            }

            $item = [
                'name' => $definition['name'],
                'type' => $definition['type'],
                'endpoint' => $definition['endpoint'],
                'operations' => array_map(function (array $operation): string {
                    return $operation['method'] . ' ' . $operation['path'];
                }, $definition['operations']),
            ];

            if (isset($definition['model'])) {
                $item['model'] = $definition['model'];
                $item['schema'] = $definition['schema']['url'] ?? null;
            }

            $summary[] = $item;
        }

        return $summary;
    }

    private function buildCustomOperations(string $apiClass, string $resourceName): array
    {
        $endpoint = $this->getAbsoluteUrl($this->buildEndpoint($resourceName));
        $operations = [];
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $methodName = 'do' . $method;
            if (!method_exists($apiClass, $methodName)) {
                continue;
            }

            $operations[] = [
                'name' => strtolower($method),
                'method' => $method,
                'path' => $endpoint,
                'description' => 'Consulta el controlador ' . $apiClass . ' para conocer parámetros y formato.',
            ];
        }

        if (empty($operations)) {
            $operations[] = [
                'name' => 'run',
                'method' => 'CUSTOM',
                'path' => $endpoint,
                'description' => 'El controlador ' . $apiClass . ' gestiona este recurso con una lógica específica.',
            ];
        }

        return $operations;
    }

    private function getAbsoluteUrl(string $path): string
    {
        $host = $this->request->host();
        if (empty($host)) {
            return $path;
        }

        $scheme = $this->request->isSecure() ? 'https' : 'http';
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return $scheme . '://' . $host . $path;
    }

    private function getBasePath(): string
    {
        return '/api/v' . ApiController::API_VERSION;
    }

    private function getDiscoverableResources(): array
    {
        $resources = $this->getResourcesMap();
        unset($resources[self::RESOURCE_NAME]);
        return $resources;
    }

    private function getQueryDocumentation(): array
    {
        return [
            'filter[campo]' => 'Filtra resultados. Permite sufijos _like, _gt, _gte, _lt, _lte, _neq, _null, _notnull.',
            'operation[campo]' => 'Sobrescribe la operación lógica (AND/OR) para filtros específicos.',
            'sort[campo]' => 'Ordena por el campo indicado con valores ASC o DESC.',
            'limit' => 'Número máximo de filas devueltas (por defecto 50).',
            'offset' => 'Desplazamiento inicial para paginación.',
        ];
    }

    private function getResourcesMap(): array
    {
        if (null !== $this->cachedResources) {
            return $this->cachedResources;
        }

        $resources = [];
        $folder = Tools::folder('Dinamic', 'Lib', 'API');
        if (!is_dir($folder)) {
            $this->cachedResources = [];
            return $this->cachedResources;
        }

        foreach (Tools::folderScan($folder, false) as $fileName) {
            if (substr($fileName, -4) !== '.php') {
                continue;
            }

            $className = substr('\\FacturaScripts\\Dinamic\\Lib\\API\\' . $fileName, 0, -4);
            if (!class_exists($className)) {
                continue;
            }

            $apiClass = new $className($this->response, $this->request, []);
            if (!method_exists($apiClass, 'getResources')) {
                continue;
            }

            foreach ($apiClass->getResources() as $resourceName => $definition) {
                $resources[$resourceName] = $definition;
            }
        }

        $this->cachedResources = $resources;
        return $this->cachedResources;
    }

    private function resolvePrimaryKey(string $modelName): ?string
    {
        $class = '\\FacturaScripts\\Dinamic\\Model\\' . $modelName;
        if (!class_exists($class)) {
            return null;
        }

        try {
            $model = new $class();
        } catch (Exception $exception) {
            Tools::log()->warning('Unable to instantiate model for MCP primary key: ' . $modelName . ' -> ' . $exception->getMessage());
            return null;
        }

        return $model->primaryColumn();
    }

    private function resolveResourceType(string $apiClass): string
    {
        return is_a($apiClass, APIModel::class, true) ? 'model' : 'custom';
    }

    private function respondWithResources(): bool
    {
        if (empty($this->params)) {
            $this->returnResult(['resources' => $this->buildResourcesSummary()]);
            return true;
        }

        $resourceName = array_shift($this->params);
        if ('schema' === ($this->params[0] ?? '')) {
            return $this->respondWithSchema($resourceName);
        }

        $resources = $this->getResourcesMap();
        if (!isset($resources[$resourceName])) {
            $this->setError(Tools::trans('record-not-found'), null, Response::HTTP_NOT_FOUND);
            return false;
        }

        $definition = $this->buildResourceDefinition($resourceName, $resources[$resourceName]);
        if (null === $definition) {
            $this->setError('resource-definition-error');
            return false;
        }

        $this->returnResult($definition);
        return true;
    }

    private function respondWithSchema(string $resourceName): bool
    {
        $resources = $this->getResourcesMap();
        if (!isset($resources[$resourceName])) {
            $this->setError(Tools::trans('record-not-found'), null, Response::HTTP_NOT_FOUND);
            return false;
        }

        $info = $resources[$resourceName];
        if ($this->resolveResourceType($info['API']) !== 'model') {
            $this->setError('schema-not-available', null, Response::HTTP_BAD_REQUEST);
            return false;
        }

        $fields = $this->buildModelSchema($info['Name']);
        $this->returnResult([
            'name' => $resourceName,
            'model' => $info['Name'],
            'schema' => $fields,
        ]);
        return true;
    }

    /**
     * Procesa una petición JSON-RPC, compatible con peticiones individuales o lotes.
     *
     * @param array $payload
     *
     * @return array|null
     */
    private function handleJsonRpcPayload(array $payload): ?array
    {
        if ($this->isSequentialArray($payload)) {
            if (empty($payload)) {
                return [$this->buildRpcError(null, self::ERROR_INVALID_REQUEST, 'Batch must contain at least one request.')];
            }

            $responses = [];
            foreach ($payload as $entry) {
                if (!is_array($entry)) {
                    $responses[] = $this->buildRpcError(null, self::ERROR_INVALID_REQUEST, 'Each batch entry must be an object.');
                    continue;
                }

                $response = $this->handleJsonRpcRequest($entry);
                if (null !== $response) {
                    $responses[] = $response;
                }
            }

            return empty($responses) ? null : $responses;
        }

        return $this->handleJsonRpcRequest($payload);
    }

    private function handleJsonRpcRequest(array $request): ?array
    {
        $hasId = array_key_exists('id', $request);
        $id = $hasId ? $request['id'] : null;
        $jsonrpc = (string)($request['jsonrpc'] ?? '');
        $method = (string)($request['method'] ?? '');

        if ($jsonrpc !== self::JSON_RPC_VERSION || $method === '') {
            return $this->buildRpcError($id, self::ERROR_INVALID_REQUEST, 'Invalid JSON-RPC request.');
        }

        $params = $request['params'] ?? [];
        if (!is_array($params)) {
            return $this->buildRpcError($id, self::ERROR_INVALID_PARAMS, 'Params must be an object or array.');
        }

        try {
            $result = $this->executeRpcMethod(str_replace('.', '/', strtolower($method)), $params);
            if (!$hasId) {
                return null;
            }

            return $this->buildRpcResult($id, $result);
        } catch (RpcException $exception) {
            if (!$hasId) {
                Tools::log()->warning('mcp-rpc-notification-error', [
                    'method' => $method,
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                ]);
                return null;
            }

            return $this->buildRpcError($id, $exception->getCode(), $exception->getMessage(), $exception->getData());
        } catch (Exception $exception) {
            Tools::log()->warning('mcp-rpc-exception', ['method' => $method, 'message' => $exception->getMessage()]);
            if (!$hasId) {
                return null;
            }

            return $this->buildRpcError($id, self::ERROR_INTERNAL_ERROR, 'Internal error.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function buildRpcError($id, int $code, string $message, $data = null): array
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if (null !== $data) {
            $error['data'] = $data;
        }

        return [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id' => $id,
            'error' => $error,
        ];
    }

    private function buildRpcResult($id, $result): array
    {
        return [
            'jsonrpc' => self::JSON_RPC_VERSION,
            'id' => $id,
            'result' => $result,
        ];
    }

    private function executeRpcMethod(string $method, array $params)
    {
        switch ($method) {
            case 'ping':
            case 'server/ping':
                return ['pong' => true];

            case 'server/info':
                return $this->buildIndexDocument();

            case 'session/create':
                return $this->createSession($params);

            case 'session/refresh':
                return $this->refreshSession($params);

            case 'session/close':
                return $this->closeSession($params);

            case 'resources/list':
                return ['resources' => $this->buildResourcesSummary()];

            case 'resources/get':
                $resource = (string)($params['name'] ?? $params['resource'] ?? '');
                if ($resource === '') {
                    throw new RpcException(self::ERROR_INVALID_PARAMS, 'Missing resource name.');
                }

                [$resourceKey, $info] = $this->getResourceEntry($resource);
                $definition = $this->buildResourceDefinition($resourceKey, $info);
                if (null === $definition) {
                    throw new RpcException(self::ERROR_RESOURCE_NOT_FOUND, 'Resource definition unavailable.', ['resource' => $resource]);
                }

                return $definition;

            case 'resources/schema':
                $resource = (string)($params['name'] ?? $params['resource'] ?? '');
                if ($resource === '') {
                    throw new RpcException(self::ERROR_INVALID_PARAMS, 'Missing resource name.');
                }

                [$resourceKey, $info] = $this->ensureModelResource($resource);
                $fields = $this->buildModelSchema($info['Name']);

                return [
                    'name' => $resourceKey,
                    'model' => $info['Name'],
                    'primary_key' => $this->resolvePrimaryKey($info['Name']),
                    'schema' => $fields,
                ];

            case 'resources/custom':
                return ['resources' => $this->buildCustomResources()];

            case 'resources/query':
                $resource = (string)($params['resource'] ?? $params['name'] ?? '');
                if ($resource === '') {
                    throw new RpcException(self::ERROR_INVALID_PARAMS, 'Missing resource name.');
                }

                return $this->runModelQuery($resource, $params);

            case 'tools/list':
                return ['tools' => $this->describeTools()];

            case 'tools/call':
                return $this->handleToolCall($params);

            default:
                throw new RpcException(self::ERROR_METHOD_NOT_FOUND, 'Method not found.', ['method' => $method]);
        }
    }

    private function createSession(array $params): array
    {
        $requestedId = (string)($params['session_id'] ?? '');
        if ($requestedId !== '') {
            $session = $this->getSessionData($requestedId);
            if (!empty($session)) {
                return $this->buildSessionResult($requestedId, $session);
            }
        }

        $sessionId = $this->generateSessionId();
        $session = [
            'id' => $sessionId,
            'created_at' => gmdate('c'),
            'last_seen_at' => gmdate('c'),
            'expires_at' => gmdate('c', time() + self::SESSION_TTL),
        ];

        $this->persistSession($sessionId, $session);

        return $this->buildSessionResult($sessionId, $session);
    }

    private function refreshSession(array $params): array
    {
        $sessionId = (string)($params['session_id'] ?? '');
        if ($sessionId === '') {
            throw new RpcException(self::ERROR_INVALID_PARAMS, 'Missing session identifier.');
        }

        $session = $this->getSessionData($sessionId);
        if (empty($session)) {
            throw new RpcException(self::ERROR_RESOURCE_NOT_FOUND, 'Session not found.', ['session_id' => $sessionId]);
        }

        $session['last_seen_at'] = gmdate('c');
        $session['expires_at'] = gmdate('c', time() + self::SESSION_TTL);
        $this->persistSession($sessionId, $session);

        return $this->buildSessionResult($sessionId, $session);
    }

    private function closeSession(array $params): array
    {
        $sessionId = (string)($params['session_id'] ?? '');
        if ($sessionId === '') {
            throw new RpcException(self::ERROR_INVALID_PARAMS, 'Missing session identifier.');
        }

        Cache::delete($this->buildSessionKey($sessionId));

        return [
            'session' => [
                'id' => $sessionId,
                'closed_at' => gmdate('c'),
            ],
        ];
    }

    private function buildSessionResult(string $sessionId, array $session): array
    {
        return [
            'session' => [
                'id' => $sessionId,
                'created_at' => $session['created_at'] ?? gmdate('c'),
                'last_seen_at' => $session['last_seen_at'] ?? gmdate('c'),
                'expires_at' => $session['expires_at'] ?? gmdate('c', time() + self::SESSION_TTL),
            ],
            'capabilities' => [
                'transport' => [
                    'type' => 'http-jsonrpc',
                    'endpoint' => $this->getAbsoluteUrl($this->buildEndpoint(self::RESOURCE_NAME)),
                ],
                'resources' => [
                    'list' => true,
                    'get' => true,
                    'schema' => true,
                    'query' => true,
                    'custom' => true,
                ],
                'tools' => [
                    'model_query' => true,
                    'model_mutation' => true,
                ],
            ],
            'links' => [
                'resources' => $this->getAbsoluteUrl($this->buildEndpoint(self::RESOURCE_NAME) . '/resources'),
                'custom' => $this->getAbsoluteUrl($this->buildEndpoint(self::RESOURCE_NAME) . '/custom'),
            ],
        ];
    }

    private function generateSessionId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Exception $exception) {
            return uniqid('mcp-', true);
        }
    }

    private function getSessionData(string $sessionId): array
    {
        $data = Cache::get($this->buildSessionKey($sessionId));
        if (!is_array($data)) {
            return [];
        }

        $expiresAt = $data['expires_at'] ?? null;
        if (null !== $expiresAt) {
            $timestamp = strtotime((string)$expiresAt);
            if (false !== $timestamp && $timestamp <= time()) {
                Cache::delete($this->buildSessionKey($sessionId));
                return [];
            }
        }

        return $data;
    }

    private function persistSession(string $sessionId, array $session): void
    {
        Cache::set($this->buildSessionKey($sessionId), $session);
    }

    private function buildSessionKey(string $sessionId): string
    {
        return 'mcp-session-' . $sessionId;
    }

    private function describeTools(): array
    {
        return [
            [
                'name' => 'model_query',
                'description' => 'Consulta registros de un recurso de modelo utilizando filtros, ordenaciones y paginación.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['resource'],
                    'properties' => [
                        'resource' => [
                            'type' => 'string',
                            'description' => 'Nombre del recurso (plural) tal y como aparece en resources/list.',
                        ],
                        'filter' => [
                            'type' => 'object',
                            'additionalProperties' => true,
                        ],
                        'operation' => [
                            'type' => 'object',
                            'additionalProperties' => [
                                'type' => 'string',
                                'enum' => ['AND', 'OR'],
                            ],
                        ],
                        'sort' => [
                            'type' => 'object',
                            'additionalProperties' => [
                                'type' => 'string',
                            ],
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'maximum' => self::MAX_QUERY_LIMIT,
                        ],
                        'offset' => [
                            'type' => 'integer',
                            'minimum' => 0,
                        ],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'resource' => ['type' => 'string'],
                        'model' => ['type' => 'string'],
                        'primary_key' => ['type' => 'string'],
                        'rows' => [
                            'type' => 'array',
                            'items' => ['type' => 'object'],
                        ],
                        'total' => ['type' => 'integer'],
                        'limit' => ['type' => 'integer'],
                        'offset' => ['type' => 'integer'],
                    ],
                ],
            ],
            [
                'name' => 'model_mutation',
                'description' => 'Crea, actualiza, recupera o elimina registros de un recurso de modelo.',
                'input_schema' => [
                    'type' => 'object',
                    'required' => ['resource', 'operation'],
                    'properties' => [
                        'resource' => [
                            'type' => 'string',
                            'description' => 'Nombre del recurso sobre el que se ejecutará la mutación.',
                        ],
                        'operation' => [
                            'type' => 'string',
                            'enum' => ['create', 'update', 'delete', 'retrieve'],
                        ],
                        'record' => [
                            'type' => 'object',
                            'description' => 'Datos del registro para create/update.',
                        ],
                        'id' => [
                            'type' => ['string', 'number'],
                            'description' => 'Identificador del registro cuando aplique.',
                        ],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string'],
                        'record' => ['type' => ['object', 'null']],
                    ],
                ],
            ],
        ];
    }

    private function handleToolCall(array $params): array
    {
        $toolName = (string)($params['name'] ?? $params['tool'] ?? '');
        if ($toolName === '') {
            throw new RpcException(self::ERROR_INVALID_PARAMS, 'Missing tool name.');
        }

        $arguments = $params['arguments'] ?? $params['params'] ?? [];
        if (!is_array($arguments)) {
            throw new RpcException(self::ERROR_INVALID_PARAMS, 'Tool arguments must be an object or array.', ['tool' => $toolName]);
        }

        switch (strtolower($toolName)) {
            case 'model_query':
                $resource = (string)($arguments['resource'] ?? '');
                if ($resource === '') {
                    throw new RpcException(self::ERROR_INVALID_PARAMS, 'Missing resource parameter.', ['tool' => $toolName]);
                }

                $result = $this->runModelQuery($resource, $arguments);
                return [
                    'tool' => $toolName,
                    'result' => $result,
                ];

            case 'model_mutation':
                $resource = (string)($arguments['resource'] ?? '');
                $operation = strtolower((string)($arguments['operation'] ?? ''));
                if ($resource === '' || $operation === '') {
                    throw new RpcException(self::ERROR_INVALID_PARAMS, 'Missing resource or operation parameter.', ['tool' => $toolName]);
                }

                $record = $arguments['record'] ?? $arguments['data'] ?? [];
                if (!is_array($record)) {
                    $record = [];
                }

                switch ($operation) {
                    case 'create':
                        $created = $this->createModelRecord($resource, $record);
                        return [
                            'tool' => $toolName,
                            'result' => [
                                'operation' => 'create',
                                'record' => $created,
                            ],
                        ];

                    case 'update':
                        $updated = $this->updateModelRecord($resource, array_merge($arguments, ['record' => $record]));
                        return [
                            'tool' => $toolName,
                            'result' => [
                                'operation' => 'update',
                                'record' => $updated,
                            ],
                        ];

                    case 'delete':
                        $deleted = $this->deleteModelRecord($resource, $arguments);
                        return [
                            'tool' => $toolName,
                            'result' => [
                                'operation' => 'delete',
                                'record' => $deleted,
                            ],
                        ];

                    case 'retrieve':
                        $fetched = $this->retrieveModelRecord($resource, $arguments);
                        return [
                            'tool' => $toolName,
                            'result' => [
                                'operation' => 'retrieve',
                                'record' => $fetched,
                            ],
                        ];

                    default:
                        throw new RpcException(self::ERROR_INVALID_PARAMS, 'Unsupported mutation operation.', [
                            'tool' => $toolName,
                            'operation' => $operation,
                        ]);
                }

            default:
                throw new RpcException(self::ERROR_METHOD_NOT_FOUND, 'Tool not found.', ['tool' => $toolName]);
        }
    }

    private function runModelQuery(string $resourceName, array $params): array
    {
        [$resourceKey, $info] = $this->ensureModelResource($resourceName);
        $model = $this->instantiateModel($info['Name']);
        $modelClass = get_class($model);
        $primaryKey = $this->resolvePrimaryKey($info['Name']) ?? 'id';

        $identifier = $this->resolveRecordIdentifier($params, $primaryKey, false);
        if (null !== $identifier) {
            $recordModel = $this->instantiateModel($info['Name']);
            if (false === $recordModel->loadFromCode($identifier)) {
                return [
                    'resource' => $resourceKey,
                    'model' => $info['Name'],
                    'primary_key' => $primaryKey,
                    'rows' => [],
                    'total' => 0,
                    'limit' => 1,
                    'offset' => 0,
                ];
            }

            return [
                'resource' => $resourceKey,
                'model' => $info['Name'],
                'primary_key' => $primaryKey,
                'rows' => [$recordModel->toArray()],
                'total' => 1,
                'limit' => 1,
                'offset' => 0,
            ];
        }

        $filter = $this->normalizeFilterInput($params['filter'] ?? []);
        $operation = $this->normalizeOperationInput($params['operation'] ?? []);
        $sort = $this->normalizeSort($params['sort'] ?? []);
        $limit = $this->normalizeLimit($params['limit'] ?? 50);
        $offset = max(0, (int)($params['offset'] ?? 0));

        $where = $this->buildWhereFromFilters($filter, $operation);

        $rows = [];
        foreach ($modelClass::all($where, $sort, $offset, $limit) as $record) {
            $rows[] = $record->toArray();
        }

        $total = $modelClass::count($where);

        return [
            'resource' => $resourceKey,
            'model' => $info['Name'],
            'primary_key' => $primaryKey,
            'rows' => $rows,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    private function ensureModelResource(string $resourceName): array
    {
        [$resourceKey, $info] = $this->getResourceEntry($resourceName);
        if ($this->resolveResourceType($info['API']) !== 'model') {
            throw new RpcException(self::ERROR_INVALID_PARAMS, 'Resource is not a model resource.', ['resource' => $resourceName]);
        }

        return [$resourceKey, $info];
    }

    private function getResourceEntry(string $resourceName): array
    {
        $resources = $this->getResourcesMap();
        if (isset($resources[$resourceName])) {
            return [$resourceName, $resources[$resourceName]];
        }

        foreach ($resources as $key => $info) {
            if (strcasecmp($key, $resourceName) === 0) {
                return [$key, $info];
            }

            if (isset($info['Name']) && strcasecmp($info['Name'], $resourceName) === 0) {
                return [$key, $info];
            }
        }

        throw new RpcException(self::ERROR_RESOURCE_NOT_FOUND, 'Resource not found.', ['resource' => $resourceName]);
    }

    private function instantiateModel(string $modelName): ModelClass
    {
        $class = '\\FacturaScripts\\Dinamic\\Model\\' . $modelName;
        if (!class_exists($class)) {
            throw new RpcException(self::ERROR_INTERNAL_ERROR, 'Model class not found.', ['model' => $modelName]);
        }

        $model = new $class();
        if (!$model instanceof ModelClass) {
            throw new RpcException(self::ERROR_INTERNAL_ERROR, 'Invalid model class.', ['model' => $modelName]);
        }

        return $model;
    }

    private function normalizeFilterInput($filters): array
    {
        if (!is_array($filters)) {
            return [];
        }

        $normalized = [];
        foreach ($filters as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function normalizeOperationInput($operations): array
    {
        if (!is_array($operations)) {
            return [];
        }

        $normalized = [];
        foreach ($operations as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            $normalized[$key] = strtoupper($value) === 'OR' ? 'OR' : 'AND';
        }

        return $normalized;
    }

    private function normalizeSort($sort): array
    {
        if (!is_array($sort)) {
            return [];
        }

        $normalized = [];
        foreach ($sort as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $normalized[$value] = 'ASC';
                continue;
            }

            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            $direction = strtoupper($value) === 'DESC' ? 'DESC' : 'ASC';
            $normalized[$key] = $direction;
        }

        return $normalized;
    }

    private function normalizeLimit($limit): int
    {
        $value = (int)$limit;
        if ($value <= 0) {
            $value = 50;
        }

        return min($value, self::MAX_QUERY_LIMIT);
    }

    private function buildWhereFromFilters(array $filter, array $operation, string $defaultOperation = 'AND'): array
    {
        $where = [];
        foreach ($filter as $key => $value) {
            $field = $key;
            $operator = '=';

            switch (substr($key, -3)) {
                case '_gt':
                    $field = substr($key, 0, -3);
                    $operator = '>';
                    break;

                case '_is':
                    $field = substr($key, 0, -3);
                    $operator = 'IS';
                    break;

                case '_lt':
                    $field = substr($key, 0, -3);
                    $operator = '<';
                    break;
            }

            switch (substr($key, -4)) {
                case '_gte':
                    $field = substr($key, 0, -4);
                    $operator = '>=';
                    break;

                case '_lte':
                    $field = substr($key, 0, -4);
                    $operator = '<=';
                    break;

                case '_neq':
                    $field = substr($key, 0, -4);
                    $operator = '!=';
                    break;
            }

            if (substr($key, -5) == '_null') {
                $field = substr($key, 0, -5);
                $operator = 'IS';
                $value = null;
            } elseif (substr($key, -8) == '_notnull') {
                $field = substr($key, 0, -8);
                $operator = 'IS NOT';
                $value = null;
            }

            if (substr($key, -5) == '_like') {
                $field = substr($key, 0, -5);
                $operator = 'LIKE';
            } elseif (substr($key, -6) == '_isnot') {
                $field = substr($key, 0, -6);
                $operator = 'IS NOT';
            }

            $logic = $operation[$key] ?? $defaultOperation;
            $logic = strtoupper($logic) === 'OR' ? 'OR' : 'AND';

            $where[] = new DataBaseWhere($field, $value, $operator, $logic);
        }

        return $where;
    }

    private function createModelRecord(string $resourceName, array $record): array
    {
        [, $info] = $this->ensureModelResource($resourceName);
        $model = $this->instantiateModel($info['Name']);
        $this->fillModel($model, $record);

        MiniLog::clear();
        if (false === $model->save()) {
            $messages = $this->collectLogMessages();
            throw new RpcException(self::ERROR_OPERATION_FAILED, 'record-save-error', [
                'messages' => $messages,
                'record' => $record,
            ]);
        }

        MiniLog::clear();
        return $model->toArray();
    }

    private function updateModelRecord(string $resourceName, array $arguments): array
    {
        [, $info] = $this->ensureModelResource($resourceName);
        $model = $this->instantiateModel($info['Name']);
        $primaryKey = $this->resolvePrimaryKey($info['Name']) ?? 'id';

        $record = $arguments['record'] ?? $arguments['data'] ?? [];
        if (!is_array($record)) {
            throw new RpcException(self::ERROR_INVALID_PARAMS, 'Record payload must be an object.', ['resource' => $resourceName]);
        }

        $identifier = $this->resolveRecordIdentifier($arguments + ['record' => $record], $primaryKey);
        if (false === $model->loadFromCode($identifier)) {
            throw new RpcException(self::ERROR_RESOURCE_NOT_FOUND, 'Record not found.', [
                'resource' => $resourceName,
                'id' => $identifier,
            ]);
        }

        $this->fillModel($model, $record);

        MiniLog::clear();
        if (false === $model->save()) {
            $messages = $this->collectLogMessages();
            throw new RpcException(self::ERROR_OPERATION_FAILED, 'record-save-error', [
                'messages' => $messages,
                'id' => $identifier,
            ]);
        }

        MiniLog::clear();
        return $model->toArray();
    }

    private function deleteModelRecord(string $resourceName, array $arguments): array
    {
        [, $info] = $this->ensureModelResource($resourceName);
        $model = $this->instantiateModel($info['Name']);
        $primaryKey = $this->resolvePrimaryKey($info['Name']) ?? 'id';

        $identifier = $this->resolveRecordIdentifier($arguments, $primaryKey);
        if (false === $model->loadFromCode($identifier)) {
            throw new RpcException(self::ERROR_RESOURCE_NOT_FOUND, 'Record not found.', [
                'resource' => $resourceName,
                'id' => $identifier,
            ]);
        }

        $data = $model->toArray();

        MiniLog::clear();
        if (false === $model->delete()) {
            $messages = $this->collectLogMessages();
            throw new RpcException(self::ERROR_OPERATION_FAILED, 'record-delete-error', [
                'messages' => $messages,
                'id' => $identifier,
            ]);
        }

        MiniLog::clear();
        return $data;
    }

    private function retrieveModelRecord(string $resourceName, array $arguments): array
    {
        [, $info] = $this->ensureModelResource($resourceName);
        $model = $this->instantiateModel($info['Name']);
        $primaryKey = $this->resolvePrimaryKey($info['Name']) ?? 'id';

        $identifier = $this->resolveRecordIdentifier($arguments, $primaryKey);
        if (false === $model->loadFromCode($identifier)) {
            throw new RpcException(self::ERROR_RESOURCE_NOT_FOUND, 'Record not found.', [
                'resource' => $resourceName,
                'id' => $identifier,
            ]);
        }

        return $model->toArray();
    }

    private function resolveRecordIdentifier(array $arguments, string $primaryKey, bool $required = true): ?string
    {
        if (isset($arguments['id']) && $arguments['id'] !== '') {
            return (string)$arguments['id'];
        }

        if (isset($arguments[$primaryKey]) && $arguments[$primaryKey] !== '') {
            return (string)$arguments[$primaryKey];
        }

        $record = $arguments['record'] ?? $arguments['data'] ?? [];
        if (is_array($record) && isset($record[$primaryKey]) && $record[$primaryKey] !== '') {
            return (string)$record[$primaryKey];
        }

        if ($required) {
            throw new RpcException(self::ERROR_INVALID_PARAMS, 'Missing record identifier.', ['primary_key' => $primaryKey]);
        }

        return null;
    }

    private function fillModel(ModelClass $model, array $data): void
    {
        $fields = $model->getModelFields();
        foreach ($data as $key => $value) {
            if (isset($fields[$key])) {
                $model->{$key} = $value;
            }
        }
    }

    private function collectLogMessages(): array
    {
        $logs = MiniLog::read('', ['critical', 'error', 'warning', 'notice']);
        $messages = [];
        foreach ($logs as $log) {
            if (!empty($log['message'])) {
                $messages[] = $log['message'];
            }
        }
        MiniLog::clear();

        return array_values(array_unique($messages));
    }

    private function isSequentialArray(array $array): bool
    {
        $index = 0;
        foreach ($array as $key => $value) {
            if ($key !== $index) {
                return false;
            }
            ++$index;
        }

        return true;
    }
}
