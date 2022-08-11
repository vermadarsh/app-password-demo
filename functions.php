<?php
/**
 * Child theme functions file.
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

/**
 * Debugger function which shall be removed in production.
 */
if ( ! function_exists( 'debug' ) ) {
	/**
	 * Debug function definition.
	 *
	 * @param string $params Holds the variable name.
	 */
	function debug( $params ) {
		echo '<pre>';
		print_r( $params );
		echo '</pre>';
	}
}

/**
 * Check if the function exists.
 */
if ( ! function_exists( 'twsix_wp_enqueue_scripts_callback' ) ) {
	/**
	 * Enqueue child style.
	 *
	 * @since 1.0.0
	 */
	function twsix_wp_enqueue_scripts_callback() {
		wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
		wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array( 'parent-style' ) );
	}

	add_action( 'wp_enqueue_scripts', 'twsix_wp_enqueue_scripts_callback' );
}

/**
 * Check if the function exists.
 */
if ( ! function_exists( 'twsix_admin_menu_callback' ) ) {
	/**
	 * Add management page.
	 *
	 * @since 1.0.0
	 */
	function twsix_admin_menu_callback() {
		add_management_page(
			__( 'App Password Demo', 'twentysixteen-child' ),
			__( 'App Password Demo', 'twentysixteen-child' ),
			'manage_options',
			'app-password-demo',
			'twsix_app_password_demo_callback'
		);
	}

	add_action( 'admin_menu', 'twsix_admin_menu_callback' );
}

/**
 * Check if the function exists.
 */
