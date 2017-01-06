<?php
/**
 * View Admin As - Class Store
 *
 * Store class that stores the VAA data for use
 *
 * @author  Jory Hogeveen <info@keraweb.nl>
 * @package view-admin-as
 * @since   1.6
 * @version 1.6.2
 */

! defined( 'VIEW_ADMIN_AS_DIR' ) and die( 'You shall not pass!' );

final class VAA_View_Admin_As_Store
{
	/**
	 * The single instance of the class.
	 *
	 * @since  1.6
	 * @static
	 * @var    VAA_View_Admin_As_Store
	 */
	private static $_instance = null;

	/**
	 * The nonce
	 *
	 * @since  1.3.4
	 * @since  1.6    Moved to this class from main class
	 * @var    string
	 */
	private $nonce = '';

	/**
	 * The parsed nonce
	 *
	 * @since  1.6.2
	 * @var    string
	 */
	private $nonce_parsed = '';

	/**
	 * Database option key
	 *
	 * @since  1.4
	 * @since  1.6    Moved to this class from main class
	 * @var    string
	 */
	private $optionKey = 'vaa_view_admin_as';

	/**
	 * Database option data
	 *
	 * @since  1.4
	 * @since  1.6    Moved to this class from main class
	 * @var    array
	 */
	private $optionData = array(
		'db_version',
	);

	/**
	 * User meta key for settings ans views
	 *
	 * @since  1.3.4
	 * @since  1.6    Moved to this class from main class
	 * @var    bool
	 */
	private $userMetaKey = 'vaa-view-admin-as';

	/**
	 * User meta value for settings ans views
	 *
	 * @since  1.5
	 * @since  1.6    Moved to this class from main class
	 * @var    array
	 */
	private $userMeta = array(
		'settings',
		'views',
	);

	/**
	 * Array of default settings
	 *
	 * @since  1.5
	 * @since  1.6    Moved to this class from main class
	 * @var    array
	 */
	private $defaultSettings = array();

	/**
	 * Array of allowed settings
	 *
	 * @since  1.5
	 * @since  1.6    Moved to this class from main class
	 * @var    array
	 */
	private $allowedSettings = array();

	/**
	 * Array of default settings
	 *
	 * @since  1.5
	 * @since  1.5.2  added force_group_users
	 * @since  1.6    Moved to this class from main class
	 * @since  1.6.1  added freeze_locale
	 * @var    array
	 */
	private $defaultUserSettings = array(
		'admin_menu_location' => 'top-secondary',
		'force_group_users'   => 'no',
		'freeze_locale'       => 'no',
		'hide_front'          => 'no',
		'view_mode'           => 'browse',
	);

	/**
	 * Array of allowed settings
	 * Setting name (key) => array( values )
	 *
	 * @since  1.5
	 * @since  1.5.2  added force_group_users
	 * @since  1.6    Moved to this class from main class
	 * @since  1.6.1  added freeze_locale
	 * @var    array
	 */
	private $allowedUserSettings = array(
		'admin_menu_location' => array( 'top-secondary', 'my-account' ),
		'force_group_users'   => array( 'yes', 'no' ),
		'freeze_locale'       => array( 'yes', 'no' ),
		'hide_front'          => array( 'yes', 'no' ),
		'view_mode'           => array( 'browse', 'single' ),
	);

	/**
	 * Array of available capabilities
	 *
	 * @since  1.3
	 * @since  1.6    Moved to this class from main class
	 * @var    array
	 */
	private $caps = array();

	/**
	 * Array of available roles (WP_Role objects)
	 *
	 * @since  0.1
	 * @since  1.6    Moved to this class from main class
	 * @var    array
	 */
	private $roles = array();

	/**
	 * Array of available users (WP_User objects)
	 *
	 * @since  0.1
	 * @since  1.6    Moved to this class from main class
	 * @var    array
	 */
	private $users = array();

	/**
	 * Array of available user ID's (key) and display names (value)
	 *
	 * @since  0.1
	 * @since  1.6    Moved to this class from main class
	 * @var    array
	 */
	private $userids = array();

	/**
	 * Current user object
	 *
	 * @since  0.1
	 * @since  1.6    Moved to this class from main class
	 * @var    WP_User
	 */
	private $curUser;

