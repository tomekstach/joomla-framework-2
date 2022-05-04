<?php

/**
 * REST API application
 *
 * @copyright  Copyright (C) 2021 Katalyst Education. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License Version 2 or Later
 */

namespace Katalyst\CM\Service;

use Joomla\Application\AbstractWebApplication;
use Joomla\Application\Controller\ContainerControllerResolver;
use Joomla\Application\Controller\ControllerResolverInterface;
use Joomla\Application\Web\WebClient;
use Joomla\Console\Application as ConsoleApplication;
use Joomla\Console\Loader\ContainerLoader;
use Joomla\Console\Loader\LoaderInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Session\Session;
use Joomla\Session\Storage\NativeStorage;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\Command\DebugEventDispatcherCommand;
use Joomla\Event\DispatcherInterface;
use Katalyst\CM\Command\UpdateCommand;
use Katalyst\CM\Controller\Api\AuthenticationControllerGet;
use Katalyst\CM\Helper;
use Katalyst\CM\Helper\AuthenticationHelper;
use Katalyst\CM\Model\AuthenticationModel;
use Katalyst\CM\View\Authentication\AuthenticationJsonView;
use Katalyst\CM\WebApplication;
use Joomla\Input\Input;
use Joomla\Router\Command\DebugRouterCommand;
use Joomla\Router\Router;
use Joomla\Router\RouterInterface;
use Joomla\Session\Handler\DatabaseHandler;

use Joomla\Language\LanguageFactory;
use Joomla\Language\Parser\IniParser;
use Joomla\Language\ParserRegistry;
use Joomla\Language\Text;
use Joomla\Language\Language;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Jose\Component\Signature\JWSVerifier;
use RuntimeException;

/**
 * Application service provider
 */
class ApplicationProvider implements ServiceProviderInterface
{
  /**
   * LanguageFactory object to use for testing
   *
   * @var  LanguageFactory
   */
  private static $factory;

  /**
   * Language object
   *
   * @var  Text
   */
  protected $lang;

  /**
   * File parser registry
   *
   * @var  ParserRegistry
   */
  private $parserRegistry;

  /**
   * Registers the service provider with a DI container.
   *
   * @param   Container  $container  The DI container.
   *
   * @return  void
   */
  public function register(Container $container): void
  {
    /*
		 * Application Classes
		 */

    $container->share(ConsoleApplication::class, [$this, 'getConsoleApplicationService'], true);

    // This service cannot be protected as it is decorated when the debug bar is available
    $container->alias(WebApplication::class, AbstractWebApplication::class)
      ->share(AbstractWebApplication::class, [$this, 'getWebApplicationClassService']);

    /*
		 * Application Helpers and Dependencies
		 */

    $container->alias(ContainerLoader::class, LoaderInterface::class)
      ->share(LoaderInterface::class, [$this, 'getCommandLoaderService'], true);

    // This service cannot be protected as it is decorated when the debug bar is available
    $container->alias(ContainerControllerResolver::class, ControllerResolverInterface::class)
      ->share(ControllerResolverInterface::class, [$this, 'getControllerResolverService']);

    $container->alias(Helper::class, 'application.helper')
      ->share('application.helper', [$this, 'getApplicationHelperService'], true);

    $container->alias(AuthenticationHelper::class, 'application.helper.authentication')
      ->share('application.helper.authentication', [$this, 'getApplicationHelperAuthenticationService'], true);

    $container->share(WebClient::class, [$this, 'getWebClientService'], true);

    // This service cannot be protected as it is decorated when the debug bar is available
    $container->alias(RouterInterface::class, 'application.router')
      ->alias(Router::class, 'application.router')
      ->share('application.router', [$this, 'getApplicationRouterService']);

    $container->share(Input::class, [$this, 'getInputClassService'], true);

    $container->share(Session::class, [$this, 'getSessionClassService'], true);

    /*
		 * Console Commands
		 */

    $container->share(DebugEventDispatcherCommand::class, [$this, 'getDebugEventDispatcherCommandService'], true);
    $container->share(DebugRouterCommand::class, [$this, 'getDebugRouterCommandService'], true);
    $container->share(UpdateCommand::class, [$this, 'getUpdateCommandService'], true);

    /*
		 * MVC Layer
		 */

    // Controllers
    $container->alias(TestControllerGet::class, 'controller.api.test')
      ->share('controller.api.test', [$this, 'getControllerApiTestService'], true);

    $container->alias(AuthenticationControllerGet::class, 'controller.api.authentication')
      ->share('controller.api.authentication', [$this, 'getControllerApiAuthenticationService'], true);

    // Models
    $container->alias(AuthenticationModel::class, 'model.authentication')
      ->share('model.authentication', [$this, 'getModelAuthenticationService'], true);

    // Views
    $container->alias(AuthenticationJsonView::class, 'view.authentication.json')
      ->share('view.authentication.json', [$this, 'getViewAuthenticationJsonService'], true);
  }

