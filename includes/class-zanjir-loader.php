<?php
/**
 * Registers actions and filters with WordPress.
 *
 * Accepts the classic Plugin Boilerplate signature:
 * add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 )
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Loader {

	/**
	 * @var array<int, array{hook: string, component: object|string, callback: string, priority: int, accepted_args: int}>
	 */
	private $actions = array();

	/**
	 * @var array<int, array{hook: string, component: object|string, callback: string, priority: int, accepted_args: int}>
	 */
	private $filters = array();

	/**
	 * Register a WordPress action.
	 *
	 * @param string       $hook          Action hook name.
	 * @param object|string $component    Object instance or class name.
	 * @param string       $callback      Method name on the component.
	 * @param int          $priority      Priority (default 10).
	 * @param int          $accepted_args Accepted argument count (default 1).
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Register a WordPress filter.
	 *
	 * @param string       $hook          Filter hook name.
	 * @param object|string $component    Object instance or class name.
	 * @param string       $callback      Method name on the component.
	 * @param int          $priority      Priority (default 10).
	 * @param int          $accepted_args Accepted argument count (default 1).
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Register all actions and filters with WordPress.
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}