	/**
	 * Current user session
	 *
	 * @since  1.3.4
	 * @since  1.6    Moved to this class from main class
	 * @var    string
	 */
	private $curUserSession = '';

	/**
	 * Selected view mode
	 *
	 * Format: array( VIEW_TYPE => VIEW_DATA )
	 *
	 * @since  0.1
	 * @since  1.6    Moved to this class from main class
	 * @var    array
	 */
	private $viewAs = array();

	/**
	 * The selected user object (if the user view is selected)
	 *
	 * @since  0.1
	 * @since  1.6    Moved to this class from main class
	 * @var    WP_User
	 */
	private $selectedUser;

	/**
	 * The selected capabilities (if a view is selected)
	 *
	 * @since  1.6.2
	 * @var    array
	 */
	private $selectedCaps = array();

	/**
	 * Populate the instance
	 * @since  1.6
	 */
	private function __construct() {
		self::$_instance = $this;
	}

	/**
	 * Store available roles
	 *
	 * @todo  Check function wp_roles() >> WP 4.3+
	 *
	 * @since   1.5
	 * @since   1.5.2  Get role objects instead of arrays
	 * @since   1.6    Moved to this class from main class
	 * @access  public
	 * @global  WP_Roles  $wp_roles
	 * @return  void
	 */
	public function store_roles() {
		global $wp_roles;

		// Store available roles
		$roles = $wp_roles->role_objects; // role_objects for objects, roles for arrays
		$role_names = $wp_roles->role_names;

		if ( ! is_super_admin( $this->get_curUser()->ID ) ) {

			// The current user is not a super admin (or regular admin in single installations)
			unset( $roles['administrator'] );

			// @see   https://codex.wordpress.org/Plugin_API/Filter_Reference/editable_roles
			$editable_roles = apply_filters( 'editable_roles', $wp_roles->roles );

			// Current user has the view_admin_as capability, otherwise this functions would'nt be called
			foreach ( $roles as $role_key => $role ) {
				// Remove roles that this user isn't allowed to edit
				if ( ! array_key_exists( $role_key, $editable_roles ) ) {
					unset( $roles[ $role_key ] );
				}
				// Remove roles that have the view_admin_as capability
				elseif ( is_array( $role->capabilities ) && array_key_exists( 'view_admin_as', $role->capabilities ) ) {
					unset( $roles[ $role_key ] );
				}
			}
		}

		// @since  1.5.2.1  Merge role names with the role objects
		foreach ( $roles as $role_key => $role ) {
			if ( isset( $role_names[ $role_key ] ) ) {
				$roles[ $role_key ]->name = $role_names[ $role_key ];
			}
		}

		$this->set_roles( $roles );
	}

