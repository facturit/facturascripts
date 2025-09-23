<?php
/**
 * Helper with curated templates for the Advanced Notes wizard.
 */

namespace FacturaScripts\Plugins\AdvancedNotes\Lib;

use FacturaScripts\Core\Tools;

class AdvancedNoteTemplate
{
    public static function priorities(): array
    {
        return [
            'low' => [
                'value' => 'low',
                'label' => Tools::trans('priority-low'),
                'description' => Tools::trans('priority-low-description'),
            ],
            'medium' => [
                'value' => 'medium',
                'label' => Tools::trans('priority-medium'),
                'description' => Tools::trans('priority-medium-description'),
            ],
            'high' => [
                'value' => 'high',
                'label' => Tools::trans('priority-high'),
                'description' => Tools::trans('priority-high-description'),
            ],
            'critical' => [
                'value' => 'critical',
                'label' => Tools::trans('priority-critical'),
                'description' => Tools::trans('priority-critical-description'),
            ],
        ];
    }

    public static function statuses(): array
    {
        return [
            'draft' => [
                'value' => 'draft',
                'label' => Tools::trans('status-draft'),
            ],
            'in-progress' => [
                'value' => 'in-progress',
                'label' => Tools::trans('status-progress'),
            ],
            'final' => [
                'value' => 'final',
                'label' => Tools::trans('status-final'),
            ],
        ];
    }

    public static function modules(): array
    {
        return [
            'context' => [
                'type' => 'context',
                'icon' => 'fa-solid fa-circle-info',
                'name' => Tools::trans('module-context'),
                'description' => Tools::trans('module-context-description'),
                'placeholder' => Tools::trans('module-context-placeholder'),
            ],
            'agenda' => [
                'type' => 'agenda',
                'icon' => 'fa-solid fa-list-check',
                'name' => Tools::trans('module-agenda'),
                'description' => Tools::trans('module-agenda-description'),
                'placeholder' => Tools::trans('module-agenda-placeholder'),
            ],
            'highlights' => [
                'type' => 'highlights',
                'icon' => 'fa-solid fa-star',
                'name' => Tools::trans('module-highlights'),
                'description' => Tools::trans('module-highlights-description'),
                'placeholder' => Tools::trans('module-highlights-placeholder'),
            ],
            'decisions' => [
                'type' => 'decisions',
                'icon' => 'fa-solid fa-scale-balanced',
                'name' => Tools::trans('module-decisions'),
                'description' => Tools::trans('module-decisions-description'),
                'placeholder' => Tools::trans('module-decisions-placeholder'),
            ],
            'actions' => [
                'type' => 'actions',
                'icon' => 'fa-solid fa-circle-play',
                'name' => Tools::trans('module-actions'),
                'description' => Tools::trans('module-actions-description'),
                'placeholder' => Tools::trans('module-actions-placeholder'),
            ],
            'resources' => [
                'type' => 'resources',
                'icon' => 'fa-solid fa-paperclip',
                'name' => Tools::trans('module-resources'),
                'description' => Tools::trans('module-resources-description'),
                'placeholder' => Tools::trans('module-resources-placeholder'),
            ],
            'timeline' => [
                'type' => 'timeline',
                'icon' => 'fa-solid fa-timeline',
                'name' => Tools::trans('module-timeline'),
                'description' => Tools::trans('module-timeline-description'),
                'placeholder' => Tools::trans('module-timeline-placeholder'),
            ],
            'metrics' => [
                'type' => 'metrics',
                'icon' => 'fa-solid fa-chart-line',
                'name' => Tools::trans('module-metrics'),
                'description' => Tools::trans('module-metrics-description'),
                'placeholder' => Tools::trans('module-metrics-placeholder'),
            ],
            'insights' => [
                'type' => 'insights',
                'icon' => 'fa-solid fa-lightbulb',
                'name' => Tools::trans('module-insights'),
                'description' => Tools::trans('module-insights-description'),
                'placeholder' => Tools::trans('module-insights-placeholder'),
            ],
            'open-questions' => [
                'type' => 'open-questions',
                'icon' => 'fa-solid fa-question-circle',
                'name' => Tools::trans('module-open-questions'),
                'description' => Tools::trans('module-open-questions-description'),
                'placeholder' => Tools::trans('module-open-questions-placeholder'),
            ],
        ];
    }

