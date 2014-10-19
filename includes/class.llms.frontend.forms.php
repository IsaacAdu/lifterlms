<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
* Front End Forms Class
*
* Class used managing front end facing forms.
*
* @version 1.0
* @author codeBOX
* @project lifterLMS
*/
class LLMS_Frontend_Forms {

	/**
	* Constructor
	*
	* initializes the forms methods
	*/
	public function __construct() {

		add_action( 'template_redirect', array( $this, 'save_account_details' ) );	

		add_action( 'init', array( $this, 'create_order' ) );
		add_action( 'init', array( $this, 'confirm_order' ) );	
		add_action( 'init', array( $this, 'login' ) );
		add_action( 'init', array( $this, 'user_registration' ) );
		add_action( 'init', array( $this, 'reset_password' ) );
		add_action( 'init', array( $this, 'mark_complete' ) );

		add_action( 'lifterlms_order_process_begin', array( $this, 'order_processing' ), 10, 1 );
		add_action( 'lifterlms_order_process_success', array( $this, 'order_success' ), 10, 2 );
		add_action( 'lifterlms_order_process_complete', array( $this, 'order_complete' ), 10, 1 );


		add_action( 'lifterlms_content_restricted_by_prerequisite', array( $this, 'llms_restricted_by_prerequisite' ), 10, 1 );
		add_action( 'lifterlms_content_restricted_by_start_date', array( $this, 'llms_restricted_by_start_date' ), 10, 1 );
		

	}

	// Mark lesson as complete
    public function mark_complete() {
    	global $wpdb;

    	if ( 'POST' !== strtoupper( $_SERVER[ 'REQUEST_METHOD' ] ) ) {
			return;
		}

    	if ( ! isset( $_POST['mark_complete'] ) || empty( $_POST['_wpnonce'] ) ) {
    		return;
    	}

    	if ( isset( $_POST['mark-complete'] ) ) {
    		$lesson = new LLMS_Lesson( $_POST['mark-complete'] );

    		$current_lesson = new stdClass();

    		$current_lesson->id = $_POST['mark-complete'];

    		$current_lesson->parent_id = $lesson->get_parent_course();
    		$current_lesson->title = get_the_title($current_lesson->id);


    		$current_lesson->user_id = get_post_meta( $current_lesson->parent_id , '_llms_student', true );

    		//TODO move this to it's own class and create a userpostmeta class.
    		$user = new LLMS_Person;
			$user_postmetas = $user->get_user_postmeta_data( get_current_user_id(), $current_lesson->id );

    		if ( empty($current_lesson->user_id) ) {
    			throw new Exception( '<strong>' . __( 'Error', 'lifterlms' ) . ':</strong> ' . __( 'User cannot be found.', 'lifterlms' ) );
    		}
    		elseif ( $user_postmetas['_is_complete']->meta_value === 'yes' ) {
    			return;

    		}
    		else {

    			$key = '_is_complete';
    			$value = 'yes';

    			$update_user_postmeta = $wpdb->insert( $wpdb->prefix .'lifterlms_user_postmeta', 
					array( 
						'user_id' 			=> $current_lesson->user_id,
						'post_id' 			=> $current_lesson->id,
						'meta_key'			=> $key,
						'meta_value'		=> $value,
						'updated_date'		=> current_time('mysql'),
					)
				);
				do_action( 'lifterlms_lesson_completed', $current_lesson->user_id, $current_lesson->id);
				LLMS_log('lifterlms_lesson_completed do_action triggered');
				llms_add_notice( sprintf( __( 'Congratulations! You have completed %s', 'lifterlms' ), $current_lesson->title ) );

    		}
    	}

    }

