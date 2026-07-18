<?php

declare(strict_types=1);

namespace AndreasRottmann\Plugin\Content\BenutzerImages\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;

final class BenutzerImages extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'onContentPrepare',
        ];
    }

    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        $app = $this->getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $context = (string) $event->getContext();

        if (!str_starts_with($context, 'com_content.')) {
            return;
        }

        $article = $event->getItem();

        if (
            !is_object($article)
            || !isset($article->text)
            || !is_string($article->text)
        ) {
            return;
        }

        $app->getDocument()->addStyleSheet(
            Uri::root() . 'media/plg_content_benutzerimages/css/gallery.css'
        );

        $placeholder = trim(
            (string) $this->params->get(
                'placeholder',
                '[benutzer_images]'
            )
        );

        if (
            $placeholder === ''
            || !str_contains($article->text, $placeholder)
        ) {
            return;
        }

        $titel = trim(
            (string) $this->params->get(
                'article_title',
                'Ihre Fotos'
            )
        );

        if ($titel !== '' && property_exists($article, 'title')) {
            $article->title = $titel;
        }

        $article->text = str_replace(
            $placeholder,
            $this->renderGallery(),
            $article->text
        );
    }

    private function getUserConfig(): array
    {
        $prefix = trim((string) $this->params->get('user_prefix', 'Benutzer'));
        if ($prefix === '') {
            $prefix = 'Benutzer';
        }

        $count = max(1, min(99, (int) $this->params->get('user_count', 8)));

        return ['prefix' => $prefix, 'count' => $count];
    }

    private function renderGallery(): string
    {
        $user = $this->getApplication()->getIdentity();

        if ((int) $user->id <= 0) {
            return '<div class="alert alert-warning">'
                . 'Bitte melden Sie sich an, um Ihre Fotos anzusehen.'
                . '</div>';
        }

        $config     = $this->getUserConfig();
        $prefix     = $config['prefix'];
        $count      = $config['count'];
        $benutzername = trim((string) $user->username);
        $prefixRegex  = preg_quote($prefix, '/');

        if (!preg_match('/^' . $prefixRegex . '\s*(\d+)$/iu', $benutzername, $treffer)) {
            if ($user->authorise('core.login.admin')) {
                return '<div class="alert alert-warning">'
                    . '<strong>Admin-Hinweis:</strong> Ihr Benutzerkonto „'
                    . htmlspecialchars($benutzername, ENT_QUOTES, 'UTF-8')
                    . '" passt nicht zum Galerie-Präfix „'
                    . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8')
                    . '". Die Galerie wird nur für Benutzer wie „'
                    . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8')
                    . '1", „'
                    . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8')
                    . '2" usw. angezeigt.'
                    . '</div>';
            }

            return '<div class="alert alert-info">'
                . 'Für Ihren Benutzer sind derzeit keine Fotos verfügbar.'
                . '</div>';
        }

        $nummer = (int) $treffer[1];

        if ($nummer < 1 || $nummer > $count) {
            return '<div class="alert alert-info">'
                . 'Für Ihren Benutzer sind derzeit keine Fotos verfügbar.'
                . '</div>';
        }

        $bilderOrdner = $prefix . $nummer;

        $shootingsBasis = realpath(
            JPATH_ROOT . '/images/shootings'
        );

        if ($shootingsBasis === false) {
            return '<div class="alert alert-warning">'
                . 'Das Shooting-Verzeichnis wurde nicht gefunden.'
                . '</div>';
        }

        $galleryOrdner = realpath(
            $shootingsBasis
            . DIRECTORY_SEPARATOR
            . $bilderOrdner
        );

        if ($galleryOrdner === false || !is_dir($galleryOrdner)) {
            return '<div class="alert alert-info">'
                . 'Für diesen Benutzer wurde noch kein Bilderordner gefunden.'
                . '</div>';
        }

        $shootingsPrefix = $shootingsBasis . DIRECTORY_SEPARATOR;

        if (
            strncmp(
                $galleryOrdner . DIRECTORY_SEPARATOR,
                $shootingsPrefix,
                strlen($shootingsPrefix)
            ) !== 0
        ) {
            return '<div class="alert alert-danger">'
                . 'Ungültiger Bilderordner.'
                . '</div>';
        }

        $dateien = scandir($galleryOrdner);

        if ($dateien === false) {
            return '<div class="alert alert-warning">'
                . 'Der Bilderordner konnte nicht gelesen werden.'
                . '</div>';
        }

        $erlaubteEndungen = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $bilder = [];

        foreach ($dateien as $datei) {
            if (
                $datei === '.'
                || $datei === '..'
                || strtolower($datei) === 'index.html'
            ) {
                continue;
            }

            $dateipfad = $galleryOrdner
                . DIRECTORY_SEPARATOR
                . $datei;

            $endung = strtolower(
                pathinfo($datei, PATHINFO_EXTENSION)
            );

            if (
                is_file($dateipfad)
                && in_array($endung, $erlaubteEndungen, true)
            ) {
                $bilder[] = $datei;
            }
        }

        if ($bilder === []) {
            return '<div class="alert alert-info">'
                . 'Für diesen Benutzer sind derzeit keine Bilder vorhanden.'
                . '</div>';
        }

        usort($bilder, 'strnatcasecmp');

        $rootUrl = rtrim(Uri::root(true), '/');

        $imageEndpoint = $rootUrl
            . '/index.php?option=com_ajax'
            . '&plugin=protectedimage'
            . '&format=raw'
            . '&file=';

        $lightboxName = 'shooting-' . (int) $user->id;

        $gallery = '<div id="gallery" class="masonry-wrapper lightbox_wrapper">';
        $gallery .= '<div class="masonry">';

        foreach ($bilder as $index => $bildname) {
            $bildzahl = $index + 1;

            $geschuetzteUrl = $imageEndpoint
                . rawurlencode($bildname);

            $urlHtml = htmlspecialchars(
                $geschuetzteUrl,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            $bildnameHtml = htmlspecialchars(
                $bildname,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            $gallery .= '<div class="andi_img_effect_wrap masonry-item">';

            $gallery .= '<span class="bildnummer" ';
            $gallery .= 'style="display:block;margin:0 auto;';
            $gallery .= 'text-align:center;position:relative;';
            $gallery .= 'z-index:120;background-color:white;">';
            $gallery .= 'Bildnummer ' . $bildzahl;
            $gallery .= '</span>';

            $gallery .= '<div class="andi_over_img" ';
            $gallery .= 'style="visibility:hidden;">';
            $gallery .= $bildnameHtml . '<br>';
            $gallery .= '</div>';

            $gallery .= '<div class="inner_img">';

            $gallery .= '<a href="' . $urlHtml . '" ';
            $gallery .= 'class="andi_img_over_link glightbox" ';
            $gallery .= 'data-gallery="' . $lightboxName . '">';

            $gallery .= '<img src="' . $urlHtml . '" ';
            $gallery .= 'alt="Bildnummer ' . $bildzahl . '" ';
            $gallery .= 'class="andi_img_over_effect" ';
            $gallery .= 'loading="lazy">';

            $gallery .= '</a>';
            $gallery .= '</div>';
            $gallery .= '</div>';
        }

        $gallery .= '</div>';
        $gallery .= '</div>';
        $gallery .= '<span id="load_more"></span>';

        return $gallery;
    }
}