	/**
	 * Store available users
	 *
	 * @since   1.5
	 * @since   1.6    Moved to this class from main class
	 * @since   1.6.2  Reduce user queries to 1 for non-network pages with custom query handling
	 * @access  public
	 * @global  wpdb  $wpdb
	 * @return  void
	 */
	public function store_users() {
		global $wpdb;

		// Load the superior admins
		$superior_admins = VAA_API::get_superior_admins();

		// Is the current user a super admin?
		$is_super_admin = is_super_admin( $this->get_curUser()->ID );
		// Is it also one of the manually configured superior admins?
		$is_superior_admin = VAA_API::is_superior_admin( $this->get_curUser()->ID );

		/**
		 * Base user query
		 * Also gets the roles from the user meta table
		 * Reduces queries to 1 when getting the available users
		 *
		 * @since  1.6.2
		 * @todo   Use it for network pages as well?
		 * @todo   Check options https://github.com/JoryHogeveen/view-admin-as/issues/24
		 */
		$user_query = array(
			'select'    => "SELECT users.*, usermeta.meta_value AS roles",
			'from'      => "FROM {$wpdb->users} users",
			'left_join' => "INNER JOIN {$wpdb->usermeta} usermeta ON ( users.ID = usermeta.user_id )",
			'where'     => "WHERE ( usermeta.meta_key = '{$wpdb->get_blog_prefix()}capabilities' )",
			'order_by'  => "ORDER BY users.display_name ASC"
		);

		if ( is_network_admin() ) {

			// Get super admins (returns login's)
			$users = get_super_admins();
			// Remove current user
			if ( in_array( $this->get_curUser()->user_login, $users ) ) {
				unset( $users[ array_search( $this->get_curUser()->user_login, $users ) ] );
			}

			// Convert login to WP_User objects and filter them for superior admins
			foreach ( $users as $key => $user_login ) {
				$user = get_user_by( 'login', $user_login );
				// Compare user ID with superior admins array
				if ( $user && ! in_array( $user->ID, $superior_admins ) ) {
					$users[ $key ] = $user;
				} else {
					unset( $users[ $key ] );
				}
			}

			// @todo Maybe build network super admins where clause for SQL instead of `get_user_by`
			/*if ( ! empty( $users ) && $include = implode( ',', array_map( 'strval', $users ) ) ) {
				$user_query['where'] .= " AND users.user_login IN ({$include})";
			}*/

		} else {

			/**
			 * Exclude current user and superior admins (values are user ID's)
			 *
			 * @since  1.5.2  Exclude the current user
			 * @since  1.6.2  Exclude in SQL format
			 */
			$exclude = implode( ',',
				array_unique(
					array_map( 'absint',
						array_merge( $superior_admins, array( $this->get_curUser()->ID ) )
					)
				)
			);
			$user_query['where'] .= " AND users.ID NOT IN ({$exclude})";

			/**
			 * Do not get regular admins for normal installs
			 *
			 * @since  1.5.2  WP 4.4+ only >> ( 'role__not_in' => 'administrator' )
			 * @since  1.6.2  Exclude in SQL format (Not WP dependent)
			 */
			if ( ! is_multisite() && ! $is_superior_admin ) {
				$user_query['where'] .= " AND usermeta.meta_value NOT LIKE '%administrator%'";
			}

			// Run query (OBJECT_K to set the user ID as key)
			$users_results = $wpdb->get_results( implode( ' ', $user_query ), OBJECT_K );

			if ( $users_results ) {

				$users = array();
				// Temp set users
				$this->set_users( $users_results );
				// @hack  Short circuit the meta queries (not needed)
				add_filter( 'get_user_metadata', array( $this, '_filter_get_user_capabilities' ), 10, 3 );

				// Turn query results into WP_User objects
				foreach ( $users_results as $user ) {
					$user->roles = unserialize( $user->roles );
					$users[ $user->ID ] = new WP_User( $user );
				}

				// @hack  Restore the default meta queries
				remove_filter( 'get_user_metadata', array( $this, '_filter_get_user_capabilities' ) );
				// Clear temp users
				$this->set_users( array() );

			} else {

				// Fallback to WP native functions
				$user_args = array(
					'orderby' => 'display_name',
					// @since  1.5.2  Exclude the current user
					'exclude' => array_merge( $superior_admins, array( $this->get_curUser()->ID ) )
				);
				// @since  1.5.2  Do not get regular admins for normal installs (WP 4.4+)
				if ( ! is_multisite() && ! $is_superior_admin ) {
					$user_args['role__not_in'] = 'administrator';
				}

				$users = get_users( $user_args );
			}

			// Sort users by role and filter them on available roles
			$users = $this->filter_sort_users_by_role( $users );
		}

		// @todo Maybe $userids isn't needed anymore
		$userids = array();

		foreach ( $users as $user_key => $user ) {

			// If the current user is not a superior admin, run the user filters
			if ( true !== $is_superior_admin ) {

				/**
				 * Implement in_array() on get_super_admins() check instead of is_super_admin()
				 * Reduces the amount of queries while the end result is the same.
				 *
				 * @since  1.5.2
				 * @See    wp-includes/capabilities.php >> get_super_admins()
				 * @See    wp-includes/capabilities.php >> is_super_admin()
				 * @link   https://developer.wordpress.org/reference/functions/is_super_admin/
				 */
				//if ( is_super_admin( $user->ID ) ) {
				if ( is_multisite() && in_array( $user->user_login, (array) get_super_admins() ) ) {
					// Remove super admins for multisites
					unset( $users[ $user_key ] );
					continue;
				} elseif ( ! is_multisite() && $user->has_cap('administrator') ) {
					// Remove regular admins for normal installs
					unset( $users[ $user_key ] );
					continue;
				} elseif ( ! $is_super_admin && $user->has_cap('view_admin_as') ) {
					// Remove users who can access this plugin for non-admin users with the view_admin_as capability
					unset( $users[ $user_key ] );
					continue;
				}
			}

			// Add users who can't access this plugin to the users list
			$userids[ $user->data->ID ] = $user->data->display_name;
		}

		$this->set_users( $users );
		$this->set_userids( $userids );
	}

