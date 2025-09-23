<?php
/**
 * Plugin AdvancedNotes for FacturaScripts
 * Provides a collaborative wizard to craft advanced notes.
 */

namespace FacturaScripts\Plugins\AdvancedNotes;

use FacturaScripts\Core\DbUpdater;
use FacturaScripts\Core\Template\InitClass;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Model\Page;

class Init extends InitClass
{
    private const PAGE_NAME = 'AdvancedNoteWizard';
    private const TABLE_NAME = 'an_notes';

    public function init(): void
    {
        $this->registerPage();
    }

    public function uninstall(): void
    {
        $page = Page::find(self::PAGE_NAME);
        if (null !== $page) {
            $page->delete();
        }
    }

    public function update(): void
    {
        $this->ensureTable();
        $this->registerPage();
    }

    private function ensureTable(): void
    {
        if (false === DbUpdater::createOrUpdateTable(self::TABLE_NAME)) {
            Tools::log()->warning('advanced-notes-table-error');
        }
    }

    private function registerPage(): void
    {
        $page = Page::find(self::PAGE_NAME);
        if (null === $page) {
            $page = new Page();
            $page->name = self::PAGE_NAME;
        }

        $page->title = 'advanced-note-wizard';
        $page->menu = 'productivity';
        $page->submenu = null;
        $page->icon = 'fa-solid fa-note-sticky';
        $page->showonmenu = true;
        $page->ordernum = 45;

        if (false === $page->save()) {
            Tools::log()->warning('advanced-notes-page-error');
        }
    }
}
