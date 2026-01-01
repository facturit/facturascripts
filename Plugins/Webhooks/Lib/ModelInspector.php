<?php
namespace FacturaScripts\Plugins\Webhooks\Lib;

use FacturaScripts\Core\Template\ModelClass as TemplateModelClass;
use FacturaScripts\Core\Tools;
use ReflectionClass;

class ModelInspector
{
    /**
     * @var array<string, class-string<TemplateModelClass>>|null
     */
    private static $extendableModels;

    /**
     * Returns the map of extendable model short names to their fully qualified class names.
     *
     * @return array<string, class-string<TemplateModelClass>>
     */
    public static function extendableModels(): array
    {
        if (null !== self::$extendableModels) {
            return self::$extendableModels;
        }

        $models = [];
        $files = Tools::folderScan(FS_FOLDER . '/Dinamic/Model/', true);
        foreach ($files as $item) {
            if (false === str_ends_with($item, '.php')) {
                continue;
            }

            $relativeClass = substr($item, 0, -4);
            $relativeClass = str_replace(['/', '\\'], '\\', $relativeClass);
            $className = '\\FacturaScripts\\Dinamic\\Model\\' . $relativeClass;
            if (!class_exists($className)) {
                continue;
            }

            if (!is_subclass_of($className, TemplateModelClass::class)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract() || !$reflection->hasMethod('addExtension')) {
                continue;
            }

            $method = $reflection->getMethod('addExtension');
            if ($method->isAbstract() || !$method->isPublic() || !$method->isStatic()) {
                continue;
            }

            $models[$reflection->getShortName()] = $className;
        }

        ksort($models, SORT_NATURAL | SORT_FLAG_CASE);
        self::$extendableModels = $models;
        return self::$extendableModels;
    }
}