	/**
	 * Filter the WP_User object construction to short circuit the extra meta queries
	 *
	 * FOR INTERNAL USE ONLY!!!
	 * @hack
	 * @internal
	 *
	 * @since   1.6.2
	 * @see     wp-includes/class-wp-user.php WP_User->_init_caps()
	 * @see     get_user_metadata filter in get_metadata()
	 * @link    https://developer.wordpress.org/reference/functions/get_metadata/
	 *
	 * @global  wpdb    $wpdb
	 * @param   null    $null
	 * @param   int     $user_id
	 * @param   string  $meta_key
	 * @return  mixed
	 */
	public function _filter_get_user_capabilities( $null, $user_id, $meta_key ) {
		global $wpdb;
		if ( $wpdb->get_blog_prefix() . 'capabilities' == $meta_key && array_key_exists( $user_id, $this->get_users() ) ) {

			$roles = $this->get_users( $user_id )->roles;
			if ( is_string( $roles ) ) {
				// It is still raw DB data, unserialize it
				$roles = unserialize( $roles );
			}

			// Always return an array format due to $single handling (unused 4th parameter)
			return array( $roles );
		}
		return $null;
	}

	/**
	 * Sort users by role
	 *
	 * @since   1.1
	 * @since   1.6    Moved to this class from main class
	 * @access  public
	 *
	 * @see     store_users()
	 *
	 * @param   array  $users
	 * @return  array  $users
	 */
	public function filter_sort_users_by_role( $users ) {
		if ( ! $this->get_roles() ) {
			return $users;
		}
		$tmp_users = array();
		foreach ( $this->get_roles() as $role => $role_data ) {
			foreach ( $users as $user ) {
				// Reset the array to make sure we find a key
				// Only one key is needed to add the user to the list of available users
				reset( $user->roles );
				if ( $role == current( $user->roles ) ) {
					$tmp_users[] = $user;
				}
			}
		}
		$users = $tmp_users;
		return $users;
	}

	/**
	 * Store available capabilities
	 *
	 * @since   1.4.1
	 * @since   1.6    Moved to this class from main class
	 * @access  public
	 * @global  WP_Roles  $wp_roles
	 * @return  void
	 */
	public function store_caps() {

		// Get all available roles and capabilities
		global $wp_roles;

		// Get current user capabilities
		$caps = $this->get_curUser()->allcaps;

		// Only allow to add capabilities for an admin (or super admin)
		if ( is_super_admin( $this->get_curUser()->ID ) ) {

			// Store available capabilities
			$all_caps = array();
			foreach ( $wp_roles->role_objects as $key => $role ) {
				if ( is_array( $role->capabilities ) ) {
					foreach ( $role->capabilities as $cap => $grant ) {
						$all_caps[ $cap ] = $cap;
					}
				}
			}

			/**
			 * Add compatibility for other cap managers
			 * @since  1.5
			 * @see    VAA_View_Admin_As_Compat->init()
			 * @param  array  $all_caps  All capabilities found in the existing roles
			 * @return array
			 */
			$all_caps = apply_filters( 'view_admin_as_get_capabilities', $all_caps );

			$all_caps = array_unique( $all_caps );

			// Add new capabilities to the capability array as disabled
			foreach ( $all_caps as $capKey => $capVal ) {
				if ( is_string( $capVal ) && ! is_numeric( $capVal ) && ! array_key_exists( $capVal, $caps ) ) {
					$caps[ $capVal ] = 0;
				}
				if ( is_string( $capKey ) && ! is_numeric( $capKey ) && ! array_key_exists( $capKey, $caps ) ) {
					$caps[ $capKey ] = 0;
				}
			}

			/**
			 * Add network capabilities
			 * @since  1.5.3
			 * @see    https://codex.wordpress.org/Roles_and_Capabilities
			 * @todo   Move this to VAA_View_Admin_As_Compat?
			 */
			if ( is_multisite() ) {
				$network_caps = array(
					'manage_network' => 1,
					'manage_sites' => 1,
					'manage_network_users' => 1,
					'manage_network_plugins' => 1,
					'manage_network_themes' => 1,
					'manage_network_options' => 1,
				);
				$caps = array_merge( $network_caps, $caps );
			}
		}

		// Remove role names
		foreach ( $wp_roles->roles as $roleKey => $role ) {
			unset( $caps[ $roleKey ] );
		}
		ksort( $caps );

		$this->set_caps( $caps );
	}

