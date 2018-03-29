<?php
/**
 * View Admin As - User switcher
 *
 * @author  Jory Hogeveen <info@keraweb.nl>
 * @package View_Admin_As
 */

if ( ! defined( 'VIEW_ADMIN_AS_DIR' ) ) {
	die();
}

/**
 * User switcher view type.
 *
 * @author  Jory Hogeveen <info@keraweb.nl>
 * @package View_Admin_As
 * @since   0.1    View type existed in core.
 * @since   1.8    Created this class.
 * @version 1.8
 * @uses    \VAA_View_Admin_As_Type Extends class
 */
class VAA_View_Admin_As_Users extends VAA_View_Admin_As_Type
{
	/**
	 * The single instance of the class.
	 *
	 * @since  1.8
	 * @static
	 * @var    \VAA_View_Admin_As_Users
	 */
	private static $_instance = null;

	/**
	 * @since  1.8
	 * @var    string
	 */
	protected $type = 'user';

	/**
	 * The icon for this view type.
	 *
	 * @since  1.8
	 * @var    string
	 */
	protected $icon = 'dashicons-admin-users';

	/**
	 * Provide ajax search instead of loading all users at once?
	 *
	 * @since  1.8  Ajax search UI not available yet.
	 * @var    bool
	 */
	protected $ajax_search = false;

	/**
	 * Populate the instance.
	 *
	 * @since   1.8
	 * @access  protected
	 * @param   \VAA_View_Admin_As  $vaa  The main VAA object.
	 */
	protected function __construct( $vaa ) {
		self::$_instance = $this;
		parent::__construct( $vaa );

		$this->priorities = array(
			'toolbar'            => 30,
			'view_title'         => 5,
			'validate_view_data' => 10,
			'update_view'        => 10,
			'do_view'            => 2,
		);

		$this->label          = __( 'Users', VIEW_ADMIN_AS_DOMAIN );
		$this->label_singular = __( 'User', VIEW_ADMIN_AS_DOMAIN );

		if ( ! $this->has_access() ) {
			return;
		}

		$this->init_hooks();

		// Users can also be switched from the user list page.
		if ( 'browse' === $this->store->get_userSettings( 'view_mode' ) ) {
			$this->add_filter( 'user_row_actions', array( $this, 'filter_user_row_actions' ), 10, 2 );
		}
	}

	/**
	 * Apply the user view.
	 *
	 * @since   1.8
	 * @access  public
	 */
	public function do_view() {

		if ( $this->selected && ( ! $this->is_enabled() || $this->ajax_search ) ) {
			// Store the single selected user.
			$this->validate_target_user( $this->selected );
		}

		if ( parent::do_view() ) {

			/**
			 * Change current user object so changes can be made on various screen settings.
			 * wp_set_current_user() returns the new user object.
			 */
			$this->store->set_selectedUser( wp_set_current_user( (int) $this->selected ) );

			// @since  1.6.2  Set the caps for this view (user view).
			if ( isset( $this->store->get_selectedUser()->allcaps ) ) {
				$this->store->set_selectedCaps( $this->store->get_selectedUser()->allcaps );
			}
		}
	}

	/**
	 * Change the VAA admin bar menu title.
	 *
	 * @since   1.8
	 * @access  public
	 * @param   array  $titles  The current title(s).
	 * @return  array
	 */
	public function view_title( $titles = array() ) {
		$current = $this->validate_target_user( $this->selected );
		if ( $current ) {

			$type = $this->label_singular;
			$user = $this->store->get_selectedUser();
			$title = $user->display_name;

			/**
			 * Filter documented in /templates/adminbar-user-items.php
			 */
			$titles[ $type ] = apply_filters( 'vaa_admin_bar_view_title_' . $this->type, $title, $user );

			/**
			 * Filter documented in /templates/adminbar-user-items.php
			 */
			if ( ! $this->store->get_view( 'role' ) && apply_filters( 'vaa_admin_bar_view_title_' . $this->type . '_show_roles', true, $user ) ) {
				$user_roles = array();
				foreach ( (array) $user->roles as $role ) {
					$user_roles[] = $this->store->get_rolenames( $role );
				}
				$titles[ $type ] .= ' <span class="user-role">(' . implode( ', ', $user_roles ) . ')</span>';
			}
		}
		return $titles;
	}

