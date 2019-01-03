<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;

/**
* Class AdminAddonMediaRenamePlugin
* @package Grav\Plugin
*/
class AdminAddonMediaRenamePlugin extends Plugin {

  const ROUTE = '/admin-addon-media-rename';
  const TASK_RENAME = 'AdminAddonMediaRenameDoRename';

  public static function getSubscribedEvents() {
    return [
      'onPluginsInitialized' => ['onPluginsInitialized', 0]
    ];
  }

  public function getPath() {
    return '/' . trim($this->grav['admin']->base, '/') . '/' . trim(self::ROUTE, '/');
  }

  public function buildBaseUrl() {
    return rtrim($this->grav['uri']->rootUrl(true), '/') . '/' . trim($this->getPath(), '/');
  }

  public function onPluginsInitialized() {
    if (!$this->isAdmin() || !$this->grav['user']->authenticated) {
      return;
    }

    if ($this->grav['uri']->path() == $this->getPath()) {
      $this->enable([
        'onPagesInitialized' => ['processRenameRequest', 0]
      ]);
      return;
    }

    $this->enable([
      'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
      'onPagesInitialized'  => ['onTwigExtensions',    0],
      'onAdminTaskExecute'  => ['onAdminTaskExecute',  0],
    ]);
  }

  public function onAdminTaskExecute($e) {
    $method = $e['method'];

    if ($method === 'task' . self::TASK_RENAME) {
      // Make sure we have all the data we need
      if (!isset($_POST['file_name']) || !isset($_POST['new_file_name'])) {
        $this->outputError($this->grav['language']->translate(['PLUGIN_ADMIN_ADDON_MEDIA_RENAME.ERROR.INVALID_INPUT']));
      }

      $fileName = $_POST['file_name'];
      $newFileName = $_POST['new_file_name'];
      $replaceAll = (isset($_POST['replace_all'])) ? $_POST['replace_all'] : false;

      $page = $this->grav['admin']->page(true);

      // Only process changes
      if ($fileName != $newFileName) {
        // Locate the media file
        $basePath = $page->path() . DS;

        $filePath = $basePath . $fileName;
        if (!file_exists($filePath)) {
          $this->outputError($this->grav['language']->translate(['PLUGIN_ADMIN_ADDON_MEDIA_RENAME.ERROR.FILE_NOT_FOUND', $filePath]));
        }

        $newFilePath = $basePath . $newFileName;
        if (file_exists($newFilePath)) {
          $this->outputError($this->grav['language']->translate(['PLUGIN_ADMIN_ADDON_MEDIA_RENAME.ERROR.NEW_FILE_EXISTS', $filePath, $newFilePath]));
        }

        if (!rename($filePath, $newFilePath)) {
          $this->outputError($this->grav['language']->translate(['PLUGIN_ADMIN_ADDON_MEDIA_RENAME.ERROR.RENAME_FAILED', $filePath, $newFilePath]));
        }

        if ($replaceAll) {
          $oldUrl = $page->url() . '/' . $fileName;
          $instances = $this->grav['pages']->instances();
          foreach ($instances as $page) {
            $raw = $page->raw();

            // Find all links
            preg_match_all('/(\[[^\]]{0,}\])\(([^\)]{0,})\)/', $raw, $matches);

            // Do replace
            $replaces = 0;
            foreach ($matches[0] as $k => $m) {
              $url = $matches[2][$k];
              $normalizedUrl = $this->grav['uri']->isExternal($url) ? $url : \Grav\Common\Utils::normalizePath($page->url() . '/' . $url);

              if ($normalizedUrl === $oldUrl) {
                $oldUrl = $matches[2][$k];
                $parts = explode('/', $oldUrl);
                $lastPart = count($parts) - 1;
                $parts[$lastPart] = $newFileName;
                $newUrl = implode('/', $parts);

                $newLink = $matches[1][$k] . '(' . $newUrl . ')';
                $raw = str_replace($matches[0][$k], $newLink, $raw);
                $replaces++;
              }
            }

            if ($replaces > 0) {
              $page->raw($raw);
              $page->save();
            }
          }
        }

        // Everything went fine
        header('HTTP/1.1 200 OK');
        die('{}');
      } else {
        $this->outputError($this->grav['language']->translate(['PLUGIN_ADMIN_ADDON_MEDIA_RENAME.ERROR.NO_CHANGES']));
      }
    }

    return false;
  }

  public function onTwigTemplatePaths() {
    $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
  }

  public function onTwigExtensions() {
    $page = $this->grav['admin']->page(true);
    if (!$page) {
      return;
    }

    $modal = $this->grav['twig']->twig()->render('rename-modal.twig.html', $this->config->get('plugins.admin-addon-media-rename.modal'));
    $jsConfig = [
      'PATH' => $this->buildBaseUrl() . '/' . $page->route() . '/task:' . self::TASK_RENAME,
      'MODAL' => $modal
    ];
    $this->grav['assets']->addInlineJs('var ADMIN_ADDON_MEDIA_RENAME = ' . json_encode($jsConfig) . ';', -1000);
    $this->grav['assets']->addCss('plugin://admin-addon-media-rename/admin-addon-media-rename.css', -1000);
    $this->grav['assets']->addJs('plugin://admin-addon-media-rename/admin-addon-media-rename.js', -1000);
  }

  public function outputError($msg) {
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(['error' => ['msg' => $msg]]));
  }

}