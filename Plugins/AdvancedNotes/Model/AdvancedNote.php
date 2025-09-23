<?php
/**
 * Model that stores advanced note documents produced by the wizard.
 */

namespace FacturaScripts\Dinamic\Model;

use FacturaScripts\Core\Session;
use FacturaScripts\Core\Template\ModelClass;
use FacturaScripts\Core\Template\ModelTrait;
use FacturaScripts\Core\Tools;

class AdvancedNote extends ModelClass
{
    use ModelTrait;

    /** @var array */
    public $tags = [];

    /** @var array */
    public $collaborators = [];

    /** @var array */
    public $metadata = [];

    public function clear(): void
    {
        parent::clear();

        $this->priority = 'medium';
        $this->status = 'draft';
        $this->owner = Tools::noHtml(Session::user()->nick ?? '');
        $this->tags = [];
        $this->collaborators = [];
        $this->metadata = [];
    }

    public function loadFromData(array $data = [], array $exclude = []): void
    {
        parent::loadFromData($data, $exclude);

        $this->tags = $this->decodeList($this->tags);
        $this->collaborators = $this->decodeList($this->collaborators);
        $this->metadata = $this->decodeMetadata($this->metadata);
    }

    public function primaryDescriptionColumn(): string
    {
        return 'title';
    }

    public static function primaryColumn(): string
    {
        return 'idnote';
    }

    public function save(): bool
    {
        $encodedTags = $this->encodeList($this->tags);
        $encodedCollaborators = $this->encodeList($this->collaborators);
        $encodedMetadata = $this->encodeMetadata($this->metadata);

        $this->title = Tools::noHtml($this->title);
        $this->category = Tools::noHtml($this->category);
        $this->status = Tools::noHtml($this->status ?: 'draft');
        $this->priority = Tools::noHtml($this->priority ?: 'medium');
        $this->owner = Tools::noHtml($this->owner ?: (Session::user()->nick ?? ''));
        $this->summary = Tools::noHtml($this->summary);
        $this->content = $this->sanitizeContent($this->content);
        $this->tags = $encodedTags;
        $this->collaborators = $encodedCollaborators;
        $this->metadata = $encodedMetadata;

        if (empty($this->created_at)) {
            $this->created_at = Tools::dateTime();
        }
        $this->updated_at = Tools::dateTime();

        $saved = parent::save();

        $this->tags = $this->decodeList($encodedTags);
        $this->collaborators = $this->decodeList($encodedCollaborators);
        $this->metadata = $this->decodeMetadata($encodedMetadata);

        return $saved;
    }

    public static function tableName(): string
    {
        return 'an_notes';
    }

    public function url(string $type = 'auto', string $list = 'List'): string
    {
        return 'AdvancedNoteWizard&idnote=' . $this->idnote;
    }

    private function decodeList($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map([Tools::class, 'noHtml'], $value)));
        }

        $decoded = json_decode((string)$value, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map([Tools::class, 'noHtml'], $decoded)));
        }

        $items = array_map('trim', explode(',', (string)$value));
        return array_values(array_filter(array_map([Tools::class, 'noHtml'], $items)));
    }

    private function decodeMetadata($value): array
    {
        if (empty($value)) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function encodeList($value): string
    {
        $items = [];
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = array_map('trim', explode(',', $value));
            }
        } elseif (is_array($value)) {
            $items = $value;
        }

        $clean = [];
        foreach ($items as $item) {
            if ('' === trim((string)$item)) {
                continue;
            }
            $clean[] = Tools::noHtml((string)$item);
        }

        $encoded = json_encode(array_values(array_unique($clean)), JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '[]' : $encoded;
    }

    private function encodeMetadata($value): string
    {
        $metadata = is_array($value) ? $value : [];
        $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '{}' : $encoded;
    }

    private function sanitizeContent(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        $allowedTags = '<p><h1><h2><h3><h4><h5><h6><ul><ol><li><strong><em><u><mark><blockquote>' .
            '<code><pre><table><thead><tbody><tr><th><td><span><div><section><article><hr><br>'; // phpcs:ignore

        $clean = strip_tags((string)$html, $allowedTags);
        $clean = preg_replace('/ on[a-z]+="[^"]*"/i', '', $clean ?? '');
        $clean = preg_replace("/ on[a-z]+='[^']*'/i", '', $clean ?? '');
        $clean = preg_replace('/javascript:/i', '', $clean ?? '');

        return $clean ?? '';
    }
}
