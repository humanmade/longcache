<?php

namespace Longcache;

use WP_CLI_Command;
use WP_CLI;

class CLI_Command extends WP_CLI_Command {
	/**
	 * List URLs that would be flushed when a post changes.
	 *
	 * @subcommand list-urls-to-invalidate-for-post
	 * @synopsis <post-id> [--format=<format>]
	 *
	 * @param array $args
	 * @param array $args_assoc
	 * @return void
	 */
	public function list_urls_to_invalidate_for_post( array $args, array $args_assoc ) : void {
		$post_id = $args[0];
		$args_assoc = array_merge( [
			'format' => 'table',
		], $args_assoc );

		$urls = get_urls_to_invalidate_for_post( $post_id );

		$urls = array_map( function ( string $url ) : array {
			return [ 'url' => $url ];
		}, $urls );

		WP_CLI\Utils\format_items( $args_assoc['format'], $urls, [ 'url' ] );
	}

	/**
	 * Invalidate URLs that are associated with a given post.
	 *
	 * @subcommand invalidate-urls-for-post
	 * @synopsis <post-id> [--format=<format>]
	 *
	 * @param array $args
	 * @param array $args_assoc
	 * @return void
	 */
	public function invalidate_urls_for_post( array $args, array $args_assoc ) : void {
		$post_id = $args[0];
		$urls = get_urls_to_invalidate_for_post( $post_id );

		$result = invalidate_urls( $urls );

		if ( ! $result ) {
			WP_CLI::error( 'There was an error when trying to flush the URLs.' );
		}

		WP_CLI::success( 'Flush request succeeded.' );
	}

	/**
	 * Flush URls
	 *
	 * @synopsis [<url>...]
	 *
	 * @return void
	 */
	public function invalidate( array $args, array $args_assoc ) : void {
		if ( $args[0] === '-' ) {
			$urls = explode( "\n", stream_get_contents( STDIN ) );
		} else {
			$urls = $args;
		}

		$urls = array_map( 'trim', $urls );

		$result = invalidate_urls( $urls );

		if ( ! $result ) {
			WP_CLI::error( 'There was an error when trying to flush the URLs.' );
		}

		WP_CLI::success( 'Flush request succeeded.' );
	}

	/**
	 * Remove all entries from the log table.
	 *
	 * @subcommand truncate-log
	 *
	 * @param array $args
	 * @param array $args_assoc
	 * @return void
	 */
	public function truncate_log( array $args, array $args_assoc ) : void {
		Log\delete_entries();
		WP_CLI::success( 'Log truncated.' );
	}

	/**
	 * Display the log entries for invalidations.
	 *
	 * @synopsis [--limit=<limit>] [--page=<page>] [--format=<format>]
	 * @param array $args
	 * @param array $args_assoc
	 * @return void
	 */
	public function log( array $args, array $args_assoc ) : void {
		$args_assoc = array_merge( [
			'format' => 'table',
			'page' => 1,
			'limit' => 1000,
		], $args_assoc );
		$entries = Log\get_entries( $args_assoc['limit'], $args_assoc['page'] );
		WP_CLI\Utils\format_items( $args_assoc['format'], $entries['entries'], [ 'date', 'url', 'status' ] );
	}
}
