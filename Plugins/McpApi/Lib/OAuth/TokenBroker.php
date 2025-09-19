<?php
namespace FacturaScripts\Plugins\McpApi\Lib\OAuth;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Cache;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\ApiKey;

class TokenBroker
{
    private const CODE_PREFIX = 'mcp-oauth-code-';
    private const TOKEN_PREFIX = 'mcp-oauth-access-';
    private const CODE_TTL = 300;
    private const ACCESS_TTL = 3600;

    /**
     * @return array{api_key:string,label:string,full_access:bool,id:?int}|null
     */
    public static function validateApiKey(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if ($token === (string)Tools::config('api_key')) {
            return [
                'api_key' => $token,
                'label' => 'config-api-key',
                'full_access' => true,
                'id' => null,
            ];
        }

        $apiKey = new ApiKey();
        $where = [
            new DataBaseWhere('apikey', $token),
            new DataBaseWhere('enabled', true),
        ];

        if (false === $apiKey->loadWhere($where)) {
            return null;
        }

        return [
            'api_key' => $apiKey->apikey,
            'label' => $apiKey->nick ?: ($apiKey->description ?: 'api-key'),
            'full_access' => (bool)$apiKey->fullaccess,
            'id' => $apiKey->id,
        ];
    }

    /**
     * @param array{api_key:string,label:string,full_access:bool,id:?int} $identity
     * @param string[] $scopes
     */
    public static function issueAuthorizationCode(string $clientId, array $identity, string $redirectUri, ?string $codeChallenge, ?string $challengeMethod, array $scopes = []): string
    {
        $code = self::randomToken();
        $method = strtoupper($challengeMethod ?? 'plain');
        if (!in_array($method, ['PLAIN', 'S256'], true)) {
            $method = 'PLAIN';
        }

        $record = [
            'client_id' => $clientId,
            'identity' => $identity,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $method,
            'scopes' => $scopes,
            'created_at' => time(),
            'expires_at' => time() + self::CODE_TTL,
        ];

        Cache::set(self::CODE_PREFIX . $code, $record);
        return $code;
    }

    /**
     * @return array{access_token:string,token_type:string,expires_in:int,scope:string}
     */
    public static function exchangeCode(string $code, string $clientId, string $redirectUri, ?string $codeVerifier): ?array
    {
        $payload = Cache::get(self::CODE_PREFIX . $code);
        Cache::delete(self::CODE_PREFIX . $code);
        if (!is_array($payload)) {
            return null;
        }

        if (($payload['client_id'] ?? '') !== $clientId) {
            return null;
        }

        if (($payload['redirect_uri'] ?? '') !== $redirectUri) {
            return null;
        }

        if (!self::isCodeFresh($payload)) {
            return null;
        }

        $challenge = $payload['code_challenge'] ?? null;
        if ($challenge) {
            $method = strtoupper($payload['code_challenge_method'] ?? 'PLAIN');
            if (null === $codeVerifier || $codeVerifier === '') {
                return null;
            }

            if ($method === 'S256') {
                $expected = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
            } else {
                $expected = $codeVerifier;
            }

            if (!hash_equals($challenge, $expected)) {
                return null;
            }
        }

        $token = self::randomToken();
        $identity = $payload['identity'];
        $record = [
            'api_key' => $identity['api_key'],
            'client_id' => $clientId,
            'label' => $identity['label'],
            'full_access' => $identity['full_access'],
            'scopes' => $payload['scopes'],
            'expires_at' => time() + self::ACCESS_TTL,
        ];

        Cache::set(self::TOKEN_PREFIX . $token, $record);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => self::ACCESS_TTL,
            'scope' => implode(' ', $payload['scopes']),
        ];
    }

    public static function resolveBearerToken(string $authorizationHeader): ?string
    {
        if (!preg_match('/^Bearer\s+(\S+)$/i', trim($authorizationHeader), $matches)) {
            return null;
        }

        $token = $matches[1];
        $record = Cache::get(self::TOKEN_PREFIX . $token);
        if (!is_array($record)) {
            return null;
        }

        if (($record['expires_at'] ?? 0) < time()) {
            Cache::delete(self::TOKEN_PREFIX . $token);
            return null;
        }

        return $record['api_key'] ?? null;
    }

    /**
     * @param array{expires_at?:int}|null $payload
     */
    private static function isCodeFresh(?array $payload): bool
    {
        if (null === $payload) {
            return false;
        }

        $expiresAt = $payload['expires_at'] ?? 0;
        if ($expiresAt < time()) {
            return false;
        }

        return true;
    }

    private static function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
