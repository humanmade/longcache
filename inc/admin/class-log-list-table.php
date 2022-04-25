<?php

namespace Longcache\Admin;

use Longcache\Log;
use WP_List_Table;

class Log_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( [
			'ajax' => false,
		] );
	}

	/**
	 * Prepare the items and pagination for the table.
	 *
	 * @return void
	 */
	public function prepare_items() : void {
		$columns = $this->get_columns();
		$this->_column_headers = [ $columns, [], [] ];
		$query = Log\get_entries( 100, $this->get_pagenum() );
		$this->items = $query['entries'];
		$this->set_pagination_args( [
			'total_items' => $query['total_items'],
			'per_page' => 100,
		] );
	}

	/**
	 * Get the column names for the table.
	 *
	 * @return array
	 */
	public function get_columns() : array {
		return [
			'url' => __( 'URL', 'longcache' ),
			'date' => __( 'Date', 'longcache' ),
			'status' => __( 'Status', 'longcache' ),
		];
	}

	/**
	 * Output the url column for an item.
	 *
	 * @param array $item
	 * @return void
	 */
	public function column_url( array $item ) : void {
		echo '<span class="code">' . esc_html( $item['url'] ) . '</span>';
	}

	/**
	 * Output the date column for an item.
	 *
	 * @param array $item
	 * @return void
	 */
	public function column_date( array $item ) : void {
		echo '<span class="date">' . esc_html( $item['date'] ) . ' UTC </span>';
	}

	/**
	 * Output the status column for an item.
	 *
	 * @param array $item
	 * @return void
	 */
	public function column_status( array $item ) : void {
		echo '<span class="status" title="' . esc_attr( print_r( $item['data'], true ) ) . '">' . esc_html( $item['status'] ) . '</span>';
	}
}
