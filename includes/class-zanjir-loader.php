<?php
/**
 * Registers actions and filters with WordPress.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Loader {

	/**
	 * @var array<int, array{hook: string, callback: callable, priority: int, accepted_args: int}>
	 */
	private $actions = array();

	/**
	 * @var array<int, array{hook: string, callback: callable, priority: int, accepted_args: int}>
	 */
	private $filters = array();

	/**
	 * Register a WordPress action.
	 *
	 * @param string   $hook          Action hook name.
	 * @param callable $callback      Callback function.
	 * @param int      $priority      Priority (default 10).
	 * @param int      $accepted_args Accepted argument count (default 1).
	 */
	public function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = array(
			'hook'          => $hook,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Register a WordPress filter.
	 *
	 * @param string   $hook          Filter hook name.
	 * @param callable $callback      Callback function.
	 * @param int      $priority      Priority (default 10).
	 * @param int      $accepted_args Accepted argument count (default 1).
	 */
	public function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = array(
			'hook'          => $hook,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Register all actions and filters with WordPress.
	 */
	public function run() {
		foreach ( $this->filters as $filter ) {
			add_filter( $filter['hook'], $filter['callback'], $filter['priority'], $filter['accepted_args'] );
		}

		foreach ( $this->actions as $action ) {
			add_action( $action['hook'], $action['callback'], $action['priority'], $action['accepted_args'] );
		}
	}
}
