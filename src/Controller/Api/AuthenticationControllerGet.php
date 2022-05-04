<?php

/**
 * REST API application
 *
 * @copyright  Copyright (C) 2021 Katalyst Education. All rights reserved.
 * @license    Licensed under the MIT license. See LICENSE file in the project root for full license text.
 */

namespace Katalyst\CM\Controller\Api;

use Joomla\Application\AbstractApplication;
use Joomla\Controller\AbstractController;
use Katalyst\CM\View\Authentication\AuthenticationJsonView;
use Joomla\Input\Input;
use Joomla\Session\Session;
use Joomla\Authentication\Strategies\DatabaseStrategy;

/**
 * API Controller handling the authentication
 *
 * @method         \Katalyst\CareerMapBackend\WebApplication  getApplication()  Get the application object.
 * @property-read  \Katalyst\CareerMapBackend\WebApplication  $app              Application object
 *
 * @since          1.0
 */
class AuthenticationControllerGet extends AbstractController
{
  /**
   * The session object.
   *
   * @var		 Session
   * @since	 1.0
   */
  private $session;

  /**
   * The input object.
   *
   * @var		 Input
   * @since	 1.0
   */
  private $input;

  /**
   * The view object.
   *
   * @var    AuthenticationJsonView
   * @since  1.0
   */
  private $view;

  /**
   * Constructor.
   *
   * @param		AuthenticationJsonView $view		 The view object.
   * @param   Input                $input      The input object.
   * @param   AbstractApplication  $app        The application object.
   * @param		SessionHandler			 $session		 The session object.
   *
   * @since   1.0
   */
  public function __construct(AuthenticationJsonView $view = null, Input $input = null, AbstractApplication $app = null, Session $session = null)
  {
    parent::__construct($input, $app);

    $this->session    = $session;
    $this->input      = $input;
    $this->view       = $view;
  }

  /**
   * Get the active session
   *
   * @return  Session $session	Active session object
   *
   * @since   1.0
   */
  public function getSession(): Session
  {
    return $this->session;
  }

  /**
   * Get the Input object
   *
   * @return  Input $input	Input object
   *
   * @since   1.0
   */
  public function getInput(): Input
  {
    return $this->input;
  }

  /**
   * Execute the controller.
   *
   * @return  boolean
   *
   * @since   1.0
   */
  public function execute(): bool
  {
    // Disable browser caching
    $this->getApplication()->allowCache(false);

    $this->view->setSession($this->getSession());
    $this->view->setInput($this->getInput());

    // This is a JSON response
    $this->getApplication()->mimeType = 'application/json';

    $this->getApplication()->setBody($this->view->render());

    return true;
  }
}