	/**
	 * Store settings based on allowed settings
	 * Also merges with the default settings
	 *
	 * @since   1.5
	 * @since   1.6    Moved to this class from main class
	 * @access  public
	 *
	 * @param   array   $settings
	 * @param   string  $type      global / user
	 * @return  bool
	 */
	public function store_settings( $settings, $type ) {
		if ( $type == 'global' ) {
			$current  = $this->get_settings();
			$defaults = $this->get_defaultSettings();
			$allowed  = $this->get_allowedSettings();
		} elseif ( $type == 'user' ) {
			$current  = $this->get_userSettings();
			$defaults = $this->get_defaultUserSettings();
			$allowed  = $this->get_allowedUserSettings();
		} else {
			return false;
		}
		if ( ! is_array( $current ) ) {
			$current = $defaults;
		}
		foreach ( $settings as $setting => $value ) {
			// Only allow the settings when it exists in the defaults and the value exists in the allowed settings
			if ( array_key_exists( $setting, $defaults ) && in_array( $value, $allowed[ $setting ] ) ) {
				$current[ $setting ] = $value;
				// Some settings need a reset
				if ( in_array( $setting, array( 'view_mode' ) ) ) {
					View_Admin_As( $this )->view()->reset_view();
				}
			}
		}
		if ( $type == 'global' ) {
			$new = $this->validate_settings( wp_parse_args( $current, $defaults ), 'global' );
			return $this->update_optionData( $new, 'settings', true );
		} elseif ( $type == 'user' ) {
			$new = $this->validate_settings( wp_parse_args( $current, $defaults ), 'user' );
			return $this->update_userMeta( $new, 'settings', true );
		}
		return false;
	}

	/**
	 * Validate setting data based on allowed settings
	 * Also merges with the default settings
	 *
	 * @since   1.5
	 * @since   1.6    Moved to this class from main class
	 * @access  public
	 *
	 * @param   array       $settings
	 * @param   string      $type      global / user
	 * @return  array|bool  $settings / false
	 */
	public function validate_settings( $settings, $type ) {
		if ( $type == 'global' ) {
			$defaults = $this->get_defaultSettings();
			$allowed  = $this->get_allowedSettings();
		} elseif ( $type == 'user' ) {
			$defaults = $this->get_defaultUserSettings();
			$allowed  = $this->get_allowedUserSettings();
		} else {
			return false;
		}
		$settings = wp_parse_args( $settings, $defaults );
		foreach ( $settings as $setting => $value ) {
			if ( ! array_key_exists( $setting, $defaults ) ) {
				// We don't have such a setting
				unset( $settings[ $setting ] );
			} elseif ( ! in_array( $value, $allowed[ $setting ] ) ) {
				// Set it to default
				$settings[ $setting ] = $defaults[ $setting ];
			}
		}
		return $settings;
	}

