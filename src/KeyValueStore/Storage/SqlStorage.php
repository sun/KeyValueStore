<?php

/**
 * @file
 * Definition of DatabaseBackend.
 */

namespace KeyValueStore;

use Exception;

/**
 * Defines a default key/value store implementation.
 *
 * This is Drupal's default key/value store implementation. It uses the database to store
 * key/value data.
 */
class SqlStorage implements StorageInterface {

  /**
   * @var string
   */
  protected $collection;

  /**
   * Implements KeyValueStore\Storage\StorageInterface::__construct().
   */
  public function __construct($collection) {
    $this->collection = $collection;
  }

  protected function prepareKey($key) {
    return "$this->collection:$key";
  }

  protected function prepareKeys($keys) {
    $prepared_keys = array();
    foreach ($keys as $k) {
      $prepared_keys[] = "$this->collection:$k";
    }
    return $prepared_keys;
  }

  /**
   * Implements KeyValueStore\Storage\StorageInterface::get().
   */
  function get($key) {
    $keys = $this->prepareKeys(array($key));
    $values = $this->getMultiple($keys);
    return reset($values);
  }

  /**
   * Implements KeyValueStore\Storage\StorageInterface::getMultiple().
   */
  public function getMultiple($keys) {
    $keys = $this->prepareKeys($keys);
    try {
      $result = db_query('SELECT name, value FROM {variable} WHERE name IN (:keys)', array(':keys' => $keys));
      $values = array();
      foreach ($result as $item) {
        $item = unserialize($item);
        if ($item) {
          $values[$item->key] = $item;
        }
      }
      return $values;
    }
    catch (Exception $e) {
      // If the database is never going to be available, key/value requests should
      // return FALSE in order to allow exception handling to occur.
      return array();
    }
  }

  /**
   * Implements KeyValueStore\Storage\StorageInterface::set().
   */
  public function set($key, $value) {
    $key = $this->prepareKey($key);
    db_merge('variable')->key(array('name' => $key))->fields(array('value' => serialize($value)))->execute();
  }

  /**
   * Implements KeyValueStore\Storage\StorageInterface::setMultiple().
   */
  public function setMultiple($data) {
    foreach ($data as $key => $value) {
      $key = $this->prepareKey($key);
      $this->set($key, $value);
    }
  }

  /**
   * Implements KeyValueStore\Storage\StorageInterface::delete().
   */
  public function delete($key) {
    $key = $this->prepareKey($key);
    db_delete('variable')
    ->condition('name', $key)
    ->execute();
  }

  /**
   * Implements KeyValueStore\Storage\StorageInterface::deleteMultiple().
   */
  public function deleteMultiple(Array $keys) {
    $keys = $this->prepareKeys($keys);
    // Delete in chunks when a large array is passed.
    do {
      db_delete('variable')
        ->condition('key', array_splice($keys, 0, 1000), 'IN')
        ->execute();
    }
    while (count($keys));
  }
}