  /**
   * Get the `application.helper` service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  Helper
   */
  public function getApplicationHelperService(Container $container): Helper
  {
    $helper = new Helper;

    return $helper;
  }

  /**
   * Get the `application.helper.authentication` service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  AuthenticationHelper
   */
  public function getApplicationHelperAuthenticationService(Container $container): AuthenticationHelper
  {
    $config = $container->get('config');

    $helper = new AuthenticationHelper($container->get(DatabaseInterface::class), $config);

    return $helper;
  }

  /**
   * Get the `application.router` service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  RouterInterface
   */
  public function getApplicationRouterService(Container $container): RouterInterface
  {
    $config   = $container->get('config');

    $this->parserRegistry = new ParserRegistry;
    $this->parserRegistry->add(new IniParser);

    $headers = $this->apache2_request_headers();

    if (array_key_exists('Lang', $headers)) {
      $lang = $headers['Lang'];
    } else {
      $lang = $config->get('language');
    }

    $language = new Language($this->parserRegistry, JPATH_ROOT, $lang);
    $language->load();
    $this->lang = new Text($language);

    $router = new Router;

    /*
		 * Web routes
		 */
    //$router->addRoute(new Route(['GET', 'HEAD'], '/', HomepageController::class));

    /*$router->get(
      '/status',
      StatusController::class
    );*/

    /*
		 * API routes
		 */
    $routing  = $container->get('routing');

    foreach ($routing->get('routes') as $route) {
      foreach ($route->defaults as &$default) {
        $default = (array) $default;
      }
      $router->{$route->method}($route->pattern, $config->get('nameSpace') . $route->controller, $route->rules, $route->defaults);
    }

    return $router;
  }

  /**
   * Get the LoaderInterface service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  LoaderInterface
   */
  public function getCommandLoaderService(Container $container): LoaderInterface
  {
    $mapping = [
      DebugEventDispatcherCommand::getDefaultName() => DebugEventDispatcherCommand::class,
      DebugRouterCommand::getDefaultName()          => DebugRouterCommand::class,
      UpdateCommand::getDefaultName()               => UpdateCommand::class,
    ];

    return new ContainerLoader($container, $mapping);
  }

  /**
   * Get the ConsoleApplication service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  ConsoleApplication
   */
  public function getConsoleApplicationService(Container $container): ConsoleApplication
  {
    $application = new ConsoleApplication(new ArgvInput, new ConsoleOutput, $container->get('config'));

    $application->setCommandLoader($container->get(LoaderInterface::class));
    $application->setDispatcher($container->get(DispatcherInterface::class));
    $application->setLogger($container->get(LoggerInterface::class));
    $application->setName('Joomla! Framework v2');

    return $application;
  }

  /**
   * Get the `controller.api.authentication` service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  AuthenticationControllerGet
   */
  public function getControllerApiAuthenticationService(Container $container): AuthenticationControllerGet
  {
    return new AuthenticationControllerGet(
      $container->get(AuthenticationJsonView::class),
      $container->get(Input::class),
      $container->get(WebApplication::class),
      $container->get(Session::class)
    );
  }

  /**
   * Get the controller resolver service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  ControllerResolverInterface
   */
  public function getControllerResolverService(Container $container): ControllerResolverInterface
  {
    return new ContainerControllerResolver($container);
  }

  /**
   * Get the DebugEventDispatcherCommand service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  DebugEventDispatcherCommand
   */
  public function getDebugEventDispatcherCommandService(Container $container): DebugEventDispatcherCommand
  {
    return new DebugEventDispatcherCommand(
      $container->get(DispatcherInterface::class)
    );
  }

  /**
   * Get the DebugRouterCommand service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  DebugRouterCommand
   */
  public function getDebugRouterCommandService(Container $container): DebugRouterCommand
  {
    return new DebugRouterCommand(
      $container->get(Router::class)
    );
  }

