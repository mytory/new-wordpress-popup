<?php

if(!class_exists('popoverpublic')) {

	class popoverpublic {

		var $mylocation = '';
		var $build = 5;
		var $db;

		var $tables = array( 'popover', 'popover_ip_cache' );
		var $popover;
		var $popover_ip_cache;

		var $activepopover = false;

        var $header_code = '';
        var $footer_code = '';

		function __construct() {

			global $wpdb;

			$this->db =& $wpdb;

			foreach($this->tables as $table) {
				$this->$table = popover_db_prefix($this->db, $table);
			}

			add_action('init', array(&$this, 'selective_message_display'), 99);

			add_action( 'plugins_loaded', array(&$this, 'load_textdomain'));

			$directories = explode(DIRECTORY_SEPARATOR,dirname(__FILE__));
			$this->mylocation = $directories[count($directories)-1];

			$installed = get_option('popover_installed', false);

			if($installed === false || $installed != $this->build) {
				$this->install();

				update_option('popover_installed', $this->build);
			}

		}

		function popoverpublic() {
			$this->__construct();
		}

		function install() {

			if($this->db->get_var( "SHOW TABLES LIKE '" . $this->popover . "' ") != $this->popover) {
				 $sql = "CREATE TABLE `" . $this->popover . "` (
				  	`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
					  `popover_title` varchar(250) DEFAULT NULL,
					  `popover_content` text,
					  `popover_settings` text,
					  `popover_order` bigint(20) DEFAULT '0',
					  `popover_active` int(11) DEFAULT '0',
					  PRIMARY KEY (`id`)
					)";

				$this->db->query($sql);

			}

			// Add in IP cache table
			if($this->db->get_var( "SHOW TABLES LIKE '" . $this->popover_ip_cache . "' ") != $this->popover_ip_cache) {
				 $sql = "CREATE TABLE `" . $this->popover_ip_cache . "` (
				  	`IP` varchar(12) NOT NULL DEFAULT '',
					  `country` varchar(2) DEFAULT NULL,
					  `cached` bigint(20) DEFAULT NULL,
					  PRIMARY KEY (`IP`),
					  KEY `cached` (`cached`)
					)";

				$this->db->query($sql);

			}

		}

		function load_textdomain() {

			$locale = apply_filters( 'popover_locale', get_locale() );
			$mofile = popover_dir( "popoverincludes/languages/popover-$locale.mo" );

			if ( file_exists( $mofile ) )
				load_textdomain( 'popover', $mofile );

		}

		function get_active_popovers() {
			$sql = "SELECT * FROM {$this->popover} WHERE popover_active = 1 ORDER BY popover_order ASC";

			return $this->db->get_results( $sql );
		}

		function selective_message_display() {

			if(function_exists('get_site_option') && defined('PO_GLOBAL') && PO_GLOBAL == true) {
				$updateoption = 'update_site_option';
				$getoption = 'get_site_option';
			} else {
				$updateoption = 'update_option';
				$getoption = 'get_option';
			}

			$popovers = $this->get_active_popovers();

			if(!empty($popovers)) {

				foreach( (array) $popovers as $popover ) {

					// We have an active popover so extract the information and test it
					$popover_title = stripslashes($popover->popover_title);
					$popover_content = stripslashes($popover->popover_content);
					$popover->popover_settings = unserialize($popover->popover_settings);

					$popover_size = $popover->popover_settings['popover_size'];
					$popover_location = $popover->popover_settings['popover_location'];
					$popover_colour = $popover->popover_settings['popover_colour'];
					$popover_margin = $popover->popover_settings['popover_margin'];

					$popover_size = $this->sanitise_array($popover_size);
					$popover_location = $this->sanitise_array($popover_location);
					$popover_colour = $this->sanitise_array($popover_colour);
					$popover_margin = $this->sanitise_array($popover_margin);

					$popover_check = $popover->popover_settings['popover_check'];
					$popover_ereg = $popover->popover_settings['popover_ereg'];
					$popover_count = $popover->popover_settings['popover_count'];

					$popover_usejs = $popover->popover_settings['popover_usejs'];

					$popoverstyle = (isset($popover->popover_settings['popover_style'])) ? $popover->popover_settings['popover_style'] : '';

					$popover_hideforever = (isset($popover->popover_settings['popoverhideforeverlink'])) ? $popover->popover_settings['popoverhideforeverlink'] : '';

					$popover_delay = (isset($popover->popover_settings['popoverdelay'])) ? $popover->popover_settings['popoverdelay'] : '';

					$popover_onurl = (isset($popover->popover_settings['onurl'])) ? $popover->popover_settings['onurl'] : '';
					$popover_notonurl = (isset($popover->popover_settings['notonurl'])) ? $popover->popover_settings['notonurl'] : '';

					$popover_onurl = $this->sanitise_array($popover_onurl);
					$popover_notonurl = $this->sanitise_array($popover_notonurl);

					$show = true;

					if(!empty($popover_check)) {

						$order = explode(',', $popover_check['order']);

						foreach($order as $key) {

							switch ($key) {

								case "supporter":
													if(function_exists('is_pro_site') && is_pro_site()) {
														$show = false;
													}
													break;

								case "loggedin":	if($this->is_loggedin()) {
														$show = false;
													}
													break;

								case "isloggedin":	if(!$this->is_loggedin()) {
														$show = false;
													}
													break;

								case "commented":	if($this->has_commented()) {
														$show = false;
													}
													break;

								case "searchengine":
													if(!$this->is_fromsearchengine()) {
														$show = false;
													}
													break;

								case "internal":	$internal = str_replace('http://','',get_option('home'));
													if($this->referrer_matches(addcslashes($internal,"/"))) {
														$show = false;
													}
													break;

								case "referrer":	$match = $popover_ereg;
													if(!$this->referrer_matches(addcslashes($match,"/"))) {
														$show = false;
													}
													break;

								case "count":		if($this->has_reached_limit($popover_count)) {
														$show = false;
													}
													break;

								case 'onurl':		if(!$this->onurl( $popover_onurl )) {
														$show = false;
													}
													break;

								case 'notonurl':	if($this->onurl( $popover_notonurl )) {
														$show = false;
													}
													break;

								default:			if(has_filter('popover_process_rule_' . $key)) {
														if(!apply_filters( 'popover_process_rule_' . $key, false )) {
															$show = false;
														}
													}
													break;

							}
						}
					}

					if($show == true) {

						if($this->clear_forever($popover->id)) {
							$show = false;
						}

					}

					if($show == true) {

						// Store the active popover so we know what we are using in the footer.
						$this->activepopover = $popover;

						wp_enqueue_script('jquery');

						$popover_messagebox = 'a' . $popover->id . '-po';
						// Show the advert
						wp_enqueue_script('popoverjs', popover_url('popoverincludes/js/popover.js'), array('jquery'), $this->build);
						if(!empty($popover_delay) && $popover_delay != 'immediate') {
							// Set the delay
							wp_localize_script('popoverjs', 'popover', array(	'messagebox'		=>	'#' . $popover_messagebox,
																				'messagedelay'		=>	$popover_delay * 1000
																				));
						} else {
							wp_localize_script('popoverjs', 'popover', array(	'messagebox'		=>	'#' . $popover_messagebox,
																				'messagedelay'		=>	0
																				));
						}

						if($popover_usejs == 'yes') {
							wp_enqueue_script('popoveroverridejs', popover_url('popoverincludes/js/popoversizing.js'), array('jquery'), $this->build);
						}

                        $this->header_code .= $this->page_header();
                        $this->footer_code .= $this->page_footer();

						// Add the cookie
						if ( isset($_COOKIE['popover_view_'.COOKIEHASH]) ) {
							$count = intval($_COOKIE['popover_view_'.COOKIEHASH]);
							if(!is_numeric($count)) $count = 0;
							$count++;
						} else {
							$count = 1;
						}
						if(!headers_sent()){
                            setcookie('popover_view_'.COOKIEHASH, $count , time() + 30000000, COOKIEPATH, COOKIE_DOMAIN);
                        }

					}


				}

			}
            add_action('wp_head', array(&$this, 'echo_header_code'));
            add_action('wp_footer', array(&$this, 'echo_footer_code'));

		}

        function echo_header_code(){
            echo $this->header_code;
        }

        function echo_footer_code(){
            echo $this->footer_code;
        }

		function sanitise_array($arrayin) {
			if (!is_array($arrayin)) {
				return $arrayin;
			}

			foreach( (array) $arrayin as $key => $value) {
				$arrayin[$key] = htmlentities(stripslashes($value) ,ENT_QUOTES, 'UTF-8');
			}

			return $arrayin;
		}

		function page_header() {

			if(!$this->activepopover) {
				return;
			}

			$popover = $this->activepopover;

			$popover_title = stripslashes($popover->popover_title);
			$popover_content = stripslashes($popover->popover_content);

			$popover_size = $popover->popover_settings['popover_size'];
			$popover_location = $popover->popover_settings['popover_location'];
			$popover_colour = $popover->popover_settings['popover_colour'];
			$popover_margin = $popover->popover_settings['popover_margin'];

			$popover_size = $this->sanitise_array($popover_size);
			$popover_location = $this->sanitise_array($popover_location);
			$popover_colour = $this->sanitise_array($popover_colour);
			$popover_margin = $this->sanitise_array($popover_margin);

			$popover_check = $popover->popover_settings['popover_check'];
			$popover_ereg = $popover->popover_settings['popover_ereg'];
			$popover_count = $popover->popover_settings['popover_count'];

			$popover_usejs = $popover->popover_settings['popover_usejs'];

			$popoverstyle = $popover->popover_settings['popover_style'];

			$popover_hideforever = $popover->popover_settings['popoverhideforeverlink'];

			$popover_delay = $popover->popover_settings['popoverdelay'];

			$popover_messagebox = 'a' . $popover->id . '-po';

			$availablestyles = apply_filters( 'popover_available_styles_directory', array() );
			$availablestylesurl = apply_filters( 'popover_available_styles_url', array() );

			if( in_array($popoverstyle, array_keys($availablestyles)) ) {
				// Add the styles
				if(file_exists(trailingslashit($availablestyles[$popoverstyle]) . 'style.css')) {
					ob_start();
					include( trailingslashit($availablestyles[$popoverstyle]) . 'style.css' );
					$content = ob_get_contents();
					ob_end_clean();

                    ob_start();
					echo "<style type='text/css'>\n";
					$content = str_replace('.nwp-msgbox', '#' . $popover_messagebox, $content);
					$content = str_replace('%styleurl%', trailingslashit($availablestylesurl[$popoverstyle]), $content);
					echo $content;
					echo "</style>\n";
                    $return = ob_get_contents();
                    ob_end_clean();
                    return $return;
				}

			}
            return '';

		}

		function page_footer() {

			if(!$this->activepopover) {
				return;
			}

			$popover = $this->activepopover;

			$popover_title = stripslashes($popover->popover_title);
			$popover_content = stripslashes($popover->popover_content);

			$popover_size = $popover->popover_settings['popover_size'];
			$popover_location = $popover->popover_settings['popover_location'];
			$popover_colour = $popover->popover_settings['popover_colour'];
			$popover_margin = $popover->popover_settings['popover_margin'];

			$popover_size = $this->sanitise_array($popover_size);
			$popover_location = $this->sanitise_array($popover_location);
			$popover_colour = $this->sanitise_array($popover_colour);
			$popover_margin = $this->sanitise_array($popover_margin);

			$popover_check = $popover->popover_settings['popover_check'];
			$popover_ereg = $popover->popover_settings['popover_ereg'];
			$popover_count = $popover->popover_settings['popover_count'];

			$popover_usejs = $popover->popover_settings['popover_usejs'];

			$popoverstyle = $popover->popover_settings['popover_style'];

			$popover_hideforever = $popover->popover_settings['popoverhideforeverlink'];

			$popover_delay = $popover->popover_settings['popoverdelay'];

			$style = '';
			$backgroundstyle = '';

			if($popover_usejs == 'yes') {
				$style = 'z-index:999999;';
				$style .= 'left: -1000px; top: =100px;';
				$box = 'color: ' . $popover_colour['fore'] . '; background: ' . $popover_colour['back'] . ';';
				$box .= 'padding-top: ' . $popover_margin['top'] . '; padding-bottom: ' . $popover_margin['bottom'] . '; padding-right: ' . $popover_margin['right'] . '; padding-left: ' . $popover_margin['left'] . ';';
			} else {
				$style =  'left: ' . $popover_location['left'] . '; top: ' . $popover_location['top'] . ';' . ' z-index:999999;';

				$box = 'width: ' . $popover_size['width'] . '; height: ' . $popover_size['height'] . '; color: ' . $popover_colour['fore'] . '; background: ' . $popover_colour['back'] . ';';
				$box .= 'padding-top: ' . $popover_margin['top'] . '; padding-bottom: ' . $popover_margin['bottom'] . '; padding-right: ' . $popover_margin['right'] . '; padding-left: ' . $popover_margin['left'] . ';';
			}

			if(!empty($popover_delay) && $popover_delay != 'immediate') {
				// Hide the popover initially
				$style .= ' visibility: hidden;';
				$backgroundstyle .= ' visibility: hidden;';
			}

			$availablestyles = apply_filters( 'popover_available_styles_directory', array() );

			if( in_array($popoverstyle, array_keys($availablestyles)) ) {
				$popover_messagebox = 'a' . $popover->id . '-po';

				if(file_exists(trailingslashit($availablestyles[$popoverstyle]) . 'popover.php')) {
					ob_start();
					include( trailingslashit($availablestyles[$popoverstyle]) . 'popover.php' );
                    $content = ob_get_contents();
                    ob_end_clean();
                    return $content;
				}
			}
            return '';

		}

		function is_fromsearchengine() {
			$ref = $_SERVER['HTTP_REFERER'];

			$SE = array('/search?', '.google.', 'web.info.com', 'search.', 'del.icio.us/search', 'soso.com', '/search/', '.yahoo.', '.bing.' );

			foreach ($SE as $url) {
				if (strpos($ref,$url)!==false) return true;
			}
			return false;
		}

		function is_ie()
		{
		    if (isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false))
		        return true;
		    else
		        return false;
		}

		function is_loggedin() {
			return is_user_logged_in();
		}

		function has_commented() {
			if ( isset($_COOKIE['comment_author_'.COOKIEHASH]) ) {
				return true;
			} else {
				return false;
			}
		}

		function referrer_matches($check) {

			$referer = (isset($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : '';

			if(preg_match( '/' . $check . '/i', $referer )) {
				return true;
			} else {
				return false;
			}

		}

		function has_reached_limit($count = 3) {
			if ( isset($_COOKIE['popover_view_'.COOKIEHASH]) && addslashes($_COOKIE['popover_view_'.COOKIEHASH]) >= $count ) {
				return true;
			} else {
				return false;
			}
		}

		function myURL() {

		 	if ($_SERVER["HTTPS"] == "on") {
				$url .= "https://";
			} else {
				$url = 'http://';
			}

			if ($_SERVER["SERVER_PORT"] != "80") {
		  		$url .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
		 	} else {
		  		$url .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
		 	}

		 	return trailingslashit($url);
		}

		function onurl( $urllist = array() ) {

			$urllist = array_map( 'trim', $urllist );

			if(!empty($urllist)) {
				if(in_array($this->myURL(), $urllist)) {
					// we are on the list
					return true;
				} else {
					return false;
				}
			} else {
				return true;
			}

		}

		function insertonduplicate($table, $data) {

			global $wpdb;

			$fields = array_keys($data);
			$formatted_fields = array();
			foreach ( $fields as $field ) {
				$form = '%s';
				$formatted_fields[] = $form;
			}
			$sql = "INSERT INTO `$table` (`" . implode( '`,`', $fields ) . "`) VALUES ('" . implode( "','", $formatted_fields ) . "')";
			$sql .= " ON DUPLICATE KEY UPDATE ";

			$dup = array();
			foreach($fields as $field) {
				$dup[] = "`" . $field . "` = VALUES(`" . $field . "`)";
			}

			$sql .= implode(',', $dup);

			return $wpdb->query( $wpdb->prepare( $sql, $data) );
		}

		function clear_forever($popover_id) {
			if ( isset($_COOKIE['popover-' . $popover_id]) ) {
				return true;
			} else {
				return false;
			}
		}

	}

}
