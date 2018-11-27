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
			'cb'             => $columns['cb'],
			'protected'      => esc_html__( 'Protected', 'portier' ),
			'blogname'       => esc_html__( 'Site', 'portier' ),
			'default_access' => esc_html__( 'Default Access', 'portier' ),
			'allowed_users'  => esc_html__( 'Allowed Users', 'portier' ),
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
			'enable'  => esc_html__( 'Enable protection',  'portier' ),
			'disable' => esc_html__( 'Disable protection', 'portier' ),
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
	 * @see WP_List_Table::display_rows()
	 *
	 * @since 1.1.0
	 */
	public function display_rows() {
		$class = '';
		foreach ( $this->items as $blog ) {
			$blog = $blog->to_array();

			$class = ( 'alternate' == $class ) ? '' : 'alternate';
			// Add site-protected class
			$class .= portier_is_site_protected( $blog['blog_id'] ) ? ' site-protected' : ' site-not-protected';

			echo "<tr class='$class'>";

			$this->single_row_columns( $blog );

			echo "</tr>";
		}
	}

	/**
	 * Remove display of row action links for the list table by using the grandparent's logic
	 *
	 * @see WP_List_Table::handle_row_actions()
	 *
	 * @since 1.3.0
	 *
	 * @param array $blog Current site
	 * @param string $column_name Column name
	 * @param string $primary Primary column name
	 */
	public function handle_row_actions( $blog, $column_name, $primary ) {
		return $column_name === $primary ? '<button type="button" class="toggle-row"><span class="screen-reader-text">' . esc_html__( 'Show more details' ) . '</span></button>' : '';
	}

	/**
	 * Handles the checkbox column output
	 *
	 * @see WP_MS_Sites_List_Table::column_cb()
	 *
	 * @since 1.3.0
	 *
	 * @param array $blog Current site.
	 */
	public function column_cb( $blog ) {
			$blogname = untrailingslashit( $blog['domain'] . $blog['path'] );
		?>
			<label class="screen-reader-text" for="blog_<?php echo $blog['blog_id']; ?>">
																	<?php
																	printf( esc_html__( 'Select %s' ), $blogname );
			?>
			</label>
			<input type="checkbox" id="blog_<?php echo $blog['blog_id']; ?>" name="allblogs[]" value="<?php echo esc_attr( $blog['blog_id'] ); ?>" />
		<?php
	}

	/**
	 * Handles the protected column output
	 *
	 * @since 1.3.0
	 *
	 * @param array $blog Current site
	 * @param string $classes Cell classes
	 * @param string $data Data attributes
	 * @param string $primary Primary column name
	 */
	public function _column_protected( $blog, $classes, $data, $primary ) {
		$protected = portier_is_site_protected( $blog['blog_id'] );
		echo "<td class='$classes' $data>"; ?>
			<i class="dashicons dashicons-shield-alt" title="<?php $protected ? esc_attr_e( 'Site protection is active', 'portier' ) : esc_attr_e( 'Site protection is not active', 'portier' ); ?>"></i>
		</td>
		<?php
	}

	/**
	 * Handles the site name column output
	 *
	 * @since 1.3.0
	 *
	 * @param array $blog Current site
	 */
	public function column_blogname( $blog ) {
		switch_to_blog( $blog['blog_id'] );
		$main_site = is_main_site( $blog['blog_id'] ) ? ' &mdash; <span class="post-state">' . esc_html__( 'Main Site', 'portier' ) . '</span>' : '';
		?>
		<strong>
			<a href="<?php echo esc_url( add_query_arg( 'page', 'portier', admin_url( 'options-general.php' ) ) ); ?>" class="edit"><?php echo get_option( 'blogname' ); ?></a><?php echo $main_site; ?>
		</strong>
		<span class="site-domain"><?php echo $blog['domain']; ?></span>
		<?php
		restore_current_blog();
	}

	/**
	 * Handles the default access column output
	 *
	 * @since 1.3.0
	 *
	 * @param array $blog Current site
	 */
	public function column_default_access( $blog ) {
		switch_to_blog( $blog['blog_id'] );
		$levels = portier_default_access_levels();
		$level  = portier_get_default_access();
		$title = isset( $levels[ $level ] ) ? $levels[ $level ] : esc_html__( 'Allow none', 'portier' );
		?>
		<span title="<?php echo $title; ?>"><?php echo $title; ?></span>
		<?php
		restore_current_blog();
	}

	/**
	 * Handles the allowed users column output
	 *
	 * @since 1.3.0
	 *
	 * @param array $blog Current site
	 */
	public function column_allowed_users( $blog ) {
		switch_to_blog( $blog['blog_id'] );
		$users = portier_get_allowed_users();
		$count = count( $users );

		if ( $count ) {
			$title = implode( ', ', wp_list_pluck(
				array_map( 'get_userdata', array_slice( $users, 0, 5 ) ),
				'user_login'
			) );
			if ( 0 < $count - 5 ) {
				$title = sprintf( esc_html__( '%s and %d more', 'portier' ), $title, $count - 5 );
			}
			?>
		<span class="count" title="<?php echo $title; ?>"><?php printf( _n( '%d user', '%d users', $count, 'portier' ), $count ); ?></span>
			<?php
		} else {
			echo '&mdash;';
		}

		restore_current_blog();
	}

	/**
	 * Handles output for the default column
	 *
	 * @since 1.3.0
	 *
	 * @uses do_action() Calls 'portier_network_sites_custom_column'
	 *
	 * @param array $blog Current site
	 * @param string $column_name Column name
	 */
	public function column_default( $blog, $column_name ) {
		switch_to_blog( $blog['blog_id'] );
		/**
		 * Fires for each registered custom column in the Sites list table.
		 *
		 * @since 1.1.0
		 *
		 * @param string $column_name The name of the column to display.
		 * @param int    $blog_id     The site ID.
		 */
		do_action( 'portier_network_sites_custom_column', $column_name, $blog['blog_id'] );
		restore_current_blog();
	}
}

endif; // class_exists
