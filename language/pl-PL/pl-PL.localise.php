<?php

/**
 * REST API application
 *
 * @copyright  Copyright (C) 2021 Katalyst Education. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\Language\Localise\AbstractLocalise;

/**
 * pl-PL localise class
 *
 * @since  1.0
 */
class Pl_PLLocalise extends AbstractLocalise
{
  /**
   * Returns the potential suffixes for a specific number of items
   *
   * @param   integer  $count  The number of items.
   *
   * @return  array  An array of potential suffixes.
   *
   * @since   1.0
   */
  public function getPluralSuffixes($count)
  {
    if ($count == 0) {
      $return = array('0');
    } elseif ($count == 1) {
      $return = array('1');
    } else {
      $return = array('MORE');
    }

    return $return;
  }

  /**
   * Custom translitrate fucntion to use.
   *
   * @param   string  $string  String to transliterate
   *
   * @return  integer  The number of chars to display when searching.
   *
   * @since   1.0
   */
  public function transliterate($string)
  {
    return str_replace(
      array('a', 'c', 'e', 'g'),
      array('b', 'd', 'f', 'h'),
      $string
    );
  }
}