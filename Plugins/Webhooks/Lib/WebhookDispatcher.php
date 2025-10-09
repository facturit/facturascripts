<?php
namespace FacturaScripts\Plugins\Webhooks\Lib;

use FacturaScripts\Core\Http;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Template\ModelClass as BaseModelClass;
use FacturaScripts\Plugins\Webhooks\Model\WebhookRule;

class WebhookDispatcher
{
    public static function dispatch(BaseModelClass $model, string $event): void
    {
        $rules = WebhookRule::activeForModel($model->modelClassName(), $event);
        if (empty($rules)) {
            return;
        }

        foreach ($rules as $rule) {
            if (false === $rule->shouldTrigger($model)) {
                continue;
            }

            $payload = self::buildPayload($model, $event);
            $request = Http::postJson($rule->endpoint, $payload);
            $request->ok();

            if ($request->failed()) {
                Tools::log()->warning('webhook-request-failed', [
                    '%endpoint%' => $rule->endpoint,
                    '%error%' => $request->errorMessage(),
                ]);
            }
        }
    }

    protected static function buildPayload(BaseModelClass $model, string $event): array
    {
        $data = $model->toArray();
        $payload = [
            'event' => $event,
            'model' => $model->modelClassName(),
            'data' => $data,
        ];

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
        }

        return $payload;
    }
}
