<?php
namespace FacturaScripts\Plugins\Webhooks\Lib;

use FacturaScripts\Core\Http;
use FacturaScripts\Core\Session;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Template\ModelClass as BaseModelClass;
use FacturaScripts\Plugins\Webhooks\Lib\ModelInspector;
use FacturaScripts\Plugins\Webhooks\Model\WebhookRule;

class WebhookDispatcher
{
    public static function dispatch(BaseModelClass $model, string $event, array $original = []): void
    {
        $rules = WebhookRule::activeForModel($model->modelClassName(), $event);
        if (empty($rules)) {
            return;
        }

        foreach ($rules as $rule) {
            if (false === $rule->shouldTrigger($model, $original, $event)) {
                continue;
            }

            $payload = self::buildPayload($model, $event, $original);
            self::sendRule($rule, $payload, $model, $original);
        }
    }

    public static function dispatchActionForCodes(string $modelName, array $codes, string $modelClass = ''): void
    {
        $rules = WebhookRule::activeForModel($modelName, 'action');
        if (empty($rules)) {
            return;
        }

        $codes = array_values(array_unique(array_filter($codes)));
        if (empty($codes)) {
            return;
        }

        $modelClass = $modelClass ?: (ModelInspector::extendableModels()[$modelName] ?? '');

        foreach ($rules as $rule) {
            $selectedCodes = self::filterCodesForRule($rule, $codes, $modelClass);
            if (empty($selectedCodes)) {
                continue;
            }

            $payload = self::withUser([
                'event' => 'action',
                'model' => $modelName,
                'codes' => $selectedCodes,
            ]);

            self::sendRule($rule, $payload);
        }
    }

    protected static function buildPayload(BaseModelClass $model, string $event, array $original): array
    {
        $data = $model->toArray();
        $payload = [
            'event' => $event,
            'model' => $model->modelClassName(),
            'data' => $data,
            'previous' => empty($original) ? null : $original,
        ];

        $payload = self::withUser($payload);

        if (method_exists($model, 'getLines')) {
            $lines = [];
            foreach ($model->getLines() as $line) {
                if (method_exists($line, 'toArray')) {
                    $lines[] = $line->toArray();
                }
            }

            $payload['data'] = [
                'header' => $data,
                'lines' => $lines,
            ];

            $payload['previous'] = empty($original) ? null : [
                'header' => $original,
                'lines' => [],
            ];
        }

        return $payload;
    }

    protected static function filterCodesForRule(WebhookRule $rule, array $codes, string $modelClass): array
    {
        if (empty($modelClass) || false === class_exists($modelClass)) {
            return $codes;
        }

        $selected = [];
        foreach ($codes as $code) {
            $model = new $modelClass();
            if (false === method_exists($model, 'loadFromCode')) {
                continue;
            }

            if (false === $model->loadFromCode($code)) {
                continue;
            }

            if (false === $rule->shouldTrigger($model, $model->getOriginal(), 'action')) {
                continue;
            }

            $selected[] = $code;
        }

        return $selected;
    }

    protected static function sendRule(WebhookRule $rule, array $payload, ?BaseModelClass $model = null, array $original = []): void
    {
        $request = Http::postJson($rule->endpoint, $payload);
        $request->ok();

        $responseText = trim($request->body());
        $logContext = [
            '%endpoint%' => $rule->endpoint,
            '%event%' => $payload['event'] ?? 'unknown',
        ];

        if ($request->failed()) {
            $logContext['%error%'] = $request->errorMessage();
            Tools::log()->warning('webhook-request-failed', $logContext);
            Tools::log('webhooks')->warning('webhook-request-failed', $logContext);
            return;
        }

        $message = $responseText ?: Tools::lang()->trans('webhook-dispatched', $logContext);
        Tools::log()->notice($message);
        Tools::log('webhooks')->notice('webhook-dispatched', array_merge($logContext, ['%response%' => $responseText]));
    }

    protected static function withUser(array $payload): array
    {
        $user = Session::user();
        if (!empty($user->nick)) {
            $payload['user'] = ['nick' => $user->nick];
        }

        return $payload;
    }
}
