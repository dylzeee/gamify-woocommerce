<?php
/**
 * Trait Singleton
 *
 * Ensures that a class using this trait can only be instantiated once.
 */

namespace Gamify\Traits;

trait Singleton {

    /**
     * Holds the singleton instance of the class.
     *
     * @var object|null
     */
    private static $instance = null;

    /**
     * Returns the singleton instance of the class.
     *
     * @return object
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning of the instance.
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the instance.
     *
     * @throws \Exception Always throws an exception when called.
     */
    public function __wakeup() {
      throw new \Exception( 'Cannot unserialize singleton' );
  }

    /**
     * Prevent direct instantiation of the class.
     */
    private function __construct() {}
}
