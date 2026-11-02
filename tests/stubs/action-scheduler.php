<?php
/**
 * PHPStan stubs for the Action Scheduler functions Profitly calls.
 *
 * WooCommerce bundles Action Scheduler, but woocommerce-stubs does not include it.
 * Signatures match action-scheduler/functions.php. Never loaded at runtime.
 *
 * @package Profitly
 */

/**
 * Enqueue an action to run as soon as possible.
 *
 * @param string               $hook     Hook name.
 * @param array<string, mixed> $args    Arguments passed to the callback.
 * @param string               $group    Group name.
 * @param bool                 $unique   Whether to skip a duplicate of a pending action.
 * @param int                  $priority Priority.
 * @return int The action ID, or 0 when not enqueued.
 */
function as_enqueue_async_action( $hook, $args = array(), $group = '', $unique = false, $priority = 10 ) {
	return 0;
}

/**
 * Check whether a matching action is pending or running.
 *
 * @param string                    $hook  Hook name.
 * @param array<string, mixed>|null $args  Arguments, or null for any.
 * @param string                    $group Group name.
 * @return bool
 */
function as_has_scheduled_action( $hook, $args = null, $group = '' ) {
	return false;
}

/**
 * Cancel all matching actions.
 *
 * @param string               $hook  Hook name.
 * @param array<string, mixed> $args  Arguments.
 * @param string               $group Group name.
 * @return void
 */
function as_unschedule_all_actions( $hook, $args = array(), $group = '' ) {
}
