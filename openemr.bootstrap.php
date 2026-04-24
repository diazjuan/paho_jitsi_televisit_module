<?php

/**
 * Bootstrap file for PAHO Jitsi Televisit custom module
 * Compatible with OpenEMR 7.0.3
 *
 * This file uses the OFFICIAL OpenEMR module system with event listeners
 * It does NOT modify any core OpenEMR files - fully update-safe
 *
 * @package OpenEMR
 * @license GNU General Public License 3
 */

namespace OpenEMR\Modules\PahoJitsiTelevisit;

use OpenEMR\Core\ModulesClassLoader;

/**
 * @global OpenEMR\Core\ModulesClassLoader $classLoader
 */
$classLoader->registerNamespaceIfNotExists(
    'OpenEMR\\Modules\\PahoJitsiTelevisit\\',
    __DIR__ . DIRECTORY_SEPARATOR . 'src'
);

/**
 * @global EventDispatcher $eventDispatcher Injected by the OpenEMR module loader
 */

// Load our Bootstrap class and subscribe to events
$bootstrap = new Bootstrap($eventDispatcher);
$bootstrap->subscribeToEvents();

