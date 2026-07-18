<?php

declare(strict_types=1);

namespace AndreasRottmann\Plugin\EditorsXtd\BenutzerImages\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

final class BenutzerImages extends CMSPlugin
{
    public function onDisplay($name)
    {
        $contentPlugin = PluginHelper::getPlugin('content', 'benutzerimages');
        $params        = new Registry($contentPlugin ? $contentPlugin->params : '');
        $placeholder   = trim((string) $params->get('placeholder', '[benutzer_images]'));

        if ($placeholder === '') {
            $placeholder = '[benutzer_images]';
        }

        $button          = new CMSObject();
        $button->modal   = false;
        $button->text    = 'Benutzerbilder';
        $button->name    = 'benutzerimages';
        $button->icon    = 'picture';
        $button->onclick = "Joomla.editors.instances['" . $name . "'].replaceSelection('"
            . addslashes($placeholder) . "'); return false;";

        return $button;
    }
}
