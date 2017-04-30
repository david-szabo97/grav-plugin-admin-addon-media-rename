<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class AdminAddonMediaRenamePlugin
 * @package Grav\Plugin
 */
class AdminAddonMediaRenamePlugin extends Plugin {

  public static function getSubscribedEvents() {
    return [
      'onPluginsInitialized' => ['onPluginsInitialized', 0]
    ];
  }

  /**
   * Initialize the plugin
   */
  public function onPluginsInitialized() {
    if (!$this->isAdmin()) {
        return;
    }
  }
  
}
