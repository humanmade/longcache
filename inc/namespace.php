<?php

namespace Longcache;

use Altis\Cloud;
use Exception;
use WP_CLI;
use WP_Post;

/**
 * Bootstrap function to set up the plugin.
 *
 * @return void
 */
function bootstrap() : void {
	add_action( 'template_redirect', __NAMESPACE__ . '\\set_cache_ttl' );
	add_action( 'save_post', __NAMESPACE__ . '\\on_save_post', 10, 2 );
	add_action( 'logcache.invalidate_urls', __NAMESPACE__ . '\\on_cron_invalidate_urls' );

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		require_once __DIR__ . '/class-cli-command.php';
		WP_CLI::add_command( 'longcache', __NAMESPACE__ . '\\CLI_Command' );
	}
}

/**
 * Check if the current request should be cached.
 *
 * @return boolean
 */
function should_cache_response() : bool {
	$should_cache = true;

	if ( is_user_logged_in() ) {
		$should_cache = false;
	}

	if ( in_array( $_SERVER['REQUEST_METHOD'], [ 'POST', 'DELETE', 'PUT' ] ) ) {
		$should_cache = false;
	}

	if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
		$should_cache = false;
	}

	return apply_filters( 'longcache.should_cache', $should_cache );
}
/**
 * Set the cache TTL depending on the curernt global scope.
 *
 * @return void
 */
function set_cache_ttl() : void {
	if ( ! should_cache_response() ) {
		return;
	}

	global $batcache;
	$max_age = absint( apply_filters( 'longcache.max-age', DAY_IN_SECONDS * 14 ) ); // 14 days by default.
	if ( ! $batcache || ! is_object( $batcache ) ) {
		header( 'Cache-Control: s-maxage=' . $max_age . ', must-revalidate' );
	} else {
		header( 'Cache-Control: s-maxage=' . $max_age . ', max-age=' . $batcache->max_age . ', must-revalidate' );
	}
}

/**
 * Invalidate URLs on the CDN cache.
 *
 * @param array $urls
 * @return bool
 */
function invalidate_urls( array $urls ) : bool {
	if ( ! $urls ) {
		return true;
	}

	// Delete the URLs from Batcache (they need the full URL).
	array_map( __NAMESPACE__ . '\\batcache_clear_url', $urls );

	$urls = array_map( function ( string $url ) : string {
		$parts = parse_url( $url );
		$path = $parts['path'] ?? '/';

		$url = $path;
		if ( isset( $parts['query'] ) ) {
			$url .= '?' . $parts['query'];
		}
		return $url;
	}, $urls );

	try {
		$result = Cloud\purge_cdn_paths( $urls );
	} catch ( Exception $e ) {
		foreach ( $urls as $url ) {
			Log\insert_entry( $url, 'failed', $e->getMessage() );
		}
		return false;
	}

	foreach ( $urls as $url ) {
		Log\insert_entry( $url, $result ? 'succeeded' : 'failed' );
	}
	return $result;
}

/**
 * Clear cache for a given URL from Batcache
 *
 * This is taken from the batcache plugin.
 *
 * @param string $url
 * @return void
 */
function batcache_clear_url( string $url ) : void {
	if ( empty( $url ) ) {
		return;
	}


	// Remove all query params.
	$url = strtok( $url, '?' );

	if ( 0 === strpos( $url, 'https://' ) ) {
		$url = str_replace( 'https://', 'http://', $url );
	}

	if ( 0 !== strpos( $url, 'http://' ) ) {
		$url = 'http://' . $url;
	}

	$url_key = md5( $url );

	// Make sure batcache is set up as a global group.
	wp_cache_add_global_groups( 'batcache' );

	wp_cache_add( "{$url_key}_version", 0, 'batcache' );
	wp_cache_incr( "{$url_key}_version", 1, 'batcache' );
}

/**
 * Invalidate URLs on the CDN cache.
 *
 * @param array $urls
 * @return void
 */
function queue_invalidate_urls( array $urls ) : void {
	if ( ! $urls ) {
		return;
	}
	wp_schedule_single_event( time() + 5, 'logcache.invalidate_urls', [ $urls ] );
}

/**
 * Callback function for the deferred invalidat urls cron.
 *
 * @param array $urls
 * @return void
 */
function on_cron_invalidate_urls( array $urls ) : void {
	invalidate_urls( $urls );
}

/**
 * Invalidate URLs when a post is saved.
 *
 * @param integer $post_id
 * @param WP_Post $post
 * @return void
 */
function on_save_post( int $post_id, WP_Post $post ) : void {
	if ( $post->post_status !== 'publish' ) {
		return;
	}

	queue_invalidate_urls( get_urls_to_invalidate_for_post( $post_id ) );
}

/**
 * Get the URLs for a given post id.
 *
 * @param integer $post_id
 * @return string[]
 */
function get_urls_to_invalidate_for_post( int $post_id ) : array {
	$urls = [
		get_permalink( $post_id ),
	];

	$urls = apply_filters( 'longcache.urls_to_invalidate_for_post', $urls, $post_id );

	return $urls;
}
