<?php

/**
 * REST API application
 *
 * @copyright  Copyright (C) 2021 Katalyst Education. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

namespace Katalyst\CM\Service;

use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Registry\Registry;

/**
 * Configuration service provider
 */
class ConfigurationProvider implements ServiceProviderInterface
{
  /**
   * Configuration instance
   *
   * @var  Registry
   */
  private $config;

  /**
   * Rounting instance
   *
   * @var  Registry
   */
  private $routing = null;

  /**
   * Constructor.
   *
   * @param   string  $file  Path to the config file.
   *
   * @throws  \RuntimeException
   */
  public function __construct(string $file, string $routingFile = '')
  {
    // Verify the configuration exists and is readable.
    if (!is_readable($file)) {
      throw new \RuntimeException('Configuration file does not exist or is unreadable.');
    }

    $this->config = (new Registry)->loadFile($file);

    if (strlen($routingFile) > 0) {
      if (!is_readable($routingFile)) {
        throw new \RuntimeException('Routing file does not exist or is unreadable.');
      }

      $this->routing = (new Registry)->loadFile($routingFile);
    }

    // Hardcode database driver option
    $this->config->set('database.driver', 'mysql');
  }

  /**
   * Registers the service provider with a DI container.
   *
   * @param   Container  $container  The DI container.
   *
   * @return  void
   */
  public function register(Container $container): void
  {
    $container->share(
      'config',
      function (): Registry {
        return $this->config;
      },
      true
    );

    if ($this->routing !== null) {
      $container->share(
        'routing',
        function (): Registry {
          return $this->routing;
        },
        true
      );
    }
  }
}