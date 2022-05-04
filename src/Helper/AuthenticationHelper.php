<?php

/**
 * REST API application
 *
 * @copyright  Copyright (C) 2021 Katalyst Education. All rights reserved.
 * @license    Licensed under the MIT license. See LICENSE file in the project root for full license text.
 */

namespace Katalyst\CM\Helper;

use Joomla\Database\DatabaseInterface;

use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\HS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\Signature\Serializer\JWSSerializerManager;
use Jose\Component\Signature\JWSVerifier;

/**
 * Helper interacting with the Authentication API
 */
class AuthenticationHelper
{
  /**
   * The database driver
   *
   * @var  DatabaseInterface
   */
  private $database;

  /**
   * Configuration object
   *
   * @var  Object
   */
  private $config;

  /**
   * Instantiate the helper.
   *
   * @param   DatabaseInterface  $database  The database driver.
   * @param   Object             $config    The configuration object.
   */
  public function __construct(DatabaseInterface $database, $config)
  {
    $this->database = $database;
    $this->config   = $config;
  }

  /**
   * Prepare JWK Key.
   *
   * @return  string
   */
  public function getKey(): string
  {
    $jwk = JWKFactory::createOctKey(1024, ['alg' => 'HS256', 'use' => 'sig']);

    return $jwk->get('k');
  }

  /**
   * Prepare JWK Token.
   *
   * @return  string
   */
  public function getToken($userid = 1): string
  {
    if (strlen($this->config->get('k')) < 50) {
      // To generate key:
      //$jwk = JWKFactory::createOctKey(1024, ['alg' => 'HS256', 'use' => 'sig']);
      return [];
    } else {
      $jwk = new JWK([
        'kty' => 'oct',
        'k' => $this->config->get('k'),
      ]);
    }

    // The algorithm manager with the HS256 algorithm.
    $algorithmManager = new AlgorithmManager([
      new HS256(),
    ]);

    $jwsBuilder = new JWSBuilder($algorithmManager);

    // The payload we want to sign. The payload MUST be a string hence we use our JSON Converter.
    $payload = json_encode([
      'iat' => time(),
      'nbf' => time(),
      'exp' => time() + (int) $this->config->get('tokenExpire'),
      'iss' => 'service-tnt',
      'aud' => 'TNT',
      'uid' => $userid
    ]);

    $jws = $jwsBuilder
      ->create()                               // We want to create a new JWS
      ->withPayload($payload)                  // We set the payload
      ->addSignature($jwk, ['alg' => 'HS256']) // We add a signature with a simple protected header
      ->build();                               // We build it

    $serializer = new CompactSerializer();
    $token = $serializer->serialize($jws, 0);

    return $token;
  }
}