	/**
	 * Delete all View Admin As metadata for this user
	 *
	 * @since   1.5
	 * @since   1.6    Moved to this class from main class
	 * @since   1.6.2  Option to remove the VAA metadata for all users
	 * @access  public
	 *
	 * @global  wpdb        $wpdb
	 * @param   int|string  $user_id     ID of the user being deleted/removed (pass `all` for all users)`
	 * @param   object      $user        User object provided by the wp_login hook
	 * @param   bool        $reset_only  Only reset (not delete) the user meta
	 * @return  bool
	 */
	public function delete_user_meta( $user_id = null, $user = null, $reset_only = true ) {
		global $wpdb;

		/**
		 * Set the first parameter to `all` to remove the meta value for all users
		 * @since  1.6.2
		 * @see    https://developer.wordpress.org/reference/classes/wpdb/update/
		 * @see    https://developer.wordpress.org/reference/classes/wpdb/delete/
		 */
		if ( 'all' == $user_id ) {
			if ( $reset_only ) {
				return (bool) $wpdb->update(
					$wpdb->usermeta, // table
					array( 'meta_value', false ), // data
					array( 'meta_key' => $this->get_userMetaKey() ) // where
				);
			} else {
				return (bool) $wpdb->delete(
					$wpdb->usermeta, // table
					array( 'meta_key' => $this->get_userMetaKey() ) // where
				);
			}
		}

		$id = false;
		if ( is_numeric( $user_id ) ) {
			// Delete hooks
			$id = $user_id;
		} elseif ( isset( $user->ID ) ) {
			// Login/Logout hooks
			$id = $user->ID;
		}
		if ( false != $id ) {
			$success = true;
			if ( $reset_only == true ) {
				// Reset db metadata (returns: true on success, false on failure)
				if ( get_user_meta( $id, $this->get_userMetaKey() ) ) {
					$success = update_user_meta( $id, $this->get_userMetaKey(), false );
				}
			} else {
				// Remove db metadata (returns: true on success, false on failure)
				$success = delete_user_meta( $id, $this->get_userMetaKey() );
			}
			// Update current metadata if it is the current user
			if ( $success && $this->get_curUser()->ID == $id ){
				$this->set_userMeta( false );
			}

			return $success;
		}
		// No user or metadata found, no deletion needed
		return true;
	}

	/*
	 * Getters
	 */
	public function get_curUser()                           { return $this->curUser; }
	public function get_curUserSession()                    { return (string) $this->curUserSession; }
	public function get_viewAs( $key = false )              { return VAA_API::get_array_data( $this->viewAs, $key ); }
	public function get_caps( $key = false )                { return VAA_API::get_array_data( $this->caps, $key ); }
	public function get_roles( $key = false )               { return VAA_API::get_array_data( $this->roles, $key ); }
	public function get_users( $key = false )               { return VAA_API::get_array_data( $this->users, $key ); }
	public function get_selectedUser()                      { return $this->selectedUser; }
	public function get_selectedCaps()                      { return (array) $this->selectedCaps; }
	public function get_userids()                           { return (array) $this->userids; }
	public function get_optionKey()                         { return (string) $this->optionKey; }
	public function get_optionData( $key = false )          { return VAA_API::get_array_data( $this->optionData, $key ); }
	public function get_userMetaKey()                       { return (string) $this->userMetaKey; }
	public function get_userMeta( $key = false )            { return VAA_API::get_array_data( $this->userMeta, $key ); }
	public function get_defaultSettings( $key = false )     { return VAA_API::get_array_data( $this->defaultSettings, $key ); }
	public function get_defaultUserSettings( $key = false ) { return VAA_API::get_array_data( $this->defaultUserSettings, $key ); }
	public function get_allowedSettings( $key = false )     { return (array) VAA_API::get_array_data( $this->allowedSettings, $key ); }
	public function get_allowedUserSettings( $key = false ) { return (array) VAA_API::get_array_data( $this->allowedUserSettings, $key ); }

	public function get_nonce( $parsed = false ) {
		return ( $parsed ) ? $this->nonce_parsed : $this->nonce;
	}

	public function get_settings( $key = false ) {
		return VAA_API::get_array_data( $this->validate_settings( $this->get_optionData( 'settings' ), 'global' ), $key );
	}
	public function get_userSettings( $key = false ) {
		return VAA_API::get_array_data( $this->validate_settings( $this->get_userMeta( 'settings' ), 'user' ), $key );
	}

	public function get_version()                           { return strtolower( (string) VIEW_ADMIN_AS_VERSION ); }
	public function get_dbVersion()                         { return strtolower( (string) VIEW_ADMIN_AS_DB_VERSION ); }

	/*
	 * Setters
	 */
	public function set_viewAs( $var, $key = false, $append = false ) { $this->viewAs = VAA_API::set_array_data( $this->viewAs, $var, $key, $append ); }
	public function set_caps( $var, $key = false, $append = false )   { $this->caps   = VAA_API::set_array_data( $this->caps, $var, $key, $append ); }
	public function set_roles( $var, $key = false, $append = false )  { $this->roles  = VAA_API::set_array_data( $this->roles, $var, $key, $append ); }
	public function set_users( $var, $key = false, $append = false )  { $this->users  = VAA_API::set_array_data( $this->users, $var, $key, $append ); }
	public function set_userids( $var )                               { $this->userids = array_map( 'strval', (array) $var ); }
	public function set_curUser( $var )                               { $this->curUser = $var; }
	public function set_curUserSession( $var )                        { $this->curUserSession = (string) $var; }
	public function set_selectedUser( $var )                          { $this->selectedUser = $var; }
	public function set_selectedCaps( $var )                          { $this->selectedCaps = array_filter( (array) $var ); }
	public function set_defaultSettings( $var )                       { $this->defaultSettings = array_map( 'strval', (array) $var ); }
	public function set_defaultUserSettings( $var )                   { $this->defaultUserSettings = array_map( 'strval', (array) $var ); }