	public function confirm_order() {
		global $wpdb;

		if ( ! isset($_REQUEST['token']) ) {
			return;
		}

		if ( 'POST' !== strtoupper( $_SERVER[ 'REQUEST_METHOD' ] ) ) {
			return;
		}

		if ( empty( $_POST[ 'action' ] ) || ( 'process_order' !== $_POST[ 'action' ] ) || empty( $_POST['_wpnonce'] ) ) {
			return;
		}

		// noonnce the post
		wp_verify_nonce( $_POST['_wpnonce'], 'lifterlms_create_order_details' );

		$current_order = LLMS()->session->get( 'llms_order', array() );

		$payment_method	= $current_order->payment_method;

		$available_gateways = LLMS()->payment_gateways()->get_available_payment_gateways();
	
		$result = $available_gateways[$payment_method]->confirm_payment($_REQUEST);

		$errors = new WP_Error();
		$order = new stdClass();

		$order->token	= $result['TOKEN'];
		$order->payer_id	= $result['PAYERID'];

		$order->payment_method	= $current_order->payment_method;
		$order->product_title	= $result['L_PAYMENTREQUEST_0_NAME0'];
		$order->product_price	= $result['PAYMENTREQUEST_0_ITEMAMT'];
		$order->product_sku		= $result['L_PAYMENTREQUEST_0_NUMBER0'];

		$order->user_id     	= (int) get_current_user_id();
		$current_user 			= get_user_by( 'id', $order->user_id );

		$order->order_completed = 'no';

		$order->total 			= $result['AMT'];
		$order->currency 		= get_lifterlms_currency();
		$order->return_url		= $this->llms_confirm_payment_url();


		if ( $confirm_order = $available_gateways[$payment_method]->confirm_payment($_REQUEST) ) {
			$process_result = $available_gateways[$payment_method]->complete_payment($order);
		}

	}

	public function create_order() {
		global $wpdb;
		//llms_log('create_order called');
		// check if session already exists. if it does assign it. 
		$current_order = LLMS()->session->get( 'llms_order', array() );

		if ( 'POST' !== strtoupper( $_SERVER[ 'REQUEST_METHOD' ] ) ) {
			return;
		}

		if ( empty( $_POST[ 'action' ] ) || ( 'create_order_details' !== $_POST[ 'action' ] ) || empty( $_POST['_wpnonce'] ) ) {
			//llms_log($_POST[ 'action' ]);
			return;
		}
		//llms_log($_POST[ 'action' ]);
		// noonnce the post
		wp_verify_nonce( $_POST['_wpnonce'], 'lifterlms_create_order_details' );

		$errors = new WP_Error();
		$order = new stdClass();

		$order->payment_method	= $_POST['payment_method'];
		$order->product_title	= $_POST['product_title'];
		$order->product_price	= $_POST['product_price'];
		$order->product_sku		= $_POST['product_sku'];

		$order->user_id     	= (int) get_current_user_id();
		$current_user 			= get_user_by( 'id', $order->user_id );
		$product_id 			= ! empty( $_POST[ 'product_id' ] ) ? llms_clean( $_POST[ 'product_id' ] ) : '';
		
		$order->order_completed = 'no';
		$order->product_id 		= $product_id;

		$order->total 			= $_POST['product_price'];
		$order->currency 		= get_lifterlms_currency();
		$order->return_url		= $this->llms_confirm_payment_url();
		$order->cancel_url		= $this->llms_cancel_payment_url();

		if ( $order->user_id <= 0 || $order->product_id <= 0 ) {
			return;
		}

		$url = isset($_POST['redirect']) ? llms_clean( $_POST['redirect'] ) : '';

		$redirect = LLMS_Frontend_Forms::llms_get_redirect( $url );
	
		// if no errors were returned save the data
		if ( llms_notice_count( 'error' ) == 0 ) {
			if ($order->total == 0) {
				$lifterlms_checkout = LLMS()->checkout();
				$lifterlms_checkout->process_order();
				$lifterlms_checkout->update_order();
				do_action( 'lifterlms_order_process_success', $order->user_id, $order->product_title);
			}
			else {

				LLMS()->session->set( 'llms_order', $order );

				$lifterlms_checkout = LLMS()->checkout();
				$lifterlms_checkout->process_order();

				$available_gateways = LLMS()->payment_gateways()->get_available_payment_gateways();

				$result = $available_gateways[$order->payment_method]->process_payment($order);

			}

		}

	}

	function llms_confirm_payment_url() {

		$confirm_payment_url = llms_get_endpoint_url( 'confirm-payment', '', get_permalink( llms_get_page_id( 'checkout' ) ) );

		return apply_filters( 'lifterlms_checkout_confirm_payment_url', $confirm_payment_url );
	}

	function llms_cancel_payment_url() {

		$cancel_payment_url = esc_url( get_permalink( llms_get_page_id( 'checkout' ) ) );

		return apply_filters( 'lifterlms_checkout_confirm_payment_url', $cancel_payment_url );
	}

	public function order_processing ($url) {

		$redirect = esc_url( $url );

		llms_add_notice( __( 'Please confirm your payment.', 'lifterlms' ) );

		wp_redirect( apply_filters( 'lifterlms_order_process_pending_redirect', $url ) );
		exit;

	}