  /**
   * Get the Input class service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  Input
   */
  public function getInputClassService(Container $container): Input
  {
    return new Input($_REQUEST);
  }

  /**
   * Get the `model.authentication` service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  AuthenticationModel
   */
  public function getModelAuthenticationService(Container $container): AuthenticationModel
  {
    $config = $container->get('config');

    return new AuthenticationModel($container->get(DatabaseInterface::class), $container->get(AuthenticationHelper::class), $config, $this->lang);
  }

  /**
   * Get the UpdateCommand service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  UpdateCommand
   */
  public function getUpdateCommandService(Container $container): UpdateCommand
  {
    return new UpdateCommand;
  }

  /**
   * Get the `view.authentication.json` service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  AuthenticationJsonView
   */
  public function getViewAuthenticationJsonService(Container $container): AuthenticationJsonView
  {
    return new AuthenticationJsonView(
      $container->get('model.authentication')
    );
  }

  /**
   * Get the WebApplication class service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  WebApplication
   */
  public function getWebApplicationClassService(Container $container): WebApplication
  {
    $application = new WebApplication(
      $container->get(ControllerResolverInterface::class),
      $container->get(RouterInterface::class),
      $container->get(Input::class),
      $container->get('config'),
      $container->get(WebClient::class)
    );

    $application->httpVersion = '2';

    // Inject extra services
    $application->setDispatcher($container->get(DispatcherInterface::class));
    $application->setLogger($container->get(LoggerInterface::class));

    return $application;
  }

  /**
   * Get the web client service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  WebClient
   */
  public function getWebClientService(Container $container): WebClient
  {
    /** @var Input $input */
    $input          = $container->get(Input::class);
    $userAgent      = $input->server->getString('HTTP_USER_AGENT', '');
    $acceptEncoding = $input->server->getString('HTTP_ACCEPT_ENCODING', '');
    $acceptLanguage = $input->server->getString('HTTP_ACCEPT_LANGUAGE', '');

    return new WebClient($userAgent, $acceptEncoding, $acceptLanguage);
  }

