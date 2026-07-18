<?php

declare(strict_types=1);

namespace AndreasRottmann\Plugin\System\BenutzerImages\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

final class BenutzerImages extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onAfterRoute' => 'onAfterRoute',
        ];
    }

    public function onAfterRoute(): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('administrator')) {
            return;
        }

        $user = $app->getIdentity();

        if ((int) $user->id <= 0) {
            return;
        }

        $contentPlugin = PluginHelper::getPlugin('content', 'benutzerimages');

        if (!$contentPlugin) {
            return;
        }

        $params = new Registry($contentPlugin->params);
        $prefix = trim((string) $params->get('user_prefix', 'Benutzer'));
        $prefix = $prefix !== '' ? $prefix : 'Benutzer';
        $count  = max(1, min(99, (int) $params->get('user_count', 8)));

        $base = JPATH_ROOT . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'shootings';

        if (!is_dir($base)) {
            @mkdir($base, 0755, true);
        }

        $created = [];

        for ($i = 1; $i <= $count; $i++) {
            $path = $base . DIRECTORY_SEPARATOR . $prefix . $i;
            if (!is_dir($path) && @mkdir($path, 0755)) {
                @file_put_contents($path . DIRECTORY_SEPARATOR . 'index.html', '<!DOCTYPE html><title></title>');
                $created[] = $prefix . $i;
            }
        }

        if ($created !== []) {
            $app->enqueueMessage(
                'Benutzerbilder: Fehlende Ordner wurden automatisch angelegt: '
                    . implode(', ', $created),
                'message'
            );
        }
    }
}
