<?php
/**
 * Tag Manager
 * Copyright (c) Webmatch GmbH
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

namespace WbmTagManager\Subscriber\Frontend;

use Enlight\Event\SubscriberInterface;
use WbmTagManager\Services\TagManagerVariables;

/**
 * Class FilterRender
 */
class FilterRender implements SubscriberInterface
{
    /**
     * @var TagManagerVariables
     */
    private $variables;

    /**
     * @var \Shopware_Components_Config
     */
    private $config;

    /**
     * @var \Enlight_Controller_Front
     */
    private $front;

    /**
     * @var string
     */
    private $pluginDir;

    public function __construct(
        TagManagerVariables $variables,
        \Shopware_Components_Config $config,
        \Enlight_Controller_Front $front,
        $pluginDir
    ) {
        $this->variables = $variables;
        $this->config = $config;
        $this->front = $front;
        $this->pluginDir = $pluginDir;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Plugins_ViewRenderer_FilterRender' => 'onFilterRender',
        ];
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     *
     * @return mixed
     */
    public function onFilterRender(\Enlight_Event_EventArgs $args)
    {
        $source = $args->getReturn();

        if (strpos($source, '<html') === false && !$this->front->Request()->isXmlHttpRequest()) {
            return $source;
        }

        $containerId = $this->config->getByNamespace('WbmTagManager', 'wbmTagManagerContainer');
        $prettyPrint = $this->config->getByNamespace('WbmTagManager', 'wbmTagManagerJsonPrettyPrint');

        if (
            $this->config->getByNamespace('WbmTagManager', 'wbmTagManagerActive') &&
            !empty($containerId) &&
            strtolower($this->front->Request()->getModuleName()) != 'backend'
        ) {
            if (!$this->front->Request()->isXmlHttpRequest() || strpos($source, '<html') !== false) {
                $headTag = file_get_contents($this->pluginDir . '/Resources/tags/head.html');
                $bodyTag = file_get_contents($this->pluginDir . '/Resources/tags/body.html');

                $headTag = sprintf($headTag, $containerId);
                $bodyTag = sprintf($bodyTag, $containerId);

                if ($dataLayer = $this->variables->getVariables()) {
                    $headTag = '<script>window.dataLayer = window.dataLayer || [];</script>' .
                        self::prependDataLayer($headTag, $dataLayer, $prettyPrint);
                }

                $source = preg_replace(
                    '/<head>/',
                    '<head>' . $headTag,
                    $source,
                    1
                );

                $source = preg_replace(
                    '/<\/noscript>/',
                    $bodyTag . '</noscript>',
                    $source,
                    1
                );
            } elseif ($dataLayer = $this->variables->getVariables()) {
                $source = self::prependDataLayer($source, $dataLayer, $prettyPrint);
            }
        }

        return $source;
    }

    /**
     * @param $source
     * @param $dataLayer
     * @param bool $prettyPrint
     *
     * @return string
     */
    public static function prependDataLayer($source, $dataLayer, $prettyPrint = false)
    {
        return '<script>window.dataLayer.push(' .
            json_encode($dataLayer, ($prettyPrint) ? JSON_PRETTY_PRINT : null) .
            ');</script>' .
            $source;
    }
}
