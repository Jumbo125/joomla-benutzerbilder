<?php

declare(strict_types=1);

namespace AndreasRottmann\Plugin\Content\BenutzerImages\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Uri\Uri;

final class FolderHinweisField extends FormField
{
    protected $type = 'FolderHinweis';

    public function getInput(): string
    {
        $prefix = trim((string) ($this->form->getValue('user_prefix', 'params') ?: 'Benutzer'));
        $count  = max(1, min(99, (int) ($this->form->getValue('user_count', 'params') ?: 8)));

        // Fehlende Ordner beim Laden der Backend-Seite automatisch anlegen
        $feedback = $this->createFolders($prefix, $count);

        // Ordnerliste
        $listItems = '';
        for ($i = 1; $i <= $count; $i++) {
            $listItems .= '<li><code>images/shootings/'
                . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8')
                . $i . '/</code></li>';
        }

        $mediaUrlEsc = htmlspecialchars(
            'index.php?option=com_media&view=media&path=images%2Fshootings',
            ENT_QUOTES,
            'UTF-8'
        );

        return <<<HTML
<div class="alert alert-info my-3">
  <p><strong>Ordnerstruktur</strong> <code>images/shootings/</code>:</p>
  <ul class="mb-2">{$listItems}</ul>
  <a href="{$mediaUrlEsc}" class="btn btn-secondary btn-sm">
    <span class="icon-images" aria-hidden="true"></span> Media Manager öffnen
  </a>
  {$feedback}
</div>
HTML;
    }

    public function getLabel(): string
    {
        return '';
    }

    private function createFolders(string $prefix, int $count): string
    {
        $base = JPATH_ROOT . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'shootings';

        if (!is_dir($base)) {
            if (!mkdir($base, 0755, true)) {
                $err = error_get_last()['message'] ?? 'unbekannt';
                return '<div class="alert alert-danger mt-2">'
                    . 'Basisverzeichnis konnte nicht erstellt werden: <code>'
                    . htmlspecialchars($base, ENT_QUOTES, 'UTF-8') . '</code><br>'
                    . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        }

        $created = [];
        $existed = [];
        $failed  = [];

        for ($i = 1; $i <= $count; $i++) {
            $name = $prefix . $i;
            $path = $base . DIRECTORY_SEPARATOR . $name;

            if (is_dir($path)) {
                $existed[] = $name;
            } elseif (mkdir($path, 0755)) {
                @file_put_contents($path . DIRECTORY_SEPARATOR . 'index.html', '<!DOCTYPE html><title></title>');
                $created[] = $name;
            } else {
                $err      = error_get_last()['message'] ?? '';
                $failed[] = $name . ($err ? ' (' . $err . ')' : '');
            }
        }

        if ($failed !== []) {
            return '<div class="alert alert-danger mt-2"><strong>Fehler – Ordner konnten nicht erstellt werden:</strong><br>'
                . htmlspecialchars(implode('<br>', $failed), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        if ($created !== []) {
            return '<div class="alert alert-success mt-2"><strong>Automatisch angelegt:</strong> '
                . htmlspecialchars(implode(', ', $created), ENT_QUOTES, 'UTF-8') . '</div>';
        }

        // Alle Ordner bereits vorhanden – keine Ausgabe nötig
        return '';
    }
}
