<?php
namespace FacturaScripts\Plugins\Webhooks\Extension\Model\Base;

use Closure;
use FacturaScripts\Plugins\Webhooks\Lib\WebhookDispatcher;

class ModelClass
{
    protected function onInsert(): Closure
    {
        return function (): void {
            WebhookDispatcher::dispatch($this, 'insert');
        };
    }

    protected function onUpdate(): Closure
    {
        return function (): void {
            WebhookDispatcher::dispatch($this, 'update');
        };
    }
}