  /**
   * Get the Session class service
   *
   * @param   Container  $container  The DI container.
   *
   * @return  Session
   *
   * @since   1.0
   */
  public function getSessionClassService(Container $container): Session
  {
    $db = $container->get(DatabaseInterface::class);
    $config = $container->get('config');
    $force_ssl = !$config->get('dev') ? 'on' : '';
    $expire = intval($config->get('sessionExpire'));
    $headers = $this->apache2_request_headers();

    // Delete expired sessions
    $query = $db->getQuery(true)
      ->delete($db->quoteName('#__session'))
      ->where($db->quoteName('time') . ' < ' . (time() - $expire));
    $db->setQuery($query);
    $db->execute();

    $options = [
      'name' => md5($headers['Host']),
      'expire' => time() + $expire
    ];

    $storage_options = [
      'use_only_cookies' => '1',
      'cookie_secure' => $force_ssl,
      'cookie_httponly' => '1'
    ];

    $ip = $_SERVER['REMOTE_ADDR'];

    ini_set('session.cookie_samesite', 'Lax');

    $session = new Session(new NativeStorage(new DatabaseHandler($db), $storage_options), $container->get(DispatcherInterface::class), $options);

    $origin = parse_url(!empty($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : $_SERVER['HTTP_REFERER']);

    // Authorization by JWT - required for localhost
    if ((substr($headers['Authorization'], 0, '6') == 'Bearer' or $origin['host'] != $_SERVER['SERVER_NAME']) and strpos($_SERVER['SERVER_NAME'], 'localhost') === false) {
      $token = str_replace('Bearer ', '', $headers['Authorization']);

      if (empty($token)) {
        throw new RuntimeException('Forbidden', 403);
      }
      // Our key.
      $jwk = new JWK([
        'kty' => 'oct',
        'k' => $config->get('k'),
      ]);

      // The algorithm manager with the HS256 algorithm.
      $algorithmManager = new AlgorithmManager([
        new HS256(),
      ]);

      // We instantiate our JWS Verifier.
      $jwsVerifier = new JWSVerifier(
        $algorithmManager
      );

      // The serializer manager. We only use the JWS Compact Serialization Mode.
      $serializerManager = new JWSSerializerManager([
        new CompactSerializer(),
      ]);

      // We try to load the token.
      $jws = $serializerManager->unserialize($token);
      $payload = json_decode($jws->getPayload());

      // We verify the signature. This method does NOT check the header.
      $isVerified = $jwsVerifier->verifyWithKey($jws, $jwk, 0);

      if ($isVerified) {
        $hash = md5($payload->uid);
        $query = $db->getQuery(true)
          ->select($db->quoteName('session_id'))
          ->from($db->quoteName('#__session'))
          ->where($db->quoteName('data') . " LIKE '%sessionhash|s:32:\"$hash\"%'");

        $db->setQuery($query, 0, 1);
        $session_id = $db->loadResult();

        if (!empty($session_id)) {
          session_id($session_id);
          $exists = true;
        } else {
          $exists = false;
        }

        $session->start();

        if (!$exists) {
          $time = $session->isNew() ? time() : $session->get('session.timer.start');

          $query = $db->getQuery(true)
            ->select([$db->quoteName('username'), $db->quoteName('level')])
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('user_id') . " = '" . $payload->uid . "'");

          $db->setQuery($query, 0, 1);
          $user = $db->loadObject();

          $columns = [
            $db->quoteName('session_id'),
            $db->quoteName('guest'),
            $db->quoteName('time'),
            $db->quoteName('userid'),
            $db->quoteName('username')
          ];

          $values = [
            $db->quote($session->getId()),
            0,
            $db->quote((int) $time),
            $payload->uid,
            $db->quote($user->username)
          ];

          $query->clear();

          $query->insert($db->quoteName('#__session'))
            ->columns($columns)
            ->values(implode(', ', $values));
          $db->setQuery($query);

          try {
            $db->execute();

            $session->set('User-Agent', $headers['User-Agent']);
            $session->set('Host', $headers['Host']);
            $session->set('IP', $ip);
            $session->set('username', $user->username);
            $session->set('userid', $payload->uid);
            $session->set('sessionhash', $hash);
            $session->set('level', $user->level);
          } catch (RuntimeException $e) {
            throw new RuntimeException('Session is broken!', $e->getCode(), $e);
          }
        }
      } else {
        throw new RuntimeException('Forbidden', 403);
      }
    } else {
      $session->start();

      $query = $db->getQuery(true)
        ->select($db->quoteName('session_id'))
        ->from($db->quoteName('#__session'))
        ->where($db->quoteName('session_id') . ' = ' . $db->quote($session->getId()));

      $db->setQuery($query, 0, 1);
      $exists = $db->loadResult();

      if (
        $session->get('User-Agent') != $headers['User-Agent'] || $session->get('IP') != $ip ||
        $session->get('Host') != $headers['Host']
      ) {
        $exists = false;
      }

      if (!$exists) {
        $query->clear();

        $query = $db->getQuery(true)
          ->delete($db->quoteName('#__session'))
          ->where($db->quoteName('session_id') . ' = ' . $db->quote($session->getId()))
          ->where($db->quoteName('userid') . " <= '1'");
        $db->setQuery($query);
        $db->execute();

        $query->clear();

        $time = $session->isNew() ? time() : $session->get('session.timer.start');

        $columns = [
          $db->quoteName('session_id'),
          $db->quoteName('guest'),
          $db->quoteName('time'),
          $db->quoteName('userid')
        ];

        $values = [
          $db->quote($session->getId()),
          1,
          $db->quote((int) $time),
          0
        ];

        $query->insert($db->quoteName('#__session'))
          ->columns($columns)
          ->values(implode(', ', $values));

        $db->setQuery($query);

        try {
          $db->execute();

          $session->set('User-Agent', $headers['User-Agent']);
          $session->set('Host', $headers['Host']);
          $session->set('IP', $ip);
          $session->set('username', '');
          $session->set('userid', 0);
        } catch (RuntimeException $e) {
          throw new RuntimeException(JText::_('JERROR_SESSION_STARTUP'), $e->getCode(), $e);
        }
      }
    }

    return $session;
  }

  private function apache2_request_headers()
  {
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

  public function getLanguage(Container $container): Language
  {
    $config = $container->get('config');
    // Get language object with the lang tag and debug setting in your configuration
    // This also loads language file /xx-XX/xx-XX.ini and localisation methods /xx-XX/xx-XX.localise.php if available
    $language = Language::getInstance($config->get('language'), $config->get('debug'));

    // Configure Text to use language instance
    Text::setLanguage($language);

    return $language;
  }
}