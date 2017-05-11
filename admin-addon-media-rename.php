<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class AdminAddonMediaRenamePlugin
 * @package Grav\Plugin
 */
class AdminAddonMediaRenamePlugin extends Plugin {

  const PATH = '/admin/admin-addon-media-rename';

  public static function getSubscribedEvents() {
    return [
      'onPluginsInitialized' => ['onPluginsInitialized', 0]
    ];
  }

  /**
   * Initialize the plugin
   */
  public function onPluginsInitialized() {
    if (!$this->isAdmin() || !$this->grav['user']->authenticated) {
        return;
    }

    if ($this->grav['uri']->path() == self::PATH) {
      // Make sure we have all the data we need
      if (!isset($_POST['media_path']) || !isset($_POST['file_name']) || !isset($_POST['new_file_name'])) {
        $this->outputError('Invalid Input');
      }

      $mediaPath = $_POST['media_path'];
      $fileName = $_POST['file_name'];
      $newFileName = $_POST['new_file_name'];
      
      // Only process changes
      if ($fileName != $newFileName) {
        // Locate the media file
        $basePath = $this->grav['locator']->findResource('page://' . $mediaPath). DS;

        $filePath = $basePath . $fileName;
        if (!file_exists($filePath)) {
          $this->outputError('Invalid file');
        }

        $newFilePath = $basePath . $newFileName;
        if (!rename($filePath, $newFilePath)) {
          $this->outputError('Rename failed');
        }

        // Everything went fine
        header('HTTP/1.1 200 OK');
        die('{}');
      } else {
        $this->outputError('No changes');
      }
    }

    $this->enable([
      'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
      'onTwigExtensions' => ['onTwigExtensions', 0]
    ]);
  }

  public function onTwigTemplatePaths() {
    $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
  }

  public function onTwigExtensions() {
    $modal = $this->grav['twig']->twig()->render('rename-modal.twig.html', [
      'fields' => [
        [
          'type'  => 'section',
          'title' => 'Rename media',
        ],
        [
          'type'     => 'text',
          'label'    => 'Original name',
          'name'     => 'old_name',
          'readonly' => 'true',
        ],
        [
          'type'     => 'text',
          'label'    => 'Original extension',
          'name'     => 'old_ext',
          'readonly' => 'true',
        ],
        [
          'type'  => 'text',
          'label' => 'New name',
          'name'  => 'new_name',
        ],
        [
          'type'     => 'text',
          'label'    => 'New extension',
          'name'     => 'new_ext',
        ]
      ]
    ]);

    $modal = str_replace("\n", "", $modal);
    $modal = str_replace("\"", "'", $modal);

    $this->grav['assets']->addInlineJs('var ADMIN_ADDON_MEDIA_RENAME = { PATH: "'.self::PATH.'", MODAL: "'.$modal.'" };', 0, false);
    $this->grav['assets']->addJs('plugin://admin-addon-media-rename/admin-addon-media-rename.js', 0, false);
  }

  public function outputError($msg) {
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(['error' => ['msg' => $msg]]));
  }

}
