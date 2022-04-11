<?php

namespace Longcache\Log;

const table = 'longcache_log';

/**
 * Set up the log.
 *
 * @return void
 */
function bootstrap() : void {

}

/**
 * Create the log table
 *
 * @return void
 */
function create_table() : void {

}

/**
 * Insert an entry into the log.
 *
 * @param string $url
 * @return void
 */
function insert_entry( string $url ) : void {
	global $wpdb;
	$wpdb->insert(
		"{$wpdb->prefix}{longcache_log}",
		[
			'url' => $url,
		]
	);
}

/**
 * Get entries from the log
 *
 * @param integer $number
 * @param integer $page
 * @return array{ entries: list<mixed>, total_items: int, total_pages: int }
 */
function get_entries( int $number, int $page ) : array {
	global $wpdb;

	$from = absint( ( $page - 1 ) * $number );
	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT SQL_CALC_FOUND_ROWS * FROM {$wpdb->prefix}{longcache_log} LIMIT $from, $number",
		),
		ARRAY_A
	);

	$total_items = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

	return [
		'entries' => $results,
		'total_items' => $total_items,
		'total_pages' => ceil( $total_items / $page ),
	];

}
