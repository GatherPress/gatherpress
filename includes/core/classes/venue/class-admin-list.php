<?php
/**
 * Handles the Venue admin list table.
 *
 * @package GatherPress\Core\Venue
 * @since 0.36.0
 */

namespace GatherPress\Core\Venue;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use GatherPress\Core\Traits\Singleton;
use GatherPress\Core\Venue\Map\Map;

/**
 * Class Admin_List.
 *
 * @since 0.36.0
 */
final class Admin_List {

	/**
	 * Enforces a single instance of this class.
	 */
	use Singleton;

	/**
	 * Registers admin list table hooks.
	 *
	 * @since 0.36.0
	 */
	protected function setup_hooks(): void {
		add_action( 'registered_post_type', array( $this, 'maybe_register_post_type_hooks' ) );
		add_filter( 'default_hidden_columns', array( $this, 'default_hidden_columns' ), 10, 2 );
	}

	/**
	 * Registers list table hooks for supported venue post types.
	 *
	 * @since 0.36.0
	 *
	 * @param string $post_type Registered post type.
	 * @return void
	 */
	public function maybe_register_post_type_hooks( string $post_type ): void {
		if ( ! post_type_supports( $post_type, 'gatherpress-venue-information' ) ) {
			return;
		}

		add_filter( sprintf( 'manage_%s_posts_columns', $post_type ), array( $this, 'set_custom_columns' ) );
		add_action( sprintf( 'manage_%s_posts_custom_column', $post_type ), array( $this, 'custom_columns' ), 10, 2 );
	}

	/**
	 * Adds Venue columns and removes the author column.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Updated columns.
	 */
	public function set_custom_columns( array $columns ): array {
		unset( $columns['author'] );

		$insert = array(
			'physical_details' => __( 'Physical details', 'gatherpress' ),
			'featured_image'   => __( 'Featured image', 'gatherpress' ),
			'static_map'       => __( 'Static map', 'gatherpress' ),
		);

		return array_slice( $columns, 0, 2, true ) + $insert + array_slice( $columns, 2, null, true );
	}

	/**
	 * Hides visual columns by default while preserving user preferences.
	 *
	 * @since 0.36.0
	 *
	 * @param string[] $hidden Screen option hidden columns.
	 * @param object   $screen Current screen.
	 * @return string[] Updated hidden columns.
	 */
	public function default_hidden_columns( array $hidden, object $screen ): array {
		$post_type = property_exists( $screen, 'post_type' ) ? (string) $screen->post_type : '';

		if ( ! post_type_supports( $post_type, 'gatherpress-venue-information' ) ) {
			return $hidden;
		}

		return array_values( array_unique( array_merge( $hidden, array( 'featured_image', 'static_map' ) ) ) );
	}

	/**
	 * Renders custom Venue columns.
	 *
	 * @since 0.36.0
	 *
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function custom_columns( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'physical_details':
				$this->render_physical_details( $post_id );
				break;
			case 'featured_image':
				$thumbnail = get_the_post_thumbnail( $post_id, array( 80, 80 ), array( 'alt' => '' ) );
				echo empty( $thumbnail ) ? '—' : wp_kses_post( $thumbnail );
				break;
			case 'static_map':
				$this->render_static_map( $post_id );
				break;
			default:
				// Other columns are rendered by WordPress.
				break;
		}
	}

	/**
	 * Renders physical venue details.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Venue post ID.
	 * @return void
	 */
	protected function render_physical_details( int $post_id ): void {
		$information = ( new Venue( $post_id ) )->get_information();
		$address     = $this->get_address( $information );
		$details     = array_filter( array( $address, $information['phone'] ) );

		if ( ! empty( $information['website'] ) ) {
			$details[] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $information['website'] ),
				esc_html( $information['website'] )
			);
		}

		if ( empty( $details ) ) {
			echo '—';
			return;
		}

		echo implode( '<br>', array_map( 'wp_kses_post', $details ) );
	}

	/**
	 * Builds a readable address from venue information.
	 *
	 * @since 0.36.0
	 *
	 * @param array<string, string> $information Venue information.
	 * @return string Address.
	 */
	protected function get_address( array $information ): string {
		if ( ! empty( $information['address'] ) ) {
			return esc_html( $information['address'] );
		}

		$street   = trim(
			implode(
				' ',
				array_filter( array( $information['house_number'] ?? '', $information['street'] ?? '' ) )
			)
		);
		$locality = implode(
			', ',
			array_filter(
				array(
					$information['city'] ?? '',
					$information['state'] ?? '',
					$information['postcode'] ?? '',
				)
			)
		);

		return esc_html(
			implode( ', ', array_filter( array( $street, $locality, $information['country'] ?? '' ) ) )
		);
	}

	/**
	 * Renders a stored static map image without generating a map.
	 *
	 * @since 0.36.0
	 *
	 * @param int $post_id Venue post ID.
	 * @return void
	 */
	protected function render_static_map( int $post_id ): void {
		$descriptor = Map::get_instance()->get_stored_descriptor( $post_id );

		if ( empty( $descriptor['url'] ) ) {
			echo '—';
			return;
		}

		$srcset = '';
		if ( ! empty( $descriptor['url_2x'] ) ) {
			$srcset = sprintf(
				' srcset="%1$s 1x, %2$s 2x"',
				esc_url( $descriptor['url'] ),
				esc_url( $descriptor['url_2x'] )
			);
		}

		printf(
			'<img src="%1$s"%2$s width="120" height="80" alt="%3$s" />',
			esc_url( $descriptor['url'] ),
			esc_attr( $srcset ),
			esc_attr( __( 'Static map of venue location', 'gatherpress' ) )
		);
	}
}
