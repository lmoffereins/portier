<?php

/**
 * Portier Network Sites List Table class
 *
 * @package Portier
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Portier_Network_Sites_List_Table' ) ) :
/**
 * The Portier Network Sites List Table
 * 
 * @see WP_MS_Sites_List_Table
 *
 * @since 1.1.0
 */
class Portier_Network_Sites_List_Table extends WP_MS_Sites_List_Table {

	/**
	 * Setup the list table's columns
	 *
	 * @since 1.1.0
	 *
	 * @uses apply_filters() Calls 'portier_network_sites_columns'
	 * 
	 * @return array Columns
	 */
	public function get_columns() {
		$columns = parent::get_columns();

		return (array) apply_filters( 'portier_network_sites_columns', array( 
			'cb'            => $columns['cb'],
			'protected'     => esc_html__( 'Protected', 'portier' ),
			'blogname'      => $columns['blogname'],
			'allowed-users' => esc_html__( 'Allowed Users', 'portier' ),
		) );
	}

	/**
	 * Setup the list table's bulk actions
	 * 
	 * @since 1.1.0
	 *
	 * @uses apply_filters() Calls 'portier_network_sites_bulk_actions'
	 * 
	 * @return array Bulk actions
	 */
	public function get_bulk_actions() {
		return (array) apply_filters( 'portier_network_sites_bulk_actions', array(
			'enable'  => esc_html__( 'Enable',  'portier' ),
			'disable' => esc_html__( 'Disable', 'portier' ),
		) );
	}

	/**
	 * Output the list table's pagination handles
	 *
	 * Removes the mode switcher from inheritance.
	 *
	 * @since 1.1.0
	 * @access protected
	 *
	 * @param string $which
	 */
	protected function pagination( $which ) {
		WP_List_Table::pagination( $which );
	}

	/**
	 * Output the site row contents
	 *
	 * @since 1.1.0
	 *
	 * @uses do_action() Calls 'portier_network_sites_custom_column'
	 */
	public function display_rows() {
		$class = '';
		foreach ( $this->items as $blog ) {
			$blog = $blog->to_array();

			$class = ( 'alternate' == $class ) ? '' : 'alternate';
			$protected = portier_is_site_protected( $blog['blog_id'] ) ? ' site-protected' : '';

			echo "<tr class='$class$protected'>";

			$blogname = ( is_subdomain_install() ) ? str_replace( '.' . get_current_site()->domain, '', $blog['domain'] ) : $blog['path'];

			list( $columns, $hidden ) = $this->get_column_info();

			foreach ( $columns as $column_name => $column_display_name ) {
				switch_to_blog( $blog['blog_id'] );

				$style = '';
				if ( in_array( $column_name, $hidden ) )
					$style = ' style="display:none;"';

				switch ( $column_name ) {
					case 'cb' : ?>
						<th scope="row" class="check-column">
							<label class="screen-reader-text" for="blog_<?php echo $blog['blog_id']; ?>"><?php printf( __( 'Select %s' ), $blogname ); ?></label>
							<input type="checkbox" id="blog_<?php echo $blog['blog_id'] ?>" name="allblogs[]" value="<?php echo esc_attr( $blog['blog_id'] ) ?>" />
						</th>

						<?php
						break;

					case 'protected' :
						echo "<td class='$column_name column-$column_name'$style>"; ?>
							<i class="dashicons dashicons-shield-alt" title="<?php ! empty( $protected ) ? esc_html_e( 'Site protection is active', 'portier' ) : esc_html_e( 'Site protection is not active', 'portier' ); ?>"></i>
						</td>

						<?php
						break;

					case 'blogname' :
						$main_site = is_main_site( $blog['blog_id'] ) ? ' (' . __( 'Main Site', 'portier' ) . ')' : '';
						echo "<td class='column-$column_name $column_name'$style>"; ?>
							<a href="<?php echo esc_url( add_query_arg( 'page', 'portier', admin_url( 'options-general.php' ) ) ); ?>" class="edit"><?php echo get_option( 'blogname' ) . $main_site; ?></a>
							<br/><span><?php echo $blogname; ?></span>
						</td>

						<?php
						break;

					case 'allowed-users' :
						$users = get_option( '_portier_allowed_users', array() );
						$count = count( $users );
						$title = implode( ', ', wp_list_pluck( array_map( 'get_userdata', array_slice( $users, 0, 5 ) ), 'user_login' ) );
						if ( 0 < $count - 5 ) {
							$title = sprintf( esc_html__( '%s and %d more', 'portier' ), $title, $count - 5 );
						}

						echo "<td class='column-$column_name $column_name'$style>"; ?>
							<span class="count" title="<?php echo $title; ?>"><?php printf( _n( '%d user', '%d users', $count, 'portier' ), $count ); ?></span>
						</td>

						<?php
						break;

					default:
						echo "<td class='$column_name column-$column_name'$style>";
						/**
						 * Fires for each registered custom column in the Sites list table.
						 *
						 * @since 1.1.0
						 *
						 * @param string $column_name The name of the column to display.
						 * @param int    $blog_id     The site ID.
						 */
						do_action( 'portier_network_sites_custom_column', $column_name, $blog['blog_id'] );
						echo "</td>";
						break;
					}
				}
			?>
			</tr>
			<?php

			// Restore site context
			restore_current_blog();
		}
	}
}

endif; // class_exists
