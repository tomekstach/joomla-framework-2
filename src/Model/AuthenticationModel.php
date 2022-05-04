<?php

/**
 * Backend part of the Career Map website application
 *
 * @copyright  Copyright (C) 2019 Katalyst Education. All rights reserved.
 * @license    Licensed under the MIT license. See LICENSE file in the project root for full license text.
 */

namespace Katalyst\CM\Model;

use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Model\DatabaseModelInterface;
use Joomla\Model\DatabaseModelTrait;
use Joomla\Input\Input;
use Joomla\Session\Session;
use Joomla\Authentication\Strategies\DatabaseStrategy;
use Katalyst\CM\Exception\AuthenticationException;
use Katalyst\CM\Exception\APIException;
use Katalyst\CM\Helper\AuthenticationHelper;

/**
 * Model class for the authentication view
 *
 * @since  1.0
 */
class AuthenticationModel implements DatabaseModelInterface
{
  use DatabaseModelTrait;

  /**
   * Session object
   *
   * @var    Session
   * @since  1.0
   */
  private $session;

  /**
   * Input object
   *
   * @var    Input
   * @since  1.0
   */
  private $input;

  /**
   * Gender information
   *
   * @var    string
   * @since  1.0
   */
  private $gender;

  /**
   * Configuration
   *
   * @var    Object
   * @since  4.0
   */
  private $config;

  /**
   * The authentication helper object
   *
   * @var  AuthenticationHelper
   */
  private $AuthenticationHelper;

  /**
   * Instantiate the model.
   *
   * @param   DatabaseDriver  $db      The database adapter.
   *
   * @since   1.0
   */
  public function __construct(DatabaseDriver $db, AuthenticationHelper $AuthenticationHelper, $config)
  {
    $this->setDb($db);

    $this->config = $config;
    $this->AuthenticationHelper =  $AuthenticationHelper;
  }

  /**
   * Set the active session
   *
   * @param   Session  $session  The active session
   *
   * @return  void
   *
   * @since   1.0
   */
  public function setSession(Session $session)
  {
    $this->session = $session;
  }

  /**
   * Set the input object
   *
   * @param   Input  $input  The input object
   *
   * @return  void
   *
   * @since   1.0
   */
  public function setInput(Input $input)
  {
    $this->input = $input;
  }

  /**
   * Function to login user
   *
   * @return  array
   *
   * @since   1.0
   */
  public function login()
  {
    $options = [
      'database_table'  => '#__users',
      'username_column' => 'username',
      'password_column' => 'password'
    ];

    $data = [
      'code'     => 200,
      'message'  => 'OK'
    ];

    if ($this->input->get('username', '', 'raw') == '') {
      throw new AuthenticationException('Username is empty!', 403);
    }

    if ($this->input->get('password', '', 'raw') == '') {
      throw new AuthenticationException('Password is empty!', 403);
    }

    $db = $this->getDb();

    $query = $db->getQuery(true)
      ->select($db->quoteName('user_id'))
      ->select($db->quoteName('username'))
      ->select($db->quoteName('level'))
      ->from($db->quoteName('#__users'))
      ->where($db->quoteName('username') . ' = ' . $db->quote($this->input->get('username', '', 'raw')));

    $db->setQuery($query, 0, 1);
    $user = $db->loadObject();

    if (!is_object($user)) {
      throw new AuthenticationException('Wrong username!', 403);
    }

    if ($user->username !== $this->input->get('username', '', 'raw')) {
      throw new AuthenticationException('Wrong username!', 403);
    }

    $authentication = new DatabaseStrategy($this->input, $this->getDb(), $options);

    $username = $authentication->authenticate();

    if (!$username) {
      throw new AuthenticationException('Wrong password!', 403);
    } else {

      $fields = array(
        $db->quoteName('guest') . " = '0'",
        $db->quoteName('userid') . " = " . $db->quote($user->user_id),
        $db->quoteName('username') . " = " . $db->quote($username)
      );

      $query = $db->getQuery(true)
        ->update($db->quoteName('#__session'))
        ->set($fields)
        ->where($db->quoteName('session_id') . ' = ' . $db->quote($this->session->getId()));

      $db->setQuery($query);
      $db->execute();

      $this->session->set('username', $username);
      $this->session->set('userid', $user->user_id);
      $this->session->set('level', $user->level);

      $data = $this->getUser($user->user_id);
    }

    return ['user' => $data];
  }