	public function order_success ($user_id, $course_title) {

		$redirect = esc_url( get_permalink( llms_get_page_id( 'myaccount' ) ) );

		llms_add_notice( sprintf( __( 'Congratulations! You have enrolled in <strong>%s</strong>', 'lifterlms' ), $course_title ) );

		wp_redirect( apply_filters( 'lifterlms_order_process_success_redirect', $redirect ) );

		exit;

	}

	public function order_complete ($user_id) {

		$redirect = esc_url( get_permalink( llms_get_page_id( 'myaccount' ) ) );

		llms_add_notice( __( 'You already own this course. You cannot purchase it again.', 'lifterlms' ) );

		wp_redirect( apply_filters( 'lifterlms_order_process_complete_redirect', $redirect ) );

	}

	function llms_restricted_by_prerequisite($prerequisite) {
		$link = get_permalink( $prerequisite->ID);
		//LLMS_log($prerequisite->ID);

		llms_add_notice( sprintf( __( 'You must complete <strong><a href="%s" alt="%s">%s</strong></a> before accessing this content', 'lifterlms' ), 
			$link, $prerequisite->post_title, $prerequisite->post_title ) );
	}

	function llms_restricted_by_start_date($date) {
		llms_add_notice( sprintf( __( 'This content is not available until %s', 'lifterlms' ), 
			$date ) );
	}

	public static function llms_get_redirect( $url ) {
		if ( ! empty( $url ) ) {

		$redirect = esc_url( $url );

		} 
		
		elseif ( wp_get_referer() ) {

			$redirect = esc_url( wp_get_referer() );

		} 

		else {

			$redirect = esc_url( get_permalink( llms_get_page_id( 'myaccount' ) ) );

		}

		return $redirect;

	}

	/**
	* Account details form
	*
	* @return void
	*/
	public function save_account_details() {

		
		if ( ! $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			return;
		}

		if ( 'POST' !== strtoupper( $_SERVER[ 'REQUEST_METHOD' ] ) ) {

			return;
		}

		if ( empty( $_POST[ 'action' ] ) || ( 'save_account_details' !== $_POST[ 'action' ] ) || empty( $_POST['_wpnonce'] ) ) {
			
			return;

		}

		wp_verify_nonce( $_POST['_wpnonce'], 'save_account_details' );

		$update       = true;
		$errors       = new WP_Error();
		$user         = new stdClass();

		$user->ID     = (int) get_current_user_id();
		$current_user = get_user_by( 'id', $user->ID );

		if ( $user->ID <= 0 ) {

			return;

		}

		$account_first_name = ! empty( $_POST[ 'account_first_name' ] ) ? llms_clean( $_POST[ 'account_first_name' ] ) : '';
		$account_last_name  = ! empty( $_POST[ 'account_last_name' ] ) ? llms_clean( $_POST[ 'account_last_name' ] ) : '';
		$account_email      = ! empty( $_POST[ 'account_email' ] ) ? sanitize_email( $_POST[ 'account_email' ] ) : '';
		$pass1              = ! empty( $_POST[ 'password_1' ] ) ? $_POST[ 'password_1' ] : '';
		$pass2              = ! empty( $_POST[ 'password_2' ] ) ? $_POST[ 'password_2' ] : '';

		$user->first_name   = $account_first_name;
		$user->last_name    = $account_last_name;
		$user->user_email   = $account_email;
		$user->display_name = $user->first_name;

		if ( $pass1 ) {

			$user->user_pass = $pass1;

		}

		if ( empty( $account_first_name ) || empty( $account_last_name ) ) {

			llms_add_notice( __( 'Please enter your name.', 'lifterlms' ), 'error' );

		}

		if ( empty( $account_email ) || ! is_email( $account_email ) ) {

			llms_add_notice( __( 'Please provide a valid email address.', 'lifterlms' ), 'error' );

		} 

		elseif ( email_exists( $account_email ) && $account_email !== $current_user->user_email ) {

			llms_add_notice( __( 'The email entered is associated with another account.', 'lifterlms' ), 'error' );

		}

		if ( ! empty( $pass1 ) && empty( $pass2 ) ) {

			llms_add_notice( __( 'Please re-enter your password.', 'lifterlms' ), 'error' );

		} 

		elseif ( ! empty( $pass1 ) && $pass1 !== $pass2 ) {

			llms_add_notice( __( 'Passwords do not match.', 'lifterlms' ), 'error' );

		}

		do_action_ref_array( 'user_profile_update_errors', array ( &$errors, $update, &$user ) );

		if ( $errors->get_error_messages() ) {

			foreach ( $errors->get_error_messages() as $error ) {

				llms_add_notice( $error, 'error' );

			}

		}

		// if no errors were returned save the data
		if ( llms_notice_count( 'error' ) == 0 ) {

			wp_update_user( $user ) ;

			llms_add_notice( __( 'Account details were changed successfully.', 'lifterlms' ) );

			do_action( 'lifterlms_save_account_details', $user->ID );

			wp_safe_redirect( get_permalink( llms_get_page_id( 'myaccount' ) ) );

			exit;
		}

	}