	/**
	 * View update handler (Ajax probably), called from main handler.
	 *
	 * @since   1.8   Renamed from `ajax_handler`
	 * @access  public
	 * @param   null    $null    Null.
	 * @param   mixed   $data    The ajax data for this module.
	 * @param   string  $type    The view type.
	 * @return  bool
	 */
	public function update_view( $null, $data, $type = null ) {

		if ( $type !== $this->type ) {
			return $null;
		}

		if ( $this->validate_view_data( null, $data ) ) {
			$this->store->set_view( $data, $this->type, true );
			return true;
		}
		return false;
	}

	/**
	 * Validate data for this view type
	 *
	 * @since   1.7
	 * @since   1.8    Moved from VAA_View_Admin_As_Controller
	 * @access  public
	 * @param   null   $null  Default return (invalid)
	 * @param   mixed  $data  The view data
	 * @return  mixed
	 */
	public function validate_view_data( $null, $data = null ) {
		// User data must be a number and exists in the loaded array of user id's.
		if ( is_numeric( $data ) && $this->validate_target_user( $data ) ) {
			return $data;
		}
		return $null;
	}

	/**
	 * Add the admin bar items.
	 *
	 * @since   1.5
	 * @since   1.8    Moved from VAA_View_Admin_As_Admin_Bar.
	 * @access  public
	 * @param   \WP_Admin_Bar  $admin_bar  The toolbar object.
	 * @param   string         $root       The root item.
	 */
	public function admin_bar_menu( $admin_bar, $root ) {
		static $done;
		if ( $done ) return;

		if ( ! $this->is_enabled() ) {
			return;
		}

		$main_root = $root;
		$root = $main_root . '-users';
		$title_submenu = false;

		$admin_bar->add_group( array(
			'id'     => $root,
			'parent' => $main_root,
			'meta'   => array(
				'class' => 'ab-sub-secondary',
			),
		) );
		$admin_bar->add_node( array(
			'id'     => $root . '-title',
			'parent' => $root,
			'title'  => VAA_View_Admin_As_Form::do_icon( $this->icon ) . $this->label,
			'href'   => false,
			'meta'   => array(
				'class'    => 'vaa-has-icon ab-vaa-title ab-vaa-toggle active',
				'tabindex' => '0',
			),
		) );

		if ( ! $this->group_user_roles() && 15 < count( $this->get_data() ) ) {
			$admin_bar->add_group( array(
				'id' => $root . '-all',
				'parent' => $root . '-title',
				'meta'   => array(
					'class' => 'vaa-auto-max-height',
				),
			) );

			$title_submenu = true;
			$root = $root . '-all';
		};

		/**
		 * Add items at the beginning of the users group.
		 *
		 * @since   1.5
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 * @param   \WP_Admin_Bar  $admin_bar  The toolbar object.
		 * @param   string         $root       The current root item.
		 * @param   string         $main_root  The main root item.
		 */
		do_action( 'vaa_admin_bar_users_before', $admin_bar, $root, $main_root );

		include VIEW_ADMIN_AS_DIR . 'ui/templates/adminbar-user-actions.php';

		if ( $this->get_data() ) {
			// Add the users.
			include VIEW_ADMIN_AS_DIR . 'ui/templates/adminbar-user-items.php';
		}

		/**
		 * Add items at the end of the users group.
		 *
		 * @since   1.5
		 * @see     'admin_bar_menu' action
		 * @link    https://codex.wordpress.org/Class_Reference/WP_Admin_Bar
		 * @param   \WP_Admin_Bar  $admin_bar  The toolbar object.
		 * @param   string         $root       The current root item.
		 * @param   string         $main_root  The main root item.
		 */
		do_action( 'vaa_admin_bar_users_after', $admin_bar, $root, $main_root );

		$done = true;
	}