  /**
   * Function to logout user
   *
   * @return  array
   *
   * @since   1.0
   */
  public function logout(): array
  {
    $db = $this->getDb();

    if (intval($this->session->get('userid')) < 1) {
      throw new AuthenticationException('User already logged out!', 400);
    }

    $query = $db->getQuery(true)
      ->delete($db->quoteName('#__session'))
      ->where($db->quoteName('session_id') . ' = ' . $db->quote($this->session->getId()));
    $db->setQuery($query);
    $db->execute();

    $this->session->set('username', '');
    $this->session->set('userid', 0);
    $this->session->set('level', 0);

    $data = [
      'code'     => 200,
      'message'  => 'Bye!',
      'error'    => false
    ];

    return ['message' => $data];
  }


  /**
   * Method to set word from language file
   *
   * @param string $string The label html code.
   *
   * @return string
   */
  public function labelLang($string)
  {
    if (!empty($string)) {
      $first = explode('{', $string);
      $second = explode('}', $first[1]);

      return str_replace('{' . $second[0] . '}', Text::_($second[0]), $string);
    } else {
      return '';
    }
  }

  /**
   * Method to return user data.
   *
   * @param		integer		$id
   *
   * @return	\stdClass
   *
   * @since		4.5
   * @throws	AuthenticationException
   */
  public function getUser(Int $id = 0): \stdClass
  {
    $db = $this->getDb();

    if ($id == 0) {
      $id = intval($this->session->get('userid', 0));
      if ($id == 0) {
        throw new AuthenticationException('You are not logged in to the TNT!', 403);
      }
    }

    $query = $db->getQuery(true)
      ->select([$db->quoteName('u.user_id', 'userID'), $db->quoteName('u.username'), $db->quoteName('u.useravatar', 'avatar'), $db->quoteName('s.status_name', 'status')])
      ->from($db->quoteName('#__users', 'u'))
      ->join('LEFT', $db->quoteName('#__status', 's'), $db->quoteName('s.id') . ' = ' . $db->quoteName('u.level'))
      ->where($db->quoteName('u.user_id') . ' = :user_idVal')
      ->bind('user_idVal', $id, ParameterType::INTEGER);
    $db->setQuery($query, 0, 1);
    $user = $db->loadObject();

    if ($user === null) {
      throw new AuthenticationException('Wrong User ID!', 403);
    }

    $user->userID = (int) $user->userID;

    return $user;
  }

  /**
   * Method to return a list of users.
   *
   * @return	array
   *
   * @throws	APIException
   */
  public function getUsers(): array
  {
    $db = $this->getDb();

    if (intval($this->session->get('level', 0)) <= 0) {
      throw new APIException('You do not have access to this method!', 403);
    }

    $query = $db->getQuery(true)
      ->select([$db->quoteName('u.user_id', 'userID'), $db->quoteName('u.username'), $db->quoteName('u.useravatar', 'avatar'), $db->quoteName('s.status_name', 'status')])
      ->from($db->quoteName('#__users', 'u'))
      ->join('LEFT', $db->quoteName('#__status', 's'), $db->quoteName('s.id') . ' = ' . $db->quoteName('u.level'));
    $db->setQuery($query);
    $users = $db->loadObjectList();

    foreach ($users as &$user) {
      $user->userID = (int) $user->userID;
    }

    return ['users' => $users];
  }

  /**
   * Method to generate JWT Key - only for admins
   *
   * @return array
   * 
   * @throws	APIException
   */
  public function getKey(): array
  {
    if (!empty($this->session->get('sessionhash')) or intval($this->session->get('level', 0)) < 4) {
      throw new APIException('You do not have access to this method!', 403);
    }

    try {
      $key = $this->AuthenticationHelper->getKey();
    } catch (\Throwable $th) {
      throw new APIException('Something is wrong with JWK Key generation!', 400);
    }

    return ['k' => $key];
  }

  /**
   * Method to generate JWT Token - only for admins
   *
   * @return array
   * 
   * @throws	APIException
   */
  public function getToken(): array
  {
    if (!empty($this->session->get('sessionhash')) or intval($this->session->get('level', 0)) < 4) {
      throw new APIException('You do not have access to this method!', 403);
    }

    try {
      $token = $this->AuthenticationHelper->getToken($this->session->get('userid', 1));
    } catch (\Throwable $th) {
      throw new APIException('Something is wrong with JWS Token generation!', 400);
    }

    return ['jws' => $token];
  }

  /**
   * Private method to return all request headers.
   *
   * @return array
   */
  private function apache2_request_headers(): array
  {
    $out = [];

    foreach ($_SERVER as $key => $value) {
      if (substr($key, 0, 5) == "HTTP_") {
        $key = str_replace(" ", "-", ucwords(strtolower(str_replace("_", " ", substr($key, 5)))));
        $out[$key] = $value;
      } else {
        $out[$key] = $value;
      }
    }
    return $out;
  }
}
