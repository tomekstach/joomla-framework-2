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
use Joomla\Event\Dispatcher;
use Joomla\Event\DispatcherInterface;
use Katalyst\CM\EventListener\ErrorSubscriber;
use Psr\Log\LoggerInterface;

/**
 * Event service provider
 */
class EventProvider implements ServiceProviderInterface
{
  /**
   * Registers the service provider with a DI container.
   *
   * @param   Container  $container  The DI container.
   *
   * @return  void
   */
  public function register(Container $container): void
  {
    // This service cannot be protected as it is decorated when the debug bar is available
    $container->alias(Dispatcher::class, DispatcherInterface::class)
      ->share(DispatcherInterface::class, [$this, 'getDispatcherService']);

    $container->share(ErrorSubscriber::class, [$this, 'getErrorSubscriber'], true)
      ->tag('event.subscriber', [ErrorSubscriber::class]);
  }

  /**
   * Get the DispatcherInterface service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  DispatcherInterface
   */
  public function getDispatcherService(Container $container): DispatcherInterface
  {
    $dispatcher = new Dispatcher;

    foreach ($container->getTagged('event.subscriber') as $subscriber) {
      $dispatcher->addSubscriber($subscriber);
    }

    return $dispatcher;
  }

  /**
   * Get the ErrorSubscriber service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  ErrorSubscriber
   */
  public function getErrorSubscriber(Container $container): ErrorSubscriber
  {
    $subscriber = new ErrorSubscriber();
    $subscriber->setLogger($container->get(LoggerInterface::class));

    return $subscriber;
  }
}