	/**
	* login form
	*
	* @return void
	*/
	public function login() {

		if ( ! empty( $_POST['login'] ) && ! empty( $_POST['_wpnonce'] ) ) {

			wp_verify_nonce( $_POST['_wpnonce'], 'lifterlms-login' );

			try {

				$creds  = array();

				$validation_error = new WP_Error();

				$validation_error = apply_filters( 'lifterlms_login_errors', $validation_error, $_POST['username'], $_POST['password'] );

				if ( $validation_error->get_error_code() ) {

					throw new Exception( '<strong>' . __( 'Error', 'lifterlms' ) . ':</strong> ' . $validation_error->get_error_message() );
				
				}

				if ( empty( $_POST['username'] ) ) {

					throw new Exception( '<strong>' . __( 'Error', 'lifterlms' ) . ':</strong> ' . __( 'Username is required.', 'lifterlms' ) );
				
				}

				if ( empty( $_POST['password'] ) ) {

					throw new Exception( '<strong>' . __( 'Error', 'lifterlms' ) . ':</strong> ' . __( 'Password is required.', 'lifterlms' ) );
				
				}

				if ( is_email( $_POST['username'] ) && apply_filters( 'lifterlms_get_username_from_email', true ) ) {

					$user = get_user_by( 'email', $_POST['username'] );

					if ( isset( $user->user_login ) ) {

						$creds['user_login'] 	= $user->user_login;

					} 

					else {

						throw new Exception( '<strong>' . __( 'Error', 'lifterlms' ) . ':</strong> ' . __( 'A user could not be found with this email address.', 'lifterlms' ) );
					
					}

				} 

				else {

					$creds['user_login'] = $_POST['username'];

				}

				$creds['user_password'] = $_POST['password'];
				$creds['remember'] = isset( $_POST['rememberme'] );
				$secure_cookie = is_ssl() ? true : false;
				$user = wp_signon( apply_filters( 'lifterlms_login_credentials', $creds ), $secure_cookie );

				if ( is_wp_error( $user ) ) {

					throw new Exception( $user->get_error_message() );

				} 

				else {

					if ( ! empty( $_POST['redirect'] ) ) {

						$redirect = esc_url( $_POST['redirect'] );

					} 
					
					elseif ( wp_get_referer() ) {

						$redirect = esc_url( wp_get_referer() );

					} 

					else {

						$redirect = esc_url( get_permalink( llms_get_page_id( 'myaccount' ) ) );

					}

					// Feedback
					llms_add_notice( sprintf( __( 'You are now logged in as <strong>%s</strong>', 'lifterlms' ), $user->display_name ) );

					if ( ! empty($_POST['product_id']) ) {

						$product_id = $_POST['product_id'];
						$checkout_url = get_permalink( llms_get_page_id( 'checkout' ) );
						$checkout_redirect = add_query_arg( 'product', $product_id, $checkout_url );

						wp_redirect( apply_filters( 'lifterlms_checkout_redirect', $checkout_redirect, $user ) );
						exit;
					}

					else {
						wp_redirect( apply_filters( 'lifterlms_checkout_redirect', $redirect, $user ) );
						exit;
					}
				}

			} 

			catch (Exception $e) {

				llms_add_notice( apply_filters('login_errors', $e->getMessage() ), 'error' );

			}
		}

	}