	public function set_nonce( $var ) {
		$this->nonce = (string) $var;
		$this->nonce_parsed = wp_create_nonce( (string) $var );
	}

	public function set_allowedSettings( $var, $key = false, $append = false ) {
		$this->allowedSettings = VAA_API::set_array_data( $this->allowedSettings, $var, $key, $append );
	}
	public function set_allowedUserSettings( $var, $key = false, $append = false ) {
		$this->allowedUserSettings = VAA_API::set_array_data( $this->allowedUserSettings, $var, $key, $append );
	}
	public function set_settings( $var, $key = false, $append = false ) {
		$this->set_optionData(
			$this->validate_settings(
				VAA_API::set_array_data( $this->get_settings(), $var, $key, $append ),
				'global'
			),
			'settings',
			true
		);
	}
	public function set_userSettings( $var, $key = false, $append = false ) {
		$this->set_userMeta(
			$this->validate_settings(
				VAA_API::set_array_data( $this->get_userSettings(), $var, $key, $append ),
				'user'
			),
			'settings',
			true
		);
	}
	public function set_optionData( $var, $key = false, $append = false ) {
		$this->optionData = VAA_API::set_array_data( $this->optionData, $var, $key, $append );
	}
	public function set_userMeta( $var, $key = false, $append = false ) {
		$this->userMeta = VAA_API::set_array_data( $this->userMeta, $var, $key, $append );
	}

	/*
	 * Update
	 */
	public function update_optionData( $var, $key = false, $append = false ) {
		$this->set_optionData( $var, $key, $append );
		return update_option( $this->get_optionKey(), $this->get_optionData() );
	}
	public function update_userMeta( $var, $key = false, $append = false ) {
		$this->set_userMeta( $var, $key, $append );
		return update_user_meta( $this->get_curUser()->ID, $this->get_userMetaKey(), $this->get_userMeta() );
	}

	/**
	 * Main Instance.
	 *
	 * Ensures only one instance of this class is loaded or can be loaded.
	 *
	 * @since   1.6
	 * @access  public
	 * @static
	 * @param   VAA_View_Admin_As  $caller  The referrer class
	 * @return  VAA_View_Admin_As_Store
	 */
	public static function get_instance( $caller = null ) {
		if ( is_object( $caller ) && 'VAA_View_Admin_As' == get_class( $caller ) ) {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self( $caller );
			}
			return self::$_instance;
		}
		return null;
	}

	/**
	 * Magic method to output a string if trying to use the object as a string.
	 *
	 * @since  1.6
	 * @access public
	 * @return string
	 */
	public function __toString() {
		return get_class( $this );
	}

	/**
	 * Magic method to keep the object from being cloned.
	 *
	 * @since  1.6
	 * @access public
	 * @return void
	 */
	public function __clone() {
		_doing_it_wrong(
			__FUNCTION__,
			get_class( $this ) . ': ' . esc_html__( 'This class does not want to be cloned', 'view-admin-as' ),
			null
		);
	}

	/**
	 * Magic method to keep the object from being unserialized.
	 *
	 * @since  1.6
	 * @access public
	 * @return void
	 */
	public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			get_class( $this ) . ': ' . esc_html__( 'This class does not want to wake up', 'view-admin-as' ),
			null
		);
	}

	/**
	 * Magic method to prevent a fatal error when calling a method that doesn't exist.
	 *
	 * @since  1.6
	 * @access public
	 * @param  string
	 * @param  array
	 * @return null
	 */
	public function __call( $method = '', $args = array() ) {
		_doing_it_wrong(
			get_class( $this ) . "::{$method}",
			esc_html__( 'Method does not exist.', 'view-admin-as' ),
			null
		);
		unset( $method, $args );
		return null;
	}

} // end class