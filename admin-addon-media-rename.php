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
    if (!$this->isAdmin()) {
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
      'onAssetsInitialized' => ['onAssetsInitialized', 0]
    ]);
  }

  public function onAssetsInitialized() {
    $this->grav['assets']->addInlineJs("
    $(function() {
      $(document).off('click', '[data-dz-name]');
      $(document).on('click', '[data-dz-name]', function(e) {
        var ele = $(this);
        var newFileName = prompt('Change filename: ', ele.text());
        if (newFileName) {
          var data = new FormData();
          data.append('media_path', ele.closest('[data-media-path]').attr('data-media-local'));
          data.append('file_name', ele.text());
          data.append('new_file_name', newFileName);
          fetch('".self::PATH."', { method: 'POST', body: data}).then(res => res.json()).then(function(result) {
            if (result.error) {
              alert('Failed to rename: ' + result.error.msg);
              return;
            }

            ele.text(newFileName);
          });
        }
      });
    });
    ");
  }

  public function outputError($msg) {
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(['error' => ['msg' => $msg]]));
  }

}