	/**
	* Reset password form
	*
	* @return void
	*/
	public function reset_password() {

		if ( ! isset( $_POST['llms_reset_password'] ) ) {

			return;
		}

		// process lost password form
		if ( isset( $_POST['user_login'] ) && isset( $_POST['_wpnonce'] ) ) {

			wp_verify_nonce( $_POST['_wpnonce'], 'lifterlms-lost_password' );

			LLMS_Shortcode_My_Account::retrieve_password();

		}

		// process reset password form
		if ( isset( $_POST['password_1'] ) 
			&& isset( $_POST['password_2'] ) 
			&& isset( $_POST['reset_key'] ) 
			&& isset( $_POST['reset_login'] ) 
			&& isset( $_POST['_wpnonce'] ) ) {

			// verify reset key again
			$user = LLMS_Shortcode_My_Account::check_password_reset_key( $_POST['reset_key'], $_POST['reset_login'] );

			if ( is_object( $user ) ) {

				// save these values into the form again in case of errors
				$args['key']   = llms_clean( $_POST['reset_key'] );
				$args['login'] = llms_clean( $_POST['reset_login'] );

				wp_verify_nonce( $_POST['_wpnonce'], 'lifterlms-reset_password' );

				if ( empty( $_POST['password_1'] ) || empty( $_POST['password_2'] ) ) {

					llms_add_notice( __( 'Please enter your password.', 'lifterlms' ), 'error' );
					$args['form'] = 'reset_password';

				}

				if ( $_POST[ 'password_1' ] !== $_POST[ 'password_2' ] ) {

					llms_add_notice( __( 'Passwords do not match.', 'lifterlms' ), 'error' );
					$args['form'] = 'reset_password';

				}

				$errors = new WP_Error();
				do_action( 'validate_password_reset', $errors, $user );

				if ( $errors->get_error_messages() ) {

					foreach ( $errors->get_error_messages() as $error ) {

						llms_add_notice( $error, 'error');
					}

				}

				if ( 0 == llms_notice_count( 'error' ) ) {

					LLMS_Shortcode_My_Account::reset_password( $user, $_POST['password_1'] );

					do_action( 'lifterlms_person_reset_password', $user );

					wp_redirect( add_query_arg( 'reset', 'true', remove_query_arg( array( 'key', 'login' ) ) ) );

					exit;
				}
			}

		}

	}

	/**
	* User Registration form
	*
	* @return void
	*/
	public function user_registration() {

		if ( ! empty( $_POST['register'] ) ) {

			wp_verify_nonce( $_POST['register'], 'lifterlms-register' );

			if ( 'no' === get_option( 'lifterlms_registration_generate_username' ) ) {

				$_username = $_POST['username'];

			} 

			else {

				$_username = '';
			}

			if ( 'no' === get_option( 'lifterlms_registration_generate_password' ) ) {

				$_password = $_POST['password'];
			}

			else {

				$_password = '';
			}

			try {

				$validation_error = new WP_Error();
				$validation_error = apply_filters( 'lifterlms_user_registration_errors', $validation_error, $_username, $_password, $_POST['email'] );

				if ( $validation_error->get_error_code() ) {

					throw new Exception( '<strong>' . __( 'Error', 'lifterlms' ) . ':</strong> ' . $validation_error->get_error_message() );

				}

			} 

			catch ( Exception $e ) {

				llms_add_notice( $e->getMessage(), 'error' );
				return;

			}

			$username   = ! empty( $_username ) ? llms_clean( $_username ) : '';
			$email      = ! empty( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
			$password   = $_password;

			// Anti-spam trap
			if ( ! empty( $_POST['email_2'] ) ) {

				llms_add_notice( '<strong>' . __( 'ERROR', 'lifterlms' ) . '</strong>: ' . __( 'Anti-spam field was filled in.', 'lifterlms' ), 'error' );
				return;

			}

			$new_person = llms_create_new_person( $email, $username, $password );

			if ( is_wp_error( $new_person ) ) {

				llms_add_notice( $new_person->get_error_message(), 'error' );
				return;

			}

			llms_set_person_auth_cookie( $new_person );

			// Redirect
			if ( wp_get_referer() ) {

				$redirect = esc_url( wp_get_referer() );
			} 

			else {

				$redirect = esc_url( get_permalink( llms_get_page_id( 'myaccount' ) ) );

			}

			if ( ! empty($_POST['product_id']) ) {

				$product_id = $_POST['product_id'];
				$checkout_url = get_permalink( llms_get_page_id( 'checkout' ) );
				$checkout_redirect = add_query_arg( 'product', $product_id, $checkout_url );

				wp_redirect( apply_filters( 'lifterlms_checkout_redirect', $checkout_redirect ) );
				exit;
			}

			else {
				wp_redirect( apply_filters( 'lifterlms_registration_redirect', $redirect ) );
				exit;
			}
		}
		
	}

}

new LLMS_Frontend_Forms();