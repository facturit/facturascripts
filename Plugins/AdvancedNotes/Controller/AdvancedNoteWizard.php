<?php
/**
 * Wizard controller that orchestrates the Advanced Notes experience.
 */

namespace FacturaScripts\Dinamic\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Tools;
use FacturaScripts\Core\Where;
use FacturaScripts\Dinamic\Lib\AssetManager;
use FacturaScripts\Dinamic\Model\AdvancedNote;
use FacturaScripts\Dinamic\Model\User;
use FacturaScripts\Plugins\AdvancedNotes\Lib\AdvancedNoteTemplate;

class AdvancedNoteWizard extends Controller
{
    /** @var AdvancedNote */
    public $note;

    /** @var array */
    public $noteTemplates = [];

    /** @var array */
    public $moduleLibrary = [];

    /** @var array */
    public $priorityOptions = [];

    /** @var array */
    public $statusOptions = [];

    /** @var array */
    public $recentNotes = [];

    /** @var array */
    public $collaboratorOptions = [];

    /** @var string */
    public $step = 'basics';

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'productivity';
        $data['title'] = 'advanced-note-wizard';
        $data['icon'] = 'fa-solid fa-note-sticky';
        $data['showonmenu'] = true;

        return $data;
    }

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        AssetManager::addCss(FS_ROUTE . '/Plugins/AdvancedNotes/Assets/CSS/AdvancedNoteWizard.css', 2);
        AssetManager::addJs(FS_ROUTE . '/Plugins/AdvancedNotes/Assets/JS/AdvancedNoteWizard.js', 2);

        $this->noteTemplates = AdvancedNoteTemplate::templates();
        $this->moduleLibrary = AdvancedNoteTemplate::modules();
        $this->priorityOptions = AdvancedNoteTemplate::priorities();
        $this->statusOptions = AdvancedNoteTemplate::statuses();
        $this->collaboratorOptions = $this->loadCollaborators();

        $action = $this->request->request->get('action', $this->request->get('action', ''));
        if ('save' === $action) {
            $this->saveNote();
        } else {
            $this->loadCurrentNote();
        }

        $this->recentNotes = AdvancedNote::all(
            [Where::eq('owner', $this->user->nick)],
            ['updated_at' => 'DESC'],
            0,
            5
        );
    }

    protected function saveNote(): void
    {
        if (false === $this->validateFormToken()) {
            Tools::log()->warning('invalid-request');
            $this->loadCurrentNote();
            return;
        }

        $payload = $this->request->request->get('note_payload', '');
        if (empty($payload)) {
            Tools::log()->warning('advanced-note-invalid-payload');
            $this->loadCurrentNote();
            return;
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            Tools::log()->warning('advanced-note-invalid-payload');
            $this->loadCurrentNote();
            return;
        }

        $noteId = $data['idnote'] ?? null;
        $note = new AdvancedNote();
        if (!empty($noteId)) {
            $note->load($noteId);
        }

        $note->title = $data['title'] ?? '';
        $note->category = $data['type'] ?? '';
        $note->status = $data['status'] ?? 'draft';
        $note->priority = $data['priority'] ?? 'medium';
        $note->owner = $this->user->nick;
        $note->summary = $data['summary'] ?? '';
        $note->due_date = empty($data['dueDate']) ? null : Tools::date($data['dueDate']);
        $note->content = $data['content']['html'] ?? '';
        $note->tags = $data['tags'] ?? [];
        $note->collaborators = $data['collaborators'] ?? [];
        $note->metadata = $this->buildMetadata($data);

        if ($note->save()) {
            Tools::log()->notice('advanced-note-saved');
            $this->note = $note;
            $this->step = 'review';
        } else {
            Tools::log()->warning('advanced-note-save-error');
            $this->note = $note;
            $this->step = $this->request->request->get('current_step', 'editor');
        }
    }

    protected function loadCurrentNote(): void
    {
        $this->step = $this->request->get('step', 'basics');
        $noteId = $this->request->get('idnote', '');

        $this->note = new AdvancedNote();
        if (!empty($noteId) && $this->note->load($noteId)) {
            $this->step = $this->request->get('step', 'editor');
            return;
        }

        $this->note->owner = $this->user->nick;
    }

    private function buildMetadata(array $data): array
    {
        $metadata = $data['metadata'] ?? [];
        $metadata['modules'] = $metadata['modules'] ?? [];
        $metadata['blocks'] = $data['content']['blocks'] ?? [];
        $metadata['stats'] = [
            'wordCount' => $data['content']['wordCount'] ?? 0,
            'readingTime' => $data['content']['readingTime'] ?? '',
        ];

        return $metadata;
    }

    private function loadCollaborators(): array
    {
        $list = [];
        foreach (User::all([], ['nick' => 'ASC']) as $user) {
            $list[] = [
                'nick' => $user->nick,
                'name' => trim($user->nick . ' ' . ($user->email ? '(' . $user->email . ')' : '')),
            ];
        }

        return $list;
    }
}