    public static function templates(): array
    {
        $modules = self::modules();

        return [
            'meeting' => [
                'key' => 'meeting',
                'icon' => 'fa-solid fa-handshake-simple',
                'name' => Tools::trans('meeting-note'),
                'description' => Tools::trans('meeting-note-description'),
                'default_tags' => [Tools::trans('tag-meeting'), Tools::trans('tag-collaboration')],
                'recommended_modules' => [
                    $modules['context'],
                    $modules['agenda'],
                    $modules['highlights'],
                    $modules['decisions'],
                    $modules['actions'],
                    $modules['resources'],
                ],
                'blocks' => [
                    [
                        'type' => 'context',
                        'title' => Tools::trans('block-context'),
                        'content' => Tools::trans('block-context-default'),
                    ],
                    [
                        'type' => 'agenda',
                        'title' => Tools::trans('block-agenda'),
                        'content' => Tools::trans('block-agenda-default'),
                    ],
                    [
                        'type' => 'highlights',
                        'title' => Tools::trans('block-highlights'),
                        'content' => Tools::trans('block-highlights-default'),
                    ],
                    [
                        'type' => 'decisions',
                        'title' => Tools::trans('block-decisions'),
                        'content' => Tools::trans('block-decisions-default'),
                    ],
                    [
                        'type' => 'actions',
                        'title' => Tools::trans('block-actions'),
                        'content' => Tools::trans('block-actions-default'),
                    ],
                    [
                        'type' => 'resources',
                        'title' => Tools::trans('block-resources'),
                        'content' => Tools::trans('block-resources-default'),
                    ],
                ],
            ],
            'project' => [
                'key' => 'project',
                'icon' => 'fa-solid fa-diagram-project',
                'name' => Tools::trans('project-note'),
                'description' => Tools::trans('project-note-description'),
                'default_tags' => [Tools::trans('tag-project'), Tools::trans('tag-progress')],
                'recommended_modules' => [
                    $modules['context'],
                    $modules['timeline'],
                    $modules['metrics'],
                    $modules['actions'],
                    $modules['decisions'],
                    $modules['resources'],
                ],
                'blocks' => [
                    [
                        'type' => 'context',
                        'title' => Tools::trans('block-overview'),
                        'content' => Tools::trans('block-overview-default'),
                    ],
                    [
                        'type' => 'timeline',
                        'title' => Tools::trans('block-timeline'),
                        'content' => Tools::trans('block-timeline-default'),
                    ],
                    [
                        'type' => 'metrics',
                        'title' => Tools::trans('block-metrics'),
                        'content' => Tools::trans('block-metrics-default'),
                    ],
                    [
                        'type' => 'actions',
                        'title' => Tools::trans('block-next-steps'),
                        'content' => Tools::trans('block-next-steps-default'),
                    ],
                    [
                        'type' => 'decisions',
                        'title' => Tools::trans('block-open-decisions'),
                        'content' => Tools::trans('block-open-decisions-default'),
                    ],
                    [
                        'type' => 'resources',
                        'title' => Tools::trans('block-supporting-docs'),
                        'content' => Tools::trans('block-supporting-docs-default'),
                    ],
                ],
            ],
            'knowledge' => [
                'key' => 'knowledge',
                'icon' => 'fa-solid fa-book-open-reader',
                'name' => Tools::trans('knowledge-note'),
                'description' => Tools::trans('knowledge-note-description'),
                'default_tags' => [Tools::trans('tag-knowledge'), Tools::trans('tag-research')],
                'recommended_modules' => [
                    $modules['context'],
                    $modules['insights'],
                    $modules['open-questions'],
                    $modules['actions'],
                    $modules['resources'],
                ],
                'blocks' => [
                    [
                        'type' => 'context',
                        'title' => Tools::trans('block-summary'),
                        'content' => Tools::trans('block-summary-default'),
                    ],
                    [
                        'type' => 'insights',
                        'title' => Tools::trans('block-insights'),
                        'content' => Tools::trans('block-insights-default'),
                    ],
                    [
                        'type' => 'open-questions',
                        'title' => Tools::trans('block-questions'),
                        'content' => Tools::trans('block-questions-default'),
                    ],
                    [
                        'type' => 'actions',
                        'title' => Tools::trans('block-follow-up'),
                        'content' => Tools::trans('block-follow-up-default'),
                    ],
                    [
                        'type' => 'resources',
                        'title' => Tools::trans('block-references'),
                        'content' => Tools::trans('block-references-default'),
                    ],
                ],
            ],
        ];
    }
}
