<?php

namespace Longcache\Log;

/**
 * Set up the log.
 *
 * @return void
 */
function bootstrap() : void {
	if ( ! wp_cache_get( 'longcache.table_created' ) ) {
		create_table();
		wp_cache_set( 'longcache.table_created', true );
	}

	if ( ! wp_next_scheduled( 'longcache.log.truncate' ) ) {
		wp_schedule_event( strtotime( '2am' ), 'daily', 'longcache.log.truncate' );
	}

	add_action( 'longcache.log.truncate', __NAMESPACE__ . '\\on_truncate_log' );
}

/**
 * Create the log table
 *
 * @return void
 */
function create_table() : void {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	$query = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}longcache_log` (
		`id` int(20) unsigned NOT NULL AUTO_INCREMENT,
		`date` datetime NOT NULL,
		`url` char(255) NOT NULL,
		`status` varchar(50) NOT NULL,
		`data` longtext,
		PRIMARY KEY (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET={$charset_collate};\n";

	$result = $wpdb->query( $query );
}

/**
 * Insert an entry into the log.
 *
 * @param string $url
 * @return void
 */
function insert_entry( string $url, string $status, $data = null ) : void {
	global $wpdb;
	$wpdb->insert(
		"{$wpdb->prefix}longcache_log",
		[
			'url' => $url,
			'date' => date( 'Y-m-d H:i:s' ),
			'status' => $status,
			'data' => serialize( $data ),
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
		"SELECT SQL_CALC_FOUND_ROWS * FROM {$wpdb->prefix}longcache_log ORDER BY date DESC LIMIT $from, $number",
		ARRAY_A
	);

	$total_items = (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' );

	$results = array_map( function ( array $item ) : array {
		$item['data'] = unserialize( $item['data'] );
		return $item;
	}, $results );

	return [
		'entries' => $results,
		'total_items' => $total_items,
		'total_pages' => ceil( $total_items / $page ),
	];
}

/**
 * Delete all the entries from the log table.
 *
 * @return void
 */
function delete_entries() : void {
	global $wpdb;
	$wpdb->query( "TRUNCATE {$wpdb->prefix}longcache_log" );
}

/**
 * Cron callback function for the longcache.log.truncate job.
 *
 * Clears all entries from the log that are over 30 days.
 *
 * @return void
 */
function on_truncate_log() : void {
	global $wpdb;
	$result = $wpdb->query(
		$wpdb->prepare( "DELETE FROM {$wpdb->prefix}longcache_log WHERE `date` < %s", date( 'Y-m-d H:i:s', strtotime( '30 days ago' ) ) ),
	);
}
