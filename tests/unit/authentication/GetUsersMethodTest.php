<?php

/**
 * REST API application
 *
 * @copyright  Copyright (C) 2021 Katalyst Education. All rights reserved.
 * @license    Licensed under the MIT license. See LICENSE file in the project root for full license text.
 */

use PHPUnit\Framework\TestCase;
use Katalyst\CM\Model\AuthenticationModel;
use Katalyst\CM\Exception\APIException;
use Joomla\Database\DatabaseInterface;
use Joomla\Session\Session;
use Katalyst\CM\Helper\AuthenticationHelper;

use Joomla\Language\LanguageFactory;
use Joomla\Language\Parser\IniParser;
use Joomla\Language\ParserRegistry;
use Joomla\Language\Text;
use Joomla\Language\Language;

require_once JPATH_ROOT . '/vendor/autoload.php';

class GetUsersMethodTest extends TestCase
{
  /** @var container */
  private $container;

  /** @var db */
  private $db;

  /** @var input */
  private $input;

  /** @var session */
  private $session;

  /** @var session data */
  protected $data = array();

  /** @var config */
  private $config;

  /** @var AuthenticationModel */
  private $AuthenticationModelUnderTest;

  /**
   * This method is called before a class is made.
   *
   * @access protected
   */
  public static function setUpBeforeClass(): void
  {
    $container = (new Joomla\DI\Container)
      ->registerServiceProvider(new Katalyst\CM\Service\ApplicationProvider)
      ->registerServiceProvider(new Katalyst\CM\Service\ConfigurationProvider(JPATH_ROOT . '/etc/config.json'))
      ->registerServiceProvider(new Joomla\Database\Service\DatabaseProvider);

    $db = $container->get(DatabaseInterface::class);

    // Set up user
    $hash = password_hash('guestPassword', PASSWORD_DEFAULT);
    $columns  = array('username', 'password', 'email', 'level', 'useravatar');
    $values    = array("'guest'", "'$hash'", "'tomasz.stach@gmail.com'", "'4'", "'avatar1.png'");
    $query = $db->getQuery(true)
      ->insert($db->quoteName('#__users'))
      ->columns($db->quoteName($columns))
      ->values(implode(',', $values));
    $db->setQuery($query);
    $db->execute();
  }

  /**
   * Sets up the fixture, for example, opens a network connection.
   * This method is called before a test is executed.
   *
   * @access protected
   */
  protected function setUp(): void
  {
    // Prepareing container
    $this->container = (new Joomla\DI\Container)
      ->registerServiceProvider(new Katalyst\CM\Service\ApplicationProvider)
      ->registerServiceProvider(new Katalyst\CM\Service\ConfigurationProvider(JPATH_ROOT . '/etc/config.json'))
      ->registerServiceProvider(new Joomla\Database\Service\DatabaseProvider);

    // Get DB driver
    $this->db = $this->container->get(DatabaseInterface::class);

    // Get Input object
    $this->input    = $this->container->get(Joomla\Input\Input::class);
    $this->config   = $this->container->get('config');

    $mockSession = $this->getMockBuilder(Session::class)
      ->setMethods(['set', 'get'])
      ->getMock();

    $mockSession->expects($this->any())
      ->method('set')
      ->will($this->returnCallback(array($this, 'setCallback')));

    $mockSession->expects($this->any())
      ->method('get')
      ->will($this->returnCallback(array($this, 'getCallback')));

    // This is test session - not from the Joomla!
    $this->session  = $mockSession;

    // Set up langage
    $parserRegistry = new ParserRegistry;
    $parserRegistry->add(new IniParser);

    $language = new Language($parserRegistry, JPATH_ROOT, 'en-GB');
    $language->load();
    $lang = new Text($language);

    // Create AuthenticationModelUnderTest
    $this->AuthenticationModelUnderTest = new AuthenticationModel($this->db, $this->container->get(AuthenticationHelper::class), $this->config, $lang);
    $this->AuthenticationModelUnderTest->setInput($this->input);
    $this->AuthenticationModelUnderTest->setSession($this->session);
  }

  /**
   * Tears down the fixture, for example, closes a network connection.
   * This method is called after a test is executed.
   *
   * @access protected
   */
  protected function tearDown(): void
  {
  }

  /**
   * This method is called after an object is unset.
   *
   * @access protected
   */
  public static function tearDownAfterClass(): void
  {
    $container = (new Joomla\DI\Container)
      ->registerServiceProvider(new Katalyst\CM\Service\ApplicationProvider)
      ->registerServiceProvider(new Katalyst\CM\Service\ConfigurationProvider(JPATH_ROOT . '/etc/config.json'))
      ->registerServiceProvider(new Joomla\Database\Service\DatabaseProvider);

    $db = $container->get(DatabaseInterface::class);

    // Delete user
    $query = $db->getQuery(true)
      ->delete($db->quoteName('#__users'))
      ->where($db->quoteName('username') . " IN ('guest')");
    $db->setQuery($query);
    $db->execute();

    // Delete sessions
    $query = $db->getQuery(true)
      ->delete($db->quoteName('#__session'))
      ->where($db->quoteName('username') . " IN ('guest')");
    $db->setQuery($query);
    $db->execute();

    // Set autoincrement for table #__users
    $query = $db->getQuery(true)
      ->select('MAX(`user_id`)')
      ->from($db->quoteName('#__users'));
    $db->setQuery($query, 0, 1);
    $id = $db->loadResult();
    $id++;

    $query = "ALTER TABLE `#__users` auto_increment = $id";
    $db->setQuery($query);
    $db->execute();
  }

  /**
   * Simple test for getUsers method.
   */
  public function testShouldReturnUserObjectIfUserIsLoggedIn()
  {
    // Given
    $query = $this->db->getQuery(true)
      ->select('`user_id`')
      ->from($this->db->quoteName('#__users'))
      ->where("`username` = 'guest'");
    $this->db->setQuery($query, 0, 1);
    $userId = $this->db->loadResult();

    $this->session->set('userid', $userId);
    $this->session->set('level', 4);

    $expected = $this->db->getQuery(true)
      ->select('COUNT(`user_id`)')
      ->from($this->db->quoteName('#__users'));
    $this->db->setQuery($query, 0, 1);
    $expected = (int) $this->db->loadResult();

    // When
    $result = $this->AuthenticationModelUnderTest->getUsers();

    // Then
    $this->assertEquals($expected, count($result['users']));

    foreach ($result['users'] as $user) {
      $this->assertObjectHasAttribute('userID', $user);
      $this->assertObjectHasAttribute('username', $user);
      $this->assertObjectHasAttribute('avatar', $user);
      $this->assertObjectHasAttribute('status', $user);
    }
  }

  /**
   * Simple test for getUsers method.
   */
  public function testShouldReturnExceptionIfUserIsNotLoggedIn()
  {
    // Expected
    $this->expectException(APIException::class);
    $this->expectExceptionMessage('You do not have access to this method!');

    // Given

    // When
    $result = $this->AuthenticationModelUnderTest->getUsers();
  }

  /**
   * Callback method which override session->set(variable_name, value)
   *
   * @return void
   */
  public function setCallback()
  {
    $args = func_get_args();

    $this->data[$args[0]] = $args[1];

    return true;
  }

  /**
   * Callback method which override session->get(variable_name)
   *
   * @return void
   */
  public function getCallback()
  {
    $args = func_get_args();

    return @$this->data[$args[0]];
  }
}