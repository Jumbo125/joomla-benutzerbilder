<?php

declare(strict_types=1);

namespace AndreasRottmann\Plugin\Ajax\ProtectedImage\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

final class ProtectedImage extends CMSPlugin
{
    public function onAjaxProtectedimage(): void
    {
        $app  = $this->getApplication();
        $user = $app->getIdentity();

        if ((int) $user->id <= 0) {
            $this->abort(403, 'Zugriff nicht gewährt: Sie sind nicht angemeldet.');
        }

        $contentPluginData = PluginHelper::getPlugin('content', 'benutzerimages');
        $contentParams     = new Registry($contentPluginData ? $contentPluginData->params : '');

        $prefix = trim((string) $contentParams->get('user_prefix', 'Benutzer'));
        if ($prefix === '') {
            $prefix = 'Benutzer';
        }
        $count = max(1, min(99, (int) $contentParams->get('user_count', 8)));

        $benutzername = trim((string) $user->username);
        $prefixRegex  = preg_quote($prefix, '/');

        // --- DIAGNOSE-MODUS (nach dem Test entfernen) ---
        if ($app->getInput()->getInt('debug', 0) === 1) {
            $testMatch = [];
            preg_match('/^' . $prefixRegex . '\s*(\d+)$/iu', $benutzername, $testMatch);

            $nummer          = (int) ($testMatch[1] ?? 0);
            $benutzerOrdner  = $prefix . $nummer;
            $basisTest       = JPATH_ROOT . '/images/shootings/' . $benutzerOrdner;
            $dateiTest       = trim((string) $app->getInput()->getString('file', ''));

            $info = [
                'user_id'       => (int) $user->id,
                'username'      => $benutzername,
                'prefix_param'  => $prefix,
                'count_param'   => $count,
                'regex'         => '/^' . $prefixRegex . '\s*(\d+)$/iu',
                'regex_match'   => $testMatch ?: null,
                'folder'        => $benutzerOrdner,
                'folder_exists' => is_dir($basisTest),
                'file_param'    => $dateiTest,
                'file_exists'   => is_file($basisTest . '/' . $dateiTest),
                'ob_level'      => ob_get_level(),
                'php_version'   => PHP_VERSION,
            ];

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit(0);
        }
        // --- ENDE DIAGNOSE-MODUS ---

        if (!preg_match('/^' . $prefixRegex . '\s*(\d+)$/iu', $benutzername, $treffer)) {
            $this->abort(403, 'Für diesen Benutzer wurde kein Bilderordner eingerichtet.');
        }

        $nummer = (int) $treffer[1];

        if ($nummer < 1 || $nummer > $count) {
            $this->abort(403, 'Benutzernummer liegt außerhalb des konfigurierten Bereichs.');
        }

        $benutzerOrdner = $prefix . $nummer;

        $dateiname = trim((string) $app->getInput()->getString('file', ''));

        if ($dateiname === '') {
            $this->abort(400, 'Es wurde keine Bilddatei angegeben.');
        }

        if (
            basename($dateiname) !== $dateiname
            || str_contains($dateiname, '/')
            || str_contains($dateiname, '\\')
            || str_contains($dateiname, "\0")
        ) {
            $this->abort(400, 'Ungültiger Dateiname.');
        }

        $erlaubteEndungen = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $dateiendung      = strtolower(pathinfo($dateiname, PATHINFO_EXTENSION));

        if (!in_array($dateiendung, $erlaubteEndungen, true)) {
            $this->abort(403, 'Dieser Dateityp ist nicht erlaubt.');
        }

        $basisOrdner = realpath(JPATH_ROOT . '/images/shootings/' . $benutzerOrdner);

        if ($basisOrdner === false || !is_dir($basisOrdner)) {
            $this->abort(404, 'Der Bilderordner wurde nicht gefunden: ' . $benutzerOrdner);
        }

        $bildPfad = realpath($basisOrdner . DIRECTORY_SEPARATOR . $dateiname);

        if ($bildPfad === false || !is_file($bildPfad)) {
            $this->abort(404, 'Das Bild wurde nicht gefunden.');
        }

        if (strncmp($bildPfad, $basisOrdner . DIRECTORY_SEPARATOR, strlen($basisOrdner) + 1) !== 0) {
            $this->abort(403, 'Zugriff verweigert.');
        }

        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($bildPfad);

        $erlaubteMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if ($mimeType === false || !in_array($mimeType, $erlaubteMimeTypes, true)) {
            $this->abort(403, 'Ungültiger Bildtyp.');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code(200);
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) filesize($bildPfad));
        header('Content-Disposition: inline; filename="' . rawurlencode(basename($bildPfad)) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($bildPfad);
        exit(0);
    }

    private function abort(int $status, string $message): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        echo $message;
        exit(0);
    }
}
