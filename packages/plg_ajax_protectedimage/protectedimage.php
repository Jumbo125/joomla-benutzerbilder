<?php
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

class PlgAjaxProtectedimage extends CMSPlugin
{
    public function onAjaxProtectedimage()
    {
        $app  = $this->app ?? \Joomla\CMS\Factory::getApplication();
        $user = $app->getIdentity();

        if ((int) $user->id <= 0) {
            $this->abort(403, 'Zugriff nicht gewährt: Sie sind nicht angemeldet.');
        }

        $contentPlugin = PluginHelper::getPlugin('content', 'benutzerimages');
        $params        = new Registry($contentPlugin ? $contentPlugin->params : '');

        $prefix = trim((string) $params->get('user_prefix', 'Benutzer'));
        if ($prefix === '') {
            $prefix = 'Benutzer';
        }
        $count = max(1, min(99, (int) $params->get('user_count', 8)));

        $benutzername = trim((string) $user->username);
        $prefixRegex  = preg_quote($prefix, '/');

        // --- DIAGNOSE-MODUS (nach dem Test entfernen) ---
        if ((int) $app->getInput()->getInt('debug', 0) === 1) {
            $testMatch = [];
            preg_match('/^' . $prefixRegex . '\s*(\d+)$/iu', $benutzername, $testMatch);
            $nummer = (int) ($testMatch[1] ?? 0);
            $ordner = JPATH_ROOT . '/images/shootings/' . $prefix . $nummer;
            $datei  = trim((string) $app->getInput()->getString('file', ''));

            $info = [
                'plugin_loaded'  => true,
                'user_id'        => (int) $user->id,
                'username'       => $benutzername,
                'prefix'         => $prefix,
                'count'          => $count,
                'regex'          => '/^' . $prefixRegex . '\s*(\d+)$/iu',
                'regex_match'    => $testMatch ?: null,
                'folder'         => $ordner,
                'folder_exists'  => is_dir($ordner),
                'file_param'     => $datei,
                'file_exists'    => $datei !== '' && is_file($ordner . '/' . $datei),
                'ob_level'       => ob_get_level(),
                'php_version'    => PHP_VERSION,
            ];

            while (ob_get_level() > 0) { ob_end_clean(); }
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
        $dateiname      = trim((string) $app->getInput()->getString('file', ''));

        if ($dateiname === '') {
            $this->abort(400, 'Es wurde keine Bilddatei angegeben.');
        }

        if (basename($dateiname) !== $dateiname
            || str_contains($dateiname, '/')
            || str_contains($dateiname, '\\')
            || str_contains($dateiname, "\0")) {
            $this->abort(400, 'Ungültiger Dateiname.');
        }

        $erlaubteEndungen = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower(pathinfo($dateiname, PATHINFO_EXTENSION)), $erlaubteEndungen, true)) {
            $this->abort(403, 'Dieser Dateityp ist nicht erlaubt.');
        }

        $basisOrdner = realpath(JPATH_ROOT . '/images/shootings/' . $benutzerOrdner);
        if ($basisOrdner === false || !is_dir($basisOrdner)) {
            $this->abort(404, 'Bilderordner nicht gefunden: ' . $benutzerOrdner);
        }

        $bildPfad = realpath($basisOrdner . DIRECTORY_SEPARATOR . $dateiname);
        if ($bildPfad === false || !is_file($bildPfad)) {
            $this->abort(404, 'Bild nicht gefunden.');
        }

        if (strncmp($bildPfad, $basisOrdner . DIRECTORY_SEPARATOR, strlen($basisOrdner) + 1) !== 0) {
            $this->abort(403, 'Zugriff verweigert.');
        }

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($bildPfad);

        if ($mimeType === false || !in_array($mimeType, ['image/jpeg','image/png','image/gif','image/webp'], true)) {
            $this->abort(403, 'Ungültiger Bildtyp.');
        }

        while (ob_get_level() > 0) { ob_end_clean(); }

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

    private function abort(int $status, string $message): void
    {
        while (ob_get_level() > 0) { ob_end_clean(); }
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo $message;
        exit(0);
    }
}
