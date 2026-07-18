<?php

declare(strict_types=1);

defined('_JEXEC') or die;

use AndreasRottmann\Plugin\Ajax\ProtectedImage\Extension\ProtectedImage;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class() implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            static function (Container $container): PluginInterface {
                $config = (array) PluginHelper::getPlugin(
                    'ajax',
                    'protectedimage'
                );

                $plugin = new ProtectedImage(
                    $container->get(DispatcherInterface::class),
                    $config
                );

                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