if ( ! function_exists( 'twsix_app_password_demo_callback' ) ) {
	/**
	 * Custom page template.
	 *
	 * @since 1.0.0
	 */
	function twsix_app_password_demo_callback() {
		$url     = admin_url( 'tools.php?page=app-password-demo' );
		$user_id = get_current_user_id();
		$me      = null;

		// Check if the connect request is made.
		$app_connect_submit        = filter_input( INPUT_POST, 'app-connect', FILTER_SANITIZE_STRING );
		$app_disconnect_submit     = filter_input( INPUT_POST, 'app-disconnect', FILTER_SANITIZE_STRING );
		$app_install_plugin_submit = filter_input( INPUT_POST, 'app-install-plugin', FILTER_SANITIZE_STRING );
		$app_upload_media_submit   = filter_input( INPUT_POST, 'upload-media', FILTER_SANITIZE_STRING );
		$app_callback              = filter_input( INPUT_GET, 'app-callback', FILTER_SANITIZE_STRING );

		if ( isset( $app_connect_submit ) ) {
			$website_url = filter_input( INPUT_POST, 'app_website', FILTER_SANITIZE_STRING );
			$redirect    = twsix_build_authorization_redirect_callback( $website_url ?? '' );

			if ( is_wp_error( $redirect ) ) {
				wp_die( $redirect );
			}
		} elseif ( isset( $app_disconnect_submit ) ) {
			delete_user_meta( get_current_user_id(), '_app_passwords_client_demo_creds' );
		} elseif ( ! is_null( $app_callback ) ) {
			if ( ! wp_verify_nonce( $_GET['state'] ?? '', 'app-callback' ) ) {
				wp_nonce_ays( 'app-callback' );
				die;
			}

			if ( 'false' === ( $_GET['success'] ?? '' ) ) {
				wp_die( __( 'Authorization rejected.' ) );
			}

			$app_state      = filter_input( INPUT_GET, 'state', FILTER_SANITIZE_STRING );
			$app_site_url   = filter_input( INPUT_GET, 'site_url', FILTER_SANITIZE_STRING );
			$app_user_login = filter_input( INPUT_GET, 'user_login', FILTER_SANITIZE_STRING );
			$app_password   = filter_input( INPUT_GET, 'password', FILTER_SANITIZE_STRING );

			if ( ! $app_site_url || ! $app_user_login || ! $app_password ) {
				wp_die( __( 'Malformed authorization callback.' ) );
			}

			$root = twsix_discover_callback( $site_url );

			if ( is_wp_error( $root ) ) {
				wp_die( $root );
			}
	
			try {
				twsix_store_credentials_callback( get_current_user_id(), $app_state, twsix_discover_callback( $app_site_url ), $app_user_login, $app_password );
				$redirect = admin_url( 'tools.php?page=app-password-demo' );
			} catch ( \Exception $e ) {
				wp_die( $e->getMessage() );
			}
		} elseif ( isset( $app_install_plugin_submit ) ) {
			$plugin_url = home_url( '/core-functions.zip' );
			$app_credentials = twsix_get_credentials_callback( $user_id );
			twsix_install_plugin_callback( $plugin_url, $app_credentials, 'wp/v2/plugins/' );
		} elseif ( isset( $app_upload_media_submit ) ) {
			$image_url       = filter_input( INPUT_POST, 'image_url', FILTER_SANITIZE_STRING );
			$app_credentials = twsix_get_credentials_callback( $user_id );
			twsix_upload_media_callback( $image_url, $app_credentials, 'wp/v2/media/' );
		}

		// Check if the credentials exist.
		if ( twsix_has_credentials_callback( $user_id ) ) {
			$me = twsix_api_request_callback( $user_id, 'wp/v2/users/me' );
		}

		// If the redirection is available.
		if ( ! empty( $redirect ) ) {
			?>
			<script type="text/javascript">window.location.href = '<?php echo $redirect; ?>';</script>
			<?php
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'App Passwords Demo', 'twentysixteen-child' ); ?></h1>

			<?php if ( is_wp_error( $me ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $me->get_error_message() ); ?></p></div>
			<?php elseif ( $me ): ?>
				<?php echo wp_json_encode( $me, JSON_PRETTY_PRINT ); ?>
				<form method="post" action="<?php echo esc_url( $url ); ?>">
					<?php wp_nonce_field( 'app-disconnect' ); ?>
					<?php submit_button( __( 'Disconnect', 'twentysixteen-child' ), 'primary', 'app-disconnect' ); ?>
					<?php submit_button( __( 'Install Plugin', 'twentysixteen-child' ), 'primary', 'app-install-plugin' ); ?>
				</form>
				<!-- MEDIA UPLOAD -->
				<form method="post" action="<?php echo esc_url( $url ); ?>">
					<div class="form-wrap">
						<div class="form-field">
							<label for="image-url"><?php esc_html_e( 'Image URL', 'twentysixteen-child' ); ?></label>
							<input type="url" value="https://summerhub.org/wp-content/uploads/2022/08/DeAnzaHS_238.jpeg" name="image_url" id="image-url" required />
						</div>
						<?php submit_button( __( 'Upload media', 'twentysixteen-child' ), 'primary', 'upload-media' ); ?>
					</div>
				</form>
			<?php else: ?>
				<form method="post" action="<?php echo esc_url( $url ); ?>">
					<div class="form-wrap">
						<div class="form-field">
							<label for="app-website"><?php esc_html_e( 'Website', 'twentysixteen-child' ); ?></label>
							<input type="url" value="" name="app_website" id="app-website"/>
						</div>

						<?php wp_nonce_field( 'app-connect' ); ?>
						<?php submit_button( __( 'Connect', 'twentysixteen-child' ), 'primary', 'app-connect' ); ?>
					</div>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}

/**
 * Check if the function exists.
 */
if ( ! function_exists( 'twsix_has_credentials_callback' ) ) {
	/**
	 * Checks if the user has credentials stored.
	 *
	 * @param int $user_id
	 *
	 * @return bool
	 */
	function twsix_has_credentials_callback( int $user_id ) {

		return metadata_exists( 'user', $user_id, '_app_passwords_client_demo_creds' );
	}
}

/**
 * Check if the function exists.
 */
if ( ! function_exists( 'twsix_build_authorization_redirect_callback' ) ) {
	/**
	 * Builds the authorization redirect link.
	 *
	 * @param string $url
	 *
	 * @return string|\WP_Error
	 */
	function twsix_build_authorization_redirect_callback( $url ) {
		require_once 'uuid.php'; // Require the file for generating UUID.
		$auth_url = twsix_get_authorize_url_callback( $url );

		if ( is_wp_error( $auth_url ) ) {
			return $auth_url;
		}

		$success_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'         => 'app-password-demo',
					'app-callback' => '1',
				),
				admin_url( 'tools.php' )
			),
			'app-callback',
			'state'
		);

		// Parse the URL.
		$parsed_url = parse_url( $url );
		$url_host   = ( ! empty( $parsed_url['host'] ) ) ? $parsed_url['host'] : '';

		// If the host is not available.
		if ( empty( $url_host ) ) {
			return $auth_url;
		}

		return add_query_arg(
			array(
				'app_name'    => urlencode( $url_host ),
				'app_id'      => urlencode( UUID::v5( '1546058f-5a25-4334-85ae-e68f2a44bbaf', $url_host ) ),
				'success_url' => urlencode( $success_url ),
			),
			$auth_url
		);
	}
}

/**
 * Check if the function exists.
 */
if ( ! function_exists( 'twsix_get_authorize_url_callback' ) ) {
	/**
	 * Looks up the Authorize Application URL for the given website.
	 *
	 * @param string $url The website to lookup.
	 *
	 * @return string|\WP_Error The authorization URL or a WP_Error if none found.
	 */
	function twsix_get_authorize_url_callback( string $url ) {
		$root = twsix_discover_callback( $url );

		if ( is_wp_error( $root ) ) {
			return $root;
		}

		$response = wp_safe_remote_get( $root );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );

		if ( $status !== 200 ) {
			return new \WP_Error( 'non_200_status', sprintf( __( 'The website returned a %d status code.' ), $status ) );
		}

		$index = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error( 'invalid_json', json_last_error_msg() );
		}

		$auth_url = $index['authentication']['application-passwords']['endpoints']['authorization'] ?? '';

		if ( ! $auth_url ) {
			return new \WP_Error( 'no_application_passwords_support', __( 'Application passwords is not available for this website.' ) );
		}

		return $auth_url;
	}
}

/**
 * Check if the function exists.
 */
if ( ! function_exists( 'twsix_discover_callback' ) ) {
	/**
	 * Discovers the REST API root from the given site URL.
	 *
	 * @param string $url
	 *
	 * @return string|\WP_Error
	 */
	function twsix_discover_callback( string $url ) {
		$response = wp_safe_remote_head( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$link = wp_remote_retrieve_header( $response, 'Link' );

		if ( ! $link ) {
			return new \WP_Error( 'no_link_header', __( 'REST API cannot be discovered. No link header found.' ) );
		}

		$parsed = twsix_parse_header_with_attributes_callback( $link );

		foreach ( $parsed as $url => $attr ) {
			if ( ( $attr['rel'] ?? '' ) === 'https://api.w.org/' ) {
				return $url;
			}
		}

		return new \WP_Error( 'no_link', __( 'REST API cannot be discovered. No REST API link found.' ) );
	}
}

/**
 * Check if the function exists.
 */
if ( ! function_exists( 'twsix_parse_header_with_attributes_callback' ) ) {
	/**
	 * Parse a header that has attributes.
	 *
	 * @param string $header
	 *
	 * @return array
	 */
	function twsix_parse_header_with_attributes_callback( string $header ): array {
		$parsed = array();
		$list   = explode( ',', $header );

		foreach ( $list as $value ) {
			$attrs = array();
			$parts = explode( ';', trim( $value ) );
			$main  = trim( $parts[0], ' <>' );

			foreach ( $parts as $part ) {
				if ( false === strpos( $part, '=' ) ) {
					continue;
				}

				[ $key, $value ] = explode( '=', $part, 2 );
				$key   = trim( $key );
				$value = trim( $value, '" ' );

				$attrs[ $key ] = $value;
			}

			$parsed[ $main ] = $attrs;
		}

		return $parsed;
	}
}

/**
 * Check if the function exists.
 */
if ( ! function_exists( 'twsix_store_credentials_callback' ) ) {
	/**
	 * Parse a header that has attributes.
	 *
	 * @param string $header
	 *
	 * @return array
	 */
	function twsix_store_credentials_callback( $user_id, $state, $site_url, $user_login, $password ) {
		update_user_meta( $user_id, '_app_passwords_client_demo_creds', array(
			'state'      => $state,
			'site_url'   => $site_url,
			'user_login' => $user_login,
			'password'   => $password,
		) );
	}
}

/**
 * Check if the function exists.
 */
if ( ! function_exists( 'twsix_api_request_callback' ) ) {
	/**
	 * Makes an authenticated API request.
	 *
	 * @param int    $user_id The user ID to make the request as.
	 * @param string $route   The route to access.
	 *
	 * @return array|\WP_Error
	 */
	function twsix_api_request_callback( $user_id, $route ) {
		$creds      = twsix_get_credentials_callback( $user_id );
		$site_url   = ( ! empty( $creds['site_url'] ) ) ? $creds['site_url'] : '';
		$user_login = ( ! empty( $creds['user_login'] ) ) ? $creds['user_login'] : '';
		$password   = ( ! empty( $creds['password'] ) ) ? $creds['password'] : '';

		if ( empty( $site_url ) || empty( $user_login ) || empty( $password ) ) {
			return new \WP_Error( 'no_credentials', __( 'No credentials stored for this user.' ) );
		}

		// Set the API.
		$response = wp_safe_remote_get(
			$site_url . $route,
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( "{$user_login}:{$password}" ),
					'Content-Type'  => 'application/json',
				)
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );

		if ( $status !== 200 ) {
			return new \WP_Error( 'non_200_status', sprintf( __( 'The website returned a %d status code.' ), $status ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error( 'invalid_json', json_last_error_msg() );
		}

		return $body;
	}
}

/**
 * Check if the function exists.
 */
if ( ! function_exists( 'twsix_get_credentials_callback' ) ) {
	/**
	 * Gets the REST API credentials for the given user.
	 *
	 * @param int $user_id The user ID to retrieve the credentials for.
	 *
	 * @return array|null An array with the API Root, username, and password, or null if no valid credentials found.
	 */
	function twsix_get_credentials_callback( $user_id ) {

		return get_user_meta( $user_id, '_app_passwords_client_demo_creds', true );
	}
}

/**
 * Check if the function exists.
 */
if ( ! function_exists( 'twsix_upload_media_callback' ) ) {
	/**
	 * Upload media to the remote app.
	 *
	 * @param string $image_url Image URL.
	 * @param array  $app_credentials Remote application credentials.
	 * @param string $route WordPress REST API route.
	 */
	function twsix_upload_media_callback( $image_url, $app_credentials, $route ) {
		$file_basename  = basename( $image_url );
		$file           = file_get_contents( $image_url );
		$remote_app_url = ( ! empty( $app_credentials['site_url'] ) ) ? $app_credentials['site_url'] : '';
		$user_login     = ( ! empty( $app_credentials['user_login'] ) ) ? $app_credentials['user_login'] : '';
		$password       = ( ! empty( $app_credentials['password'] ) ) ? $app_credentials['password'] : '';

		// Shoot the API now,
		$curl_ch = curl_init();
		curl_setopt( $curl_ch, CURLOPT_URL, $remote_app_url . $route );
		curl_setopt( $curl_ch, CURLOPT_POST, 1 );
		curl_setopt( $curl_ch, CURLOPT_POSTFIELDS, $file );
		curl_setopt( $curl_ch, CURLOPT_HTTPHEADER, array(
			'Content-Disposition: form-data; filename="' . $file_basename . '"',
			'Authorization: Basic ' . base64_encode( $user_login . ':' . $password ),
		) );
		$response = curl_exec( $curl_ch );
		curl_close( $curl_ch );
		debug( json_decode( $response ) );
		die;
	}
}

/**
 * Check if the function exists.
 */
if ( ! function_exists( 'twsix_install_plugin_callback' ) ) {
	/**
	 * Install plugin on the remote app.
	 *
	 * @param string $image_url Image URL.
	 * @param array  $app_credentials Remote application credentials.
	 * @param string $route WordPress REST API route.
	 */
	function twsix_install_plugin_callback( $plugin_url, $app_credentials, $route ) {
		$remote_app_url = ( ! empty( $app_credentials['site_url'] ) ) ? $app_credentials['site_url'] : '';
		$user_login     = ( ! empty( $app_credentials['user_login'] ) ) ? $app_credentials['user_login'] : '';
		$password       = ( ! empty( $app_credentials['password'] ) ) ? $app_credentials['password'] : '';
		$post_data      = array( 'slug' => 'akismet' );

		// Get the installed plugins.
		$installed_plugins = twsix_get_remote_app_plugins_list_callback( $app_credentials, $route );

		// Shoot the API now to install the plugin.
		$curl_ch = curl_init();
		curl_setopt( $curl_ch, CURLOPT_URL, $remote_app_url . $route );
		curl_setopt( $curl_ch, CURLOPT_POST, 1 );
		curl_setopt( $curl_ch, CURLOPT_POSTFIELDS, wp_json_encode( $post_data ) );
		curl_setopt( $curl_ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Authorization: Basic ' . base64_encode( $user_login . ':' . $password ),
		) );
		$response = curl_exec( $curl_ch );
		curl_close( $curl_ch );
		debug( json_decode( $response ) );
		die;
	}
}

/**
 * Check if the function exists.
 */
if ( ! function_exists( 'twsix_get_remote_app_plugins_list_callback' ) ) {
	/**
	 * Get list of the installed plugin from the remote app.
	 *
	 * @param array  $app_credentials Remote application credentials.
	 * @param string $route WordPress REST API route.
	 */
	function twsix_get_remote_app_plugins_list_callback( $app_credentials, $route ) {
		$remote_app_url = ( ! empty( $app_credentials['site_url'] ) ) ? $app_credentials['site_url'] : '';
		$user_login     = ( ! empty( $app_credentials['user_login'] ) ) ? $app_credentials['user_login'] : '';
		$password       = ( ! empty( $app_credentials['password'] ) ) ? $app_credentials['password'] : '';

		// Shoot the API now to get the list of plugins.
		$curl_ch = curl_init();
		curl_setopt( $curl_ch, CURLOPT_URL, $remote_app_url . $route );
		curl_setopt( $curl_ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl_ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Authorization: Basic ' . base64_encode( $user_login . ':' . $password ),
		) );
		$response = curl_exec( $curl_ch );
		curl_close( $curl_ch );

		return json_decode( $response );
	}
}