	/**
	 * Group the users under their roles?
	 *
	 * @since   1.5  As a parameter in VAA_View_Admin_As_Admin_Bar.
	 * @since   1.8  Moved from VAA_View_Admin_As_Admin_Bar and changed to a function.
	 * @access  public
	 * @return  bool
	 */
	public function group_user_roles() {
		static $check;
		if ( is_bool( $check ) ) return $check;

		$check = false;

		$roles = $this->store->get_roles();

		if ( ! $roles || ! VAA_API::is_view_type_enabled( 'role' ) ) {
			return $check;
		}

		$force = $this->store->get_userSettings( 'force_group_users' );
		$total = count( $this->get_data() ) + count( $roles );

		// If the amount of items (roles and users combined) is more than 15 users, group them under their roles.
		// There are no roles to group users on network pages.
		if ( ! is_network_admin() && ( $force || 15 < $total ) ) {
			$check = true;
		}

		return $check;
	}

	/**
	 * Check if the original user can access a user (view as).
	 * Also checks the current store.
	 *
	 * @since   1.8
	 * @access  public
	 * @param   int|\WP_User  $user
	 * @return  \WP_User
	 */
	public function validate_target_user( $user ) {
		$user_id = ( $user instanceof WP_User ) ? $user->ID : $user;

		$check = $this->get_data( $user_id );
		if ( $check ) {
			return $check;
		}

		$check = $this->filter_users_by_access( array( $user ) );
		if ( ! empty( $check[ $user_id ] ) ) {
			$user = $check[ $user_id ];
			$this->set_data( $user, $user->ID, true );
			return $user;
		}
		return null;
	}

	/**
	 * Search users.
	 *
	 * @since   1.8
	 * @param   array  $args  Function arguments.
	 * @return  \WP_User[]
	 */
	public function search_users( $args = array() ) {
		$this->store_data( $args );
		return $this->get_data();
	}

	/**
	 * Store available users.
	 *
	 * Disable some PHPMD checks for this method.
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 * @todo Refactor to enable above checks?
	 *
	 * @since   1.5
	 * @since   1.6    Moved to this class from main class.
	 * @since   1.6.2  Reduce user queries to 1 for non-network pages with custom query handling.
	 * @since   1.8    Moved from VAA_View_Admin_As_Store.
	 * @access  public
	 * @global  \wpdb  $wpdb
	 * @param   array  $args  Function arguments.
	 * @return  void
	 */
	public function store_data( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			/**
			 * Change the limit for querying users.
			 * @since  1.8
			 * @param  int  $limit  Default: 100.
			 * @return int
			 */
			'limit' => apply_filters( 'view_admin_as_user_query_limit', 100 ),
			'search' => '',
			'search_by' => 'display_name', // @todo: display_name|user_login|user_email
		) );

		$limit = (int) $args['limit'];

		$super_admins = get_super_admins();
		// Load the superior admins.
		$superior_admins = VAA_API::get_superior_admins();

		// Is the current user a super admin?
		$is_super_admin = VAA_API::is_super_admin();
		// Is it also one of the manually configured superior admins?
		$is_superior_admin = VAA_API::is_superior_admin();

		/**
		 * Base user query.
		 * Also gets the roles from the user meta table.
		 * Reduces queries to 1 when getting the available users.
		 *
		 * @since  1.6.2
		 * @todo   Use it for network pages as well?
		 * @todo   Check options https://github.com/JoryHogeveen/view-admin-as/issues/24.
		 */
		$user_query = array(
			'select'    => "SELECT users.*, usermeta.meta_value AS roles",
			'from'      => "FROM {$wpdb->users} users",
			'left_join' => "INNER JOIN {$wpdb->usermeta} usermeta ON ( users.ID = usermeta.user_id )",
			'where'     => "WHERE ( usermeta.meta_key = '{$wpdb->get_blog_prefix()}capabilities' )",
			'order_by'  => "ORDER BY users.display_name ASC",
			'limit'     => 'LIMIT ' . $limit,
		);

		/**
		 * Search for users.
		 * @since  1.8
		 * @link https://developer.wordpress.org/reference/classes/wp_user_query/prepare_query/
		 * @link https://developer.wordpress.org/reference/classes/wp_user_query/get_search_sql/
		 */
		if ( ! empty( $args['search'] ) ) {
			if ( ! in_array( $args['search_by'], array( 'display_name', 'user_login', 'user_email' ), true ) ) {
				$args['search_by'] = 'display_name';
			}
			$args['search'] = esc_sql( $args['search'] );
			$user_query['where'] .= " AND users.{$args['search_by']} LIKE '%{$args['search']}%'";
		}

		if ( is_network_admin() ) {

			/**
			 * Super admins are only available for superior admins.
			 * (short circuit return for performance).
			 * @since  1.6.3
			 */
			if ( ! $is_superior_admin ) {
				return;
			}

			// Get super admins (returns login's).
			$users = $super_admins;
			// Remove current user.
			if ( in_array( $this->store->get_curUser()->user_login, $users, true ) ) {
				unset( $users[ array_search( $this->store->get_curUser()->user_login, $users, true ) ] );
			}

			// Convert login to WP_User objects and filter them for superior admins.
			foreach ( $users as $key => $user_login ) {
				$user = get_user_by( 'login', $user_login );
				// Compare user ID with superior admins array.
				if ( isset( $user->ID ) && ! in_array( (int) $user->ID, $superior_admins, true ) ) {
					$users[ $key ] = $user;
				} else {
					unset( $users[ $key ] );
				}
			}

			// @todo Maybe build network super admins where clause for SQL instead of `get_user_by`.

			/*
			if ( ! empty( $users ) && $include = implode( ',', array_map( 'strval', $users ) ) ) {
				$user_query['where'] .= " AND users.user_login IN ({$include})";
			}
			*/

		} else {

			/**
			 * Exclude current user and superior admins (values are user ID's).
			 *
			 * @since  1.5.2  Exclude the current user.
			 * @since  1.6.2  Exclude in SQL format.
			 */
			$exclude = implode( ',',
				array_unique(
				    array_map( 'absint',
						array_merge( $superior_admins, array( $this->store->get_curUser()->ID ) )
				    )
				)
			);
			$user_query['where'] .= " AND users.ID NOT IN ({$exclude})";

			/**
			 * Do not get regular admins for normal installs.
			 *
			 * @since  1.5.2  WP 4.4+ only >> ( 'role__not_in' => 'administrator' ).
			 * @since  1.6.2  Exclude in SQL format (Not WP dependent).
			 */
			if ( ! is_multisite() && ! $is_superior_admin ) {
				$user_query['where'] .= " AND usermeta.meta_value NOT LIKE '%administrator%'";
			}

			/**
			 * Do not get super admins for network installs (values are usernames).
			 * These we're filtered after query in previous versions.
			 *
			 * @since  1.6.3
			 */
			if ( is_multisite() && ! $is_superior_admin && ! empty( $super_admins[0] ) ) {
				// Escape usernames just to be sure.
				$super_admins = array_filter( $super_admins, 'validate_username' );
				// Pre WP 4.4 - Remove empty usernames since these return true before WP 4.4.
				$super_admins = array_filter( $super_admins );

				$exclude_siblings = "'" . implode( "','", $super_admins ) . "'";
				$user_query['where'] .= " AND users.user_login NOT IN ({$exclude_siblings})";
			}

			// Run query (OBJECT_K to set the user ID as key).
			// @codingStandardsIgnoreLine >> $wpdb->prepare() not needed
			$users_results = $wpdb->get_results( implode( ' ', $user_query ), OBJECT_K );

			if ( $users_results ) {

				$users = array();
				// Temp set users.
				$this->set_data( $users_results );
				// @hack  Short circuit the meta queries (not needed).
				add_filter( 'get_user_metadata', array( $this, '_filter_get_user_capabilities' ), 10, 3 );

				// Turn query results into WP_User objects.
				foreach ( $users_results as $user ) {
					$user->roles = maybe_unserialize( $user->roles );
					$users[ $user->ID ] = new WP_User( $user );
				}

				// @hack  Restore the default meta queries.
				remove_filter( 'get_user_metadata', array( $this, '_filter_get_user_capabilities' ) );
				// Clear temp users.
				$this->set_data( array() );

			} else {

				// @todo Notice on debug? If so, check if the query gave an error before doing so...

				// Fallback to WP native functions.
				$user_args = array(
					'orderby' => 'display_name',
					// @since  1.5.2  Exclude the current user.
					'exclude' => array_merge( $superior_admins, array( $this->store->get_curUser()->ID ) ),
					// @since  1.8  Limit the number of users to return.
					'number' => $limit,
				);
				// @since  1.5.2  Do not get regular admins for normal installs (WP 4.4+).
				if ( ! is_multisite() && ! $is_superior_admin ) {
					$user_args['role__not_in'] = 'administrator';
				}
				// @since  1.8  Search for users.
				if ( ! empty( $args['search'] ) ) {
					$user_args['search'] = $args['search'];
					$user_args['search_columns'] = (array) $args['search_by'];
				}

				$users = get_users( $user_args );
			}

			// @since  1.8  Switch to ajax search because of load time.
			if ( $limit <= count( $users ) ) {
				$this->ajax_search = true;
				return;
			}

			// Sort users by role and filter them on available roles.
			$users = $this->filter_sort_users_by_role( $users );
		} // End if().

		$users = $this->filter_users_by_access( $users );

		$this->set_data( $users );
	}

	/**
	 * Filter the WP_User object construction to short circuit the extra meta queries.
	 *
	 * FOR INTERNAL USE ONLY!!!
	 * @hack
	 * @internal
	 *
	 * @since   1.6.2
	 * @since   1.8    Moved to this class from VAA_View_Admin_As_Store
	 * @see     \WP_User::for_site() ( prev: \WP_User::_init_caps() ) >> wp-includes/class-wp-user.php
	 * @see     get_metadata() >> `get_user_metadata` filter
	 * @link    https://developer.wordpress.org/reference/functions/get_metadata/
	 *
	 * @global  \wpdb   $wpdb
	 * @param   null    $null      The value get_metadata() should return.
	 * @param   int     $user_id   Object ID.
	 * @param   string  $meta_key  Meta key.
	 * @return  mixed
	 */
	public function _filter_get_user_capabilities( $null, $user_id, $meta_key ) {
		global $wpdb;
		if ( $wpdb->get_blog_prefix() . 'capabilities' === $meta_key && array_key_exists( $user_id, $this->get_data() ) ) {

			$roles = $this->get_data( $user_id )->roles;
			if ( is_string( $roles ) ) {
				// It is still raw DB data, unserialize it.
				$roles = maybe_unserialize( $roles );
			}

			// Always return an array format due to $single handling (unused 4th parameter).
			return array( $roles );
		}
		return $null;
	}

	/**
	 * Filter users and remove those who the selected user can't access.
	 *
	 * Disable some PHPMD checks for this method.
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 * @todo Refactor to enable above checks?
	 *
	 * @since   1.8
	 * @access  public
	 * @param   \WP_User[]|\WP_User    $users
	 * @param   null|int|\WP_User  $user_id
	 * @return  array
	 */
	public function filter_users_by_access( $users, $user_id = null ) {

		if ( ! is_array( $users ) ) {
			$users = array( $users );
		}

		$super_admins = get_super_admins();
		// Load the superior admins.
		$superior_admins = VAA_API::get_superior_admins();

		// Is the user a super admin?
		$is_super_admin = VAA_API::is_super_admin( $user_id );
		// Is it also one of the manually configured superior admins?
		$is_superior_admin = VAA_API::is_superior_admin( $user_id );

		foreach ( $users as $user_key => $user ) {

			if ( ! $user instanceof WP_User ) {
				$user = get_user_by( 'ID', $user );
				unset( $users[ $user_key ] );
				if ( ! $user instanceof WP_User ) {
					continue;
				}
				$user_key = $user->ID;
				$users[ $user_key ] = $user;
			}

			// If the current user is not a superior admin, run the user filters.
			if ( true !== $is_superior_admin ) {

				/**
				 * Implement in_array() on get_super_admins() check instead of is_super_admin().
				 * Reduces the amount of queries while the end result is the same.
				 *
				 * @since  1.5.2
				 * @see    get_super_admins() >> wp-includes/capabilities.php
				 * @see    is_super_admin() >> wp-includes/capabilities.php
				 * @link   https://developer.wordpress.org/reference/functions/is_super_admin/
				 */
				if ( // Remove super admins for multisites.
					( is_multisite() && in_array( $user->user_login, (array) $super_admins, true ) ) ||
					// Remove regular admins for normal installs.
					( ! is_multisite() && $user->has_cap( 'administrator' ) ) ||
					// Remove users who can access this plugin for non-admin users with the view_admin_as capability.
					( ! $is_super_admin && $user->has_cap( 'view_admin_as' ) )
				) {
					unset( $users[ $user_key ] );
					continue;
				}
			}

			// @since  1.7.6  Remove users who are not allowed to be edited by this user.
			if ( ! current_user_can( 'edit_user', $user->ID ) ) {
				unset( $users[ $user_key ] );
				continue;
			}
		}

		return $users;
	}

	/**
	 * Sort users by role.
	 * Only done if roles are stored (role type enabled and initialized before the user type).
	 *
	 * @since   1.1
	 * @since   1.6    Moved to this class from main class.
	 * @since   1.7.1  User ID as array key.
	 * @access  public
	 *
	 * @see     store_users()
	 *
	 * @param   \WP_User[]  $users  Array of user objects (WP_User).
	 * @return  \WP_User[]  $users
	 */
	public function filter_sort_users_by_role( $users ) {
		$roles = $this->store->get_roles();
		if ( ! $roles ) {
			return $users;
		}
		$tmp_users = array();
		foreach ( $roles as $role => $role_data ) {
			foreach ( $users as $user ) {
				// Reset the array to make sure we find a key.
				// Only one key is needed to add the user to the list of available users.
				reset( $user->roles );
				if ( current( $user->roles ) === $role ) {
					$tmp_users[ $user->ID ] = $user;
				}
			}
		}
		$users = $tmp_users;
		return $users;
	}

	/**
	 * Filter function to add view-as links on user rows in users.php.
	 *
	 * @since   1.6
	 * @since   1.6.3   Check whether to place link + reset link for current user.
	 * @since   1.8     Moved to this class from VAA_View_Admin_As_UI.
	 * @access  public
	 * @param   array     $actions  The existing actions.
	 * @param   \WP_User  $user     The user object.
	 * @return  array
	 */
	public function filter_user_row_actions( $actions, $user ) {

		if ( is_network_admin() ) {
			$link = network_admin_url();
		} else {
			$link = admin_url();
		}

		if ( $user->ID === $this->store->get_curUser()->ID ) {
			// Add reset link if it is the current user and a view is selected.
			if ( $this->store->get_view() ) {
				$link = VAA_API::get_reset_link( $link );
			} else {
				$link = false;
			}
		}
		elseif ( $this->store->get_users( $user->ID ) || $this->filter_users_by_access( array( $user ) ) ) {
			$link = VAA_API::get_vaa_action_link( array( $this->type => $user->ID ), $this->store->get_nonce( true ), $link );
		} else {
			$link = false;
		}

		if ( $link ) {
			$icon = 'dashicons-visibility';
			$icon_attr = array(
				'style' => array(
					'font-size: inherit;',
					'line-height: inherit;',
					'display: inline;',
					'vertical-align: text-top;',
				),
			);
			$title = VAA_View_Admin_As_Form::do_icon( $icon, $icon_attr ) . ' ' . esc_html__( 'View as', VIEW_ADMIN_AS_DOMAIN );
			$actions['vaa_view'] = '<a href="' . $link . '">' . $title . '</a>';
		}
		return $actions;
	}

	/**
	 * Set the view type data.
	 *
	 * @since   1.8
	 * @access  public
	 * @param   mixed   $val
	 * @param   string  $key     (optional) The data key.
	 * @param   bool    $append  (optional) Append if it doesn't exist?
	 */
	public function set_data( $val, $key = null, $append = true ) {
		$this->store->set_users( $val, $key, $append );
	}

	/**
	 * Get the view type data.
	 *
	 * @since   1.8
	 * @access  public
	 * @param   string  $key  (optional) The data key.
	 * @return  mixed
	 */
	public function get_data( $key = null ) {
		return $this->store->get_users( $key );
	}

	/**
	 * Main Instance.
	 *
	 * Ensures only one instance of this class is loaded or can be loaded.
	 *
	 * @since   1.8
	 * @access  public
	 * @static
	 * @param   \VAA_View_Admin_As  $caller  The referrer class.
	 * @return  \VAA_View_Admin_As_Users  $this
	 */
	public static function get_instance( $caller = null ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $caller );
		}
		return self::$_instance;
	}

} // End class VAA_View_Admin_As_Users.
