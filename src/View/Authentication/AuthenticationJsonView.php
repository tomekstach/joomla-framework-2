<?php

/**
 * Joomla! Framework Website
 *
 * @copyright  Copyright (C) 2014 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

namespace Katalyst\CM\View\Authentication;

use Katalyst\CM\Model\AuthenticationModel;
use Joomla\View\JsonView;
use Joomla\Input\Input;
use Joomla\Session\Session;
use Katalyst\CM\Exception\APIException;

/**
 * Package JSON view class for the application
 */
class AuthenticationJsonView extends JsonView
{
  /**
   * The input object
   *
   * @var  Input
   */
  private $input = '';

  /**
   * The active package
   *
   * @var  string
   */
  private $package = '';

  /**
   * The package model object.
   *
   * @var  AuthenticationModel
   */
  private $authenticationModel;

  /**
   * The active session
   *
   * @var    Session
   * @since  1.0
   */
  private $session;

  /**
   * Instantiate the view.
   *
   * @param   AuthenticationModel  $authenticationModel  The auth model object.
   */
  public function __construct(AuthenticationModel $authenticationModel)
  {
    $this->authenticationModel = $authenticationModel;
  }

  /**
   * Method to render the view
   *
   * @return  string  The rendered view
   */
  public function render()
  {
    $this->authenticationModel->setSession($this->session);
    $this->authenticationModel->setInput($this->input);

    $method = $this->input->getString('method');

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
      switch ($method) {
        case 'login':
        case 'logout':
        case 'getToken':
        case 'getKey':
          $data = $this->authenticationModel->$method();
          break;

        default:
          throw new APIException('Method Not Allowed!', 405);
          break;
      }
    } elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
      switch ($method) {
        case 'getUsers':
          $data = $this->authenticationModel->$method();
          break;

        case 'getCurrent':
          $data = ['user' => $this->authenticationModel->getUser()];
          break;

        default:
          throw new APIException('Method Not Allowed!', 405);
          break;
      }
    }

    $this->setData($data);

    return parent::render();
  }

  /**
   * Set the input object
   *
   * @param   string  $input  The input object
   *
   * @return  void
   */
  public function setInput(Input $input)
  {
    $this->input = $input;
  }

  /**
   * Set the active session
   *
   * @param   string  $session  The active session
   *
   * @return  void
   */
  public function setSession(Session $session)
  {
    $this->session = $session;
  }
}
