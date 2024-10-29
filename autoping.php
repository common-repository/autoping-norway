<?php
/*
Plugin Name: Autoping Norway
Plugin URI: http://sparhell.no/knut/?page_id=838
Description: Pings some Norwegian and international(RPC) pingback services. This plugin is dying, as the original services no longer exists. The remaining functions is stil usable. Next version will clean up all options from your database, so you may let be installed for some time.
Version: 2.6.3
Author: Knut Sparhell
Author URI: http://sparhell.no/knut/?page_id=15
-----------------------------------------------------------------------------------------------------------------------------
*/

/*
ini_set( "display_errors", TRUE );
error_reporting(E_ALL ^ E_NOTICE);
*/
/*
define ( 'AUTOPING_DEBUG_URL', 'http://sparhell.no/rec.asp?ping=' );
*/
define ( 'AUTOPING_PHP_MIN_VERSION', '5' );
define ( 'AUTOPING_WP_MIN_VERSION', '2.7' );

require_once("xml2ary.php");
require_once(ABSPATH.'/wp-includes/class-snoopy.php');

function implode_with_key( $assoc, $inglue='>', $outglue=';' ) {
	$return = '';
	foreach ( $assoc as $tk=>$tv ) {
		$return .= $outglue.$tk.$inglue.$tv;
	}
	return substr($return,strlen($outglue));
}

function autoping_plugin_data( $key='' ) {
	static $plugin_data = array();
	if ( count($plugin_data) == 0 ) $plugin_data = get_plugin_data(__FILE__);
	if ( empty($key) ) return $plugin_data;
	else return $plugin_data[$key];
}

function autoping_initialize_options() {
	$install_options = xml2ary(file_get_contents(dirname(__FILE__).'/config.xml'));
	$current_options = get_option('autoping_config');
	$install_version = $install_options['config']['_a']['version'];
	$current_version = $current_options['config']['_a']['version'];
	$charset = get_option('blog_charset');
	if ( $current_options===FALSE ) 
		add_option( 'autoping_config', $install_options );
	else if ( version_compare($install_version,$current_version)!=0 )
		update_option( 'autoping_config', $install_options );
	foreach ( $install_options['config']['_c']['services']['_c'] as $name=>$value ) {
		if ( $install_options['config']['_c']['services']['_c'][$name]['_a']['enabled']=='true' ) {
			$enc = $install_options['config']['_c']['services']['_c'][$name]['_c']['categories']['_a']['encoding'];
			$src = $install_options['config']['_c']['services']['_c'][$name]['_c']['categories']['_a']['src'];
			if ( strpos($src,'http://')===FALSE ) $src = realpath(dirname(__FILE__).'/'.$src);
			if ( empty($enc) || strtoupper($enc)==$charset )
				$install_services = xml2ary(file_get_contents($src));
			else {
				$install_services = xml2ary(file_get_contents($src),$enc);
				if ( $charset=='UTF-8' )
					$install_services = xml2ary(utf8_encode(file_get_contents($src)),$enc);
				else 
					$install_services = xml2ary(file_get_contents($src),$enc); // Doubt this line!
			}
			$current_services = get_option('autoping_'.$name);
			$install_l_update = strtotime($install_services['categories']['_a']['lastupdate']);
			$current_l_update = strtotime($current_services['categories']['_a']['lastupdate']);
			if ( $current_services === FALSE )
				add_option( 'autoping_'.$name, $install_services );
			else if ( $install_l_update > $current_l_update )
				update_option( 'autoping_'.$name, $install_services );
		}
	}
	@delete_option ( 'autoping_bloggrevyen');
	@delete_option ( 'tb-arkivet-revyen.php' );
}

function services( $attribute = 'enabled', $registered = NULL, $author = FALSE ) {
	global $user_ID;
	if ( !$user_ID ) get_currentuserinfo();
	$services = get_option('autoping_config');
	$services = $services['config']['_c']['services']['_c'];
	$return   = $services;
	$i = 0;
	foreach ( $services as $service=>$value ) {
		$keys = array_keys($services);
		$att = $services[$service]['_a'][$attribute];
		if ( empty($att) || $att===FALSE || $att=='false' || $att=='0' || $att=='no' )
			unset ( $return[$service] );
		elseif ( $attribute!='enabled' && ($att!=$registered || $services[$service]['_a']['enabled']=='false') )
			unset ( $return[$service] );
		$elements = $return[$service]['_c'];
		$is_reg = isset($elements) && array_key_exists('register',$elements);
		if ( $is_reg ) {
			$auth = get_usermeta($user_ID,'autoping_'.$keys[$i].'_auth');
			if ( $auth===FALSE || empty($auth) ) $auth = get_option('autoping_'.$keys[$i].'_auth');
			if ( $auth===FALSE || empty($auth) ) $return[$service]['unregistered'] = TRUE;
		}
		if ( $attribute=='enabled' && isset($registered) ) {
			if ( $registered ) {
				if ( $author!==FALSE && !is_null($author) && $author!=0 )
					$auth = get_usermeta($author,'autoping_'.$keys[$i].'_auth');
				else
					$auth = FALSE;
				if ( $auth===FALSE || empty($auth) ) $auth = get_usermeta($user_ID,'autoping_'.$keys[$i].'_auth');
				if ( $auth===FALSE || empty($auth) ) $auth = get_option('autoping_'.$keys[$i].'_auth');
				if ( $is_reg && ($auth===FALSE || empty($auth)) ) unset ( $return[$service] );
			} else {
				if ( !$is_reg ) unset ( $return[$service] );
			}
		}
		$i ++;
	}
	return $return;
}

function autoping_meta_box() {
	global $post_ID;
	$post = get_post($post_ID);
	echo '<input type="hidden" name="autoping_nonce" id="autoping_nonce" value="'.wp_create_nonce(plugin_basename(__FILE__)).'"/>'."\n";
	$services = services('enabled',TRUE,$post->post_author);
	echo '<table>'."\n";
	foreach ( $services as $service=>$content ) {
		$method = $services[$service]['_a']['method'];
		$name = $services[$service]['_a']['name'];
		$service = 'autoping_'.$service;
		echo " <tr>\n";
		echo '  <th scope="row" style="text-align:left;font-weight:normal"><label for="'.$service.'">'.$name.'</label></th>'."\n";
		echo '  <td style="text-align:right"><select name="'.$service.'" id="'.$service.'" style="width:14em">'."\n";
		if ( $method=='trackback' ) {
			echo '   <option value="">&lt;Auto&gt;</option>'."\n";
			$categories = get_option($service);
			$categories = $categories['categories']['_c'];
			$service    = '_'.$service;
			$has_value  = get_post_meta($post_ID,$service,TRUE);
			$i = 0;
			foreach ( $categories['longname'] as $category ) {
				$value = $categories['trackbackurl'][$i]['_v'];
				if ( !empty($has_value) && $value==$has_value ) $selected = ' selected="selected"';
				else $selected = "";
				echo '   <option value="'.$value.'"'.$selected.'>'.$category['_v'].'</option>'."\n";
				$i ++;
			}
		} elseif ( $method=='form' ) {
			echo '   <option value="">&lt;Auto&gt;</option>'."\n";
			$categories = get_option($service);
			$categories = $categories['categories']['_c'];
			$service    = '_'.$service;
			$has_value  = get_post_meta($post_ID,$service,TRUE);
			$i = 0;
			foreach ( $categories['name'] as $category ) {
				$value = $categories['value'][$i]['_v'];
				if ( !empty($has_value) && $value==$has_value )
					$selected = ' selected="selected"';
				else
					$selected = "";
				echo '   <option value="'.$value.'"'.$selected.'>'.$category['_v'].'</option>'."\n";
				$i ++;
			}
		}
		if ( !empty($has_value) && $has_value=='disabled' )
			$selected = ' selected="selected"';
		else
			$selected = "";
		echo '   <option value="disabled"'.$selected.'>&lt;'.__("Don't ping",'autoping')."&gt;</option>\n";
		echo '  </select></td>'."\n"; // br
		echo "</tr>\n";
	}
	echo "</table>\n";
}

function autoping_filter_blogglisten( $value ) {
	$host = 'blogglisten.no';
	return strpos($value,$host)===FALSE;
}

function autoping_unfilter_blogglisten( $value ) {
	$host = 'blogglisten.no';
	return strpos($value,$host)!==FALSE;
}
function autoping_remove_blogglisten() {
	global $wpdb;
	$host = 'blogglisten.no';
	$posts = $wpdb->get_results("SELECT ID,to_ping,post_date FROM $wpdb->posts WHERE to_ping LIKE '%$host%'");
	$max_age  = 10*intval($services['config']['_c']['services']['_a']['max-age-days']);
	foreach ( $posts as $post ) {
		if ( (time()-strtotime($post->post_date))/60/60/24>$max_age ) {
			$to_ping = preg_split("/\s/",trim($post->to_ping));
			$pinged  = array_filter($to_ping,'autoping_unfilter_blogglisten');
			$pinged  = array_values($pinged);
			$pinged  = trim($pinged[0]);
			$to_ping = array_filter($to_ping,'autoping_filter_blogglisten');
			$to_ping = array_values($to_ping);
			unset ( $to_ping[0] );
			$to_ping = implode("\n",array_values($to_ping));
			$wpdb->query( $wpdb->prepare("UPDATE $wpdb->posts SET to_ping='$to_ping', pinged = CONCAT(pinged, '\n', '$pinged') WHERE ID = %d", $post->ID) );
		}
	}
}

function autoping_save_post($post_ID, $post) {
	global $wpdb, $user_ID;
	$services = get_option('autoping_config');
	$max_age  = 10*intval($services['config']['_c']['services']['_a']['max-age-days']);
	if ( $post->post_type=='post' && (time()-strtotime($post->post_date))/60/60/24<$max_age ) {
		if ( !defined('AUTOPING_DEBUG_URL') )
			define ( 'AUTOPING_DEBUG_URL', '' );
		if ( !$user_ID )
			get_currentuserinfo();
		$services = services('enabled',TRUE);
		$to_ping_arr = preg_split("/\s/",trim($post->to_ping));
		$pung_arr = get_pung($post_ID);
		foreach ( $services as $service=>$value ) {
			$method = $services[$service]['_a']['method'];
			$service = 'autoping_'.$service;
			if ( $method=='trackback' ) {
				$url = $_POST[$service];
				if ( empty($url) )
					delete_post_meta($post_ID,'_'.$service);
				else
					if ( !update_post_meta($post_ID,'_'.$service,$url) )
						add_post_meta($post_ID,'_'.$service,$url);
				$servicename = get_option($service);
				$servicename = $servicename['categories']['_a']['servicename'];
				foreach ( $to_ping_arr as $key=>$old_url )
					if ( stripos($old_url,$servicename)!==FALSE )
						unset ( $to_ping_arr[$key] );
				if ( $url!='disabled' ) {
					if ( empty($url) ) {
						$service_categories = get_option('autoping_categories');
						$categories = wp_get_post_categories($post_ID,array());
						$category = $categories[0];
						$url = $service_categories[$service.'_'.$category];
					}
					if ( !empty($url) ) {
						$auth = get_usermeta($post->post_author,$service.'_auth');
						if ( $auth==FALSE || empty($auth) )
							$auth = get_usermeta($user_ID,$service.'_auth');
						if ( $auth==FALSE || empty($auth) )
							$auth = get_option($service.'_auth');
						if ( $auth!==FALSE && !empty($auth) ) {
							if ( strpos($url,'?') )
								$sep = '&'; else $sep = '?';
							$url .= $sep.implode_with_key($auth,'=','&');
						}
						$url = constant('AUTOPING_DEBUG_URL').$url;
						if ( !in_array($url,$pung_arr) )
							$to_ping_arr[] = $url;
					}
				}
			} elseif ( $method=='form' ) {
				$value = $_POST[$service];
				if ( empty($value) )
					delete_post_meta($post_ID,"_".$service);
				else
					if ( !update_post_meta($post_ID,'_'.$service,$value) )
						add_post_meta($post_ID,'_'.$service,$value);
			}
		}
		$to_ping = implode("\n",array_values($to_ping_arr));
		$wpdb->update( $wpdb->posts, array('to_ping'=>$to_ping), array('ID'=>$post_ID) );
	}
	autoping_remove_blogglisten();
	return $post_ID;
}

function autoping_display_category( $services, $options, $cat ) {
	$all_options = array();
	echo " <tr>\n    ";
	echo ' <th scope="row" style="text-align:left">'.str_repeat('-',$cat->level).$cat->cat_name."</th>\n    ";
	foreach ( $services as $service=>$value ) {
		$method = $services[$service]['_a']['method'];
		$all_options[] = "autoping_".$service.'_'.$cat->cat_ID;
		echo " <td>\n     ";
		echo ' <select name="autoping_'.$service.'_'.$cat->cat_ID.'" id="autoping_'.$service.'_'.$cat->cat_ID.'">'."\n      ";
		echo ' <option value="">&lt;'.__("Don't ping","autoping").'&gt;</option>'."\n      ";
		$service = "autoping_".$service;
		$categories = get_option($service);
		$categories = $categories['categories']['_c'];
		$has_value = $options[$service.'_'.$cat->cat_ID];
		if ( $method=='trackback' ) {
			$i = 0;
			foreach ( $categories['longname'] as $category ) {
				$url = $categories['trackbackurl'][$i]['_v'];
				if ( !empty($has_value) && $url==$has_value ) $selected = ' selected="selected"';
				else $selected = "";
				echo ' <option value="'.$url.'"'.$selected.'>'.$category['_v'].'</option>'."\n      ";
				$i ++;
			}
		} elseif ( $method=='form' ) {
			$i = 0;
			foreach ( $categories['name'] as $category ) {
				$value = $categories['value'][$i]['_v'];
				if ( !empty($has_value) && $value==$has_value ) $selected = ' selected="selected"';
				else $selected = "";
				echo ' <option value="'.$value.'"'.$selected.'>'.$category['_v'].'</option>'."\n      ";
				$i ++;
			}
		}
		echo "</select>\n     ";
		echo "</td>\n    ";
	}
	echo "</tr>\n   ";
	return $all_options;
}

function get_post_categories_hierarchically( $parent = 0 ) {
	static $level = 0;
	static $cats = array();
	$categories = get_categories(array('type'=>'post','hide_empty'=>FALSE));
	foreach ( $categories as $category ) {
		if ( $category->parent==$parent ) {
			$category->level = $level;
			$cats[] = $category;
			$level ++;
			get_post_categories_hierarchically($category->cat_ID);
			$level --;
		}
	}
	return $cats;
}

function autoping_categories() {
	global $wpdb, $user_ID;
	if ( !$user_ID )
		get_currentuserinfo();
	$regpage = 'users.php?page=autoping-register-user';
	if ( $_SERVER['REQUEST_METHOD']=='POST' ) {
		$options = $_POST;
		if	( $options['action']=='update') {
			check_admin_referer('autoping_update_options');
			$all_options = explode(',',$options['page_options']);
			foreach ( $options as $option=>$value ) 
				if ( empty($value) || in_array($option,$all_options)==FALSE ) unset ( $options[$option]);
 			if ( get_option('autoping_categories')===FALSE )
				add_option('autoping_categories',$options);
			else
				update_option('autoping_categories',$options);
			echo '<div id="message" class="updated fade"><p><strong>'.__("Settings saved.")."</strong></p></div>\n";
		}
	}
	$options = get_option('autoping_categories');
	$plugin  = __("Norway",'autoping'); // DUMMY for rescan
	$plugin  = autoping_plugin_data('Name');
	$names   = explode(' ',$plugin);
	if ( count($names)>1 ) $names[1] = __($names[1],'autoping');
	$plugin  = implode(' ',$names);
	$version = autoping_plugin_data('Version');
	$uri     = autoping_plugin_data('PluginURI');
	$desc    = autoping_plugin_data('Description');
	$phpver  = phpversion();
	echo ' <div class="wrap">'."\n ";
	echo ' <form action="" method="post">'."\n  ";
	echo " "; wp_nonce_field('autoping_update_options'); echo "\n  ";
	$all_options = array();
	echo ' <div id="icon-edit" class="icon32"><br/></div>'."\n  ";
	echo ' <h2>'.$plugin.': '.__("Categories")."</h2>\n  ";
	echo " <table>\n   ";
	$services = services();
	echo ' <caption><strong><em>'.__("Map automatic","autoping").' '.__("Categories").' for '.$name.'</em><br/><br/></strong></caption>'."\n   ";
	echo ' <tr>'."\n    ";
	echo ' <th scope="col">'.get_option('blogname').'</th>'."\n    ";
	foreach ( $services as $service=>$value ) {
		$name = $services[$service]['_a']['name'];
		echo ' <th scope="col">'.$name."</th>\n    ";
	}
	echo "</tr>\n   ";
	$categories = get_post_categories_hierarchically();
	foreach ( $categories as $category ) {
		$all_options = array_merge($all_options,autoping_display_category($services,$options,$category));
	}
	$all_options = implode(",",$all_options);
	echo ' <tr>'."\n    ";
	echo ' <td colspan="'.(count($services)+1).'"><hr/></td>'."\n    ";
	echo "</tr>\n   ";
	echo ' <tr>'."\n    ";
	echo ' <th scope="col" style="text-align:left">'.__("Method","autoping")."</th>\n    ";
	foreach ( $services as $service=>$value ) {
		$method = $services[$service]['_a']['method'];
		echo ' <td><select disabled="disabled"><option>'.$method." </option></select></td>\n    ";
	}
	echo "</tr>\n   ";
	echo ' <tr>'."\n    ";
	echo ' <th scope="col" style="text-align:left">'.__("Registering","autoping")."</th>\n    ";
	foreach ( $services as $service=>$value ) {
		if ( array_key_exists('register',$services[$service]['_c']) ) {
			if ( $services[$service]['unregistered'] ) {
				echo ' <td class="error">'.__("Missing",'autoping').'. <a href="'.$regpage.'">'.__("Register here first",'autoping')."</a>.</td>\n    ";
			} else {
				$servuser = get_usermeta($user_ID,'autoping_'.$service.'_auth');
				if ( $servuser===FALSE || empty($servuser) ) $servuser = get_option('autoping_'.$service.'_auth');
				echo ' <td>'.__("Registered to",'autoping').' <code>'.$servuser['username'].'</code>. <a href="'.$regpage.'" title="'.__("Registering","autoping").'">'.__("Not you",'autoping')."?</a></td>\n    ";
			}
		} else echo ' <td>('.__("Not used",'autoping').")</td>\n    ";
	}
	echo "</tr>\n   ";
	echo "</table>\n  ";
	echo ' <input type="hidden" name="action" value="update"/>'."\n  ";
	echo ' <input type="hidden" name="page_options" value="'.$all_options.'"/>'."\n  ";
	echo ' <p class="submit"><input type="submit" class="button-primary" value="'.__("Save Changes").'"/></p>'."\n  ";
	echo "</form>\n ";
	echo " <hr/>\n ";
	echo ' <p>'.__('Server','autoping').' <code><a href="mailto:'.$_SERVER['SERVER_ADMIN'].'?subject='.$_SERVER['SERVER_NAME'].'" title="Send mail to Server Admin">'.$_SERVER['SERVER_NAME'].'</a></code> '.__('running','autoping');
	$unames = explode(' ',php_uname());
	if ( count($unames)>7 ) {
		unset ( $unames[1] );
		$unames = array_slice($unames,0,2);
		$unamee = explode('-',$unames[1]);
		$unames[1] = $unamee[0];
	} else {
		unset ( $unames[2] );
		$unames = array_slice($unames,0,3);
	}
	$uname = implode(' ',$unames);
	echo ' PHP '.$phpver.' '.__('with','autoping').' '.php_sapi_name().', ';
	$mysql = $wpdb->get_var("SELECT VERSION()");
	$mysqls = explode('-',$mysql);
	echo ' MySQL '.$mysqls[0];
	$mysql = $wpdb->get_var("SELECT @@storage_engine");
	echo ' '.__('with','autoping').' '.$mysql;
	echo ' '.__('and','autoping');
	$ssofts = explode(' ',$_SERVER['SERVER_SOFTWARE']);
	$ssoft = $ssofts[0];
	echo ' '.$uname.' '.__('with','autoping').' '.$ssoft.'.';
	echo ' <br/><a href="'.$uri.'">'.$plugin;
	echo ' '.__('version','autoping');
	echo ' '.$version.'</a>: <blockquote lang="en" xml:lang="en">'.$desc.'</blockquote>';
	if ( defined('WPLANG') && WPLANG!='' ) {
//		echo '<br/>';
		if ( file_exists(WP_PLUGIN_DIR.'/'.dirname(plugin_basename(__FILE__)).'/languages/autoping-'.WPLANG.'.mo') ) {
			_e('Localized to','autoping');
			$langs = array( 'nb_NO'=>'Norsk (Bokm&aring;l)');
			if ( array_key_exists(WPLANG,$langs) )
				$lang = $langs[WPLANG];
			else
				$lang = '<code>'.WPLANG.'</code>';
			echo " $lang.";
		} else {
			_e('NOTICE: Localization file','autoping');
			echo '<code>'.PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)).'/languages/autoping-'.WPLANG.'.mo</code> ';
			_e('not found','autoping');
		}
	}
	echo "</p>\n ";
	echo "</div>";
}

function autoping_add_meta_box(){
	$plugin = autoping_plugin_data('Name');
	$names  = explode(' ',$plugin);
	if ( count($names)>1 ) $names[1] = __($names[1],'autoping');
	$plugin   = implode(' ',$names);
	add_meta_box( 'autoping_div', $plugin.' '.__("Categories"), 'autoping_meta_box', 'post', 'side', 'low' );
}

function autoping_add_categories_page() {
	global $wp_version;
	$name = autoping_plugin_data('Name');
	$names = explode(' ',$name);
	$name = $names[0];
	$menu = $name.' '.__("Categories");
	if ( version_compare($wp_version,'2.6.999','>') )
		$menu = '<img src="'.plugins_url(dirname(plugin_basename(__FILE__))).'/autoping.gif" alt="'.$menu.'" style="margin-right:2px"/>'.$menu;
	add_submenu_page( 'edit.php', __("Categories").' for '.$name, $menu, 'manage_categories',  'autoping-categories', 'autoping_categories' );
}

function autoping_register_page( $is_user = FALSE ) {
	global $wp_version;
	if ( $is_user ) {
		global $user_ID, $current_user;
		if ( !$user_ID ) get_currentuserinfo();
		$for = '<em>'.$current_user->display_name.'</em>';
	} else {
		$for = __("unregistered users",'autoping');
		$config = get_option('autoping_config');
		$vote = stripos('true|1|on|yes',$config['config']['_c']['services']['_c']['blopp']['_a']['vote'])!==FALSE;
	}
	$plugin   = autoping_plugin_data('Name');
	$names    = explode(' ',$plugin);
	if ( count($names)>1 ) $names[1] = __($names[1],'autoping');
	$plugin   = implode(' ',$names);
	$version  = autoping_plugin_data('Version');
	$service  = 'blopp';
	$wp_option= 'autoping_'.$service.'_auth';
	$services = services('enabled',FALSE); 
	$servatts = $services[$service]['_a'];
	$regiatts = $services[$service]['_c']['register']['_a'];
	$formatts = $services[$service]['_c']['register']['_c']['form']['_a'];
	$url      = $formatts['href'];
	$regurl   = $regiatts['href'];
	$regsrc   = $regiatts['src'];
	$userid   = $regiatts['name'];
	$as       = $regiatts['as'];
	$servname = $servatts['name'];
	echo ' <div class="wrap">'."\n ";
	if ( $_SERVER['REQUEST_METHOD']=='POST' ) {
		$options = $_POST;
		if	( $options['action']=='register') {
			$all_options = explode(",",$options['page_options']);
			if ( !$is_user ) {
				$config = get_option('autoping_config');
				if ( isset($options['vote']) ) 
					$config['config']['_c']['services']['_c']['blopp']['_a']['vote'] = 'true';
				else
					$config['config']['_c']['services']['_c']['blopp']['_a']['vote'] = 'false';
				update_option( 'autoping_config', $config );
				$vote = stripos('true|1|on|yes',$config['config']['_c']['services']['_c']['blopp']['_a']['vote'])!==FALSE;
			}
			unset ( $options['vote'] );
			if ( empty($options[$userid]) || empty($options[$regsrc]) ) {
				if ( $is_user )
					delete_usermeta( $user_ID, $wp_option );
				else
					delete_option( $wp_option );
				$message = __("Settings deleted.",'autoping').' '.__("This service is now disabled for",'autoping').' '.$for.'.';
			} else {
				foreach ( $options as $option=>$value ) {
					if ( empty($value) || in_array($option,$all_options)==FALSE )
						unset ( $options[$option] );
				}
				if ( strlen($options[$regsrc])>32 ) {
					$options[$as] = $options[$regsrc];
					$message = __("Settings saved.");
				} else {
					$snoopy = new Snoopy();
					$snoopy->agent = str_replace(' v','/',$snoopy->agent)." (WordPress/$wp_version; $plugin/$version)";
					$urls = parse_url($url);
					$snoopy->host = $urls['host'];
					if ( @$snoopy->submit($url,$options) ) {
	//					echo "<br/> Response status: ".$snoopy->response_code."\n";
						$results = new DOMDocument;
						$ok = $results->loadXML($snoopy->results); // echo $snoopy->results;
						if ( $ok ) {
							$xpath = new DOMXpath($results);
							$error = $xpath->query($formatts['error'])->item(0)->nodeValue;
							if ( $error )
								$message = $xpath->query($formatts['message'])->item(0)->nodeValue.".\n";
							else {
								$message = __("Settings saved.").' '.__("This service is now enabled for",'autoping').' '.$for.'.';
								$auth = $xpath->query($formatts['auth'])->item(0)->nodeValue;
	//							echo '<br/>Passord: '.$options[$regsrc];
	//							echo "<br/> Salt: ".$auth;
	//							echo '<br/> Hash: '.$auth.sha1($auth.$options[$regsrc]);
								$src    = $options[$regsrc];
								$method = $regiatts['method'];
								$formul = "\$hash = $method;";
								eval( $formul );
								$options[$as] = $hash;
								unset ( $options[$regsrc] );
								if ( $is_user )
									update_usermeta( $user_ID, $wp_option, $options );
								else {
									if ( get_option($wp_option)===FALSE )
										add_option( $wp_option, $options );
									else
										update_option( $wp_option, $options );
								}
							}
						} else
							$message = "Error: Response from $servname not valid XML.";
					} else
						$message = "Error: No contact with $servname: ".$snoopy->error."\n";
				}
			}
		} else
			$message = __("No action",'autoping');
		echo '<div id="message" class="updated fade"><p><strong>'."$message</strong></p></div>\n";
	}
	if ( $is_user )
		$options = get_usermeta($user_ID,$wp_option);
	else
		$options = get_option($wp_option);
	echo ' <div id="icon-edit" class="icon32"><br/></div>'."\n  ";
	echo ' <h2>'.$plugin.': '.__("Settings")." for $servname</h2>\n  ";
	echo ' <h3>'.__("Settings").' for '.$for."</h3>\n  ";
	echo " <br/><ol>\n ";
	echo ' <li><strong><a href="'.$regurl.'" target="'.$service.'">'.__("Register at",'autoping')." $servname</a> ".__("to enable this service",'autoping').".</strong></li>\n  ";
	echo ' <li><strong>'.__("Enter your",'autoping').' '.$servname.__(" registration here",'autoping').":</strong></li>\n  ";
	echo " </ol>\n";
	$url    = $formatts['src'];
	$snoopy = new Snoopy;
	$snoopy->agent = str_replace(' v','/',$snoopy->agent)." (WordPress/$wp_version; $plugin/$version)";
	$result = $snoopy->fetch($url);
	if ( $result ) {
		$doc = new DOMDocument();
		$ok = @$doc->loadHTML($snoopy->results);
		if ( $ok ) {
			$xpath = new DOMXpath($doc);
			$form = $xpath->query("//form[@id='{$formatts['id']}']")->item(0);
			$form->setAttribute('action','');
			$form->removeAttribute('enctype');
			$form->setAttribute('style','margin-left: 2em; border: 1px solid #d7d7d7; width: 50%; padding: 0 0 5px 10px');
			$labels = $xpath->query("label",$form);
			$inputs = $xpath->query("input",$form);
			foreach ( $inputs as $input ) {
				$ids[] = $input->getAttribute('id');
			}
			$i = 0;
			foreach ( $labels as $label ) {
				$label->setAttribute('for',$ids[$i]);
				$label->setAttribute('style','display: block; padding: 5px 0');
				$i ++;
			}
			$names = array();
			foreach ($inputs as $input) {
				$name = $input->getAttribute('name');
				$type = $input->getAttribute('type');
				if ( !empty($name) ) {
					if ( $name==$userid ) {
						if ( !$options || is_null($options[$userid]) || empty($options[$userid]) )
							@$input->removeAttribute( 'value' );
						else
							$input->setAttribute( 'value', $options[$userid] );
					} elseif ( $name==$regsrc ) {
						if ( !$options || is_null($options[$as]) || empty($options[$as]) )
							@$input->removeAttribute( 'value' );
						else {
							$input->setAttribute( 'value', $options[$as] );
							$input->setAttribute( 'title', $options[$as] );
							$input->setAttribute( 'onfocus', "if(this.value.length>32)this.value=''" );
						}
					}
					$input->setAttribute( 'autocomplete', 'off' );
					$input->setAttribute( 'maxlength', '32' );
					$input->setAttribute( 'style', 'margin: 2px 0 2px 0' );
					$names[] = $name;
				} elseif ( $type='submit' )
					$submit = $input;
				if ( $input->getAttribute('type')=='submit' ) {
					$input->setAttribute( 'class', 'submit button-primary' );
					$input->setAttribute( 'style', 'margin: 2px 0 2px 0' );
				}
			}
			if ( !$is_user ) {
				$node = $doc->createElement('br');
				if ( $node!==FALSE )
					$form->insertBefore( $node, $submit );
				$node = $doc->createElement('input');
				if ( $node!==FALSE ) {
					$node->setAttribute( 'type', 'checkbox' );
					$node->setAttribute( 'name', 'vote' );
					$names[] = 'vote';
					$node->setAttribute( 'style', 'margin: 2px 0 2px 0' );
					if ( $vote ) $node->setAttribute( 'checked', 'checked' );
					$form->insertBefore( $node, $submit );
				}
				$node = $doc->createTextNode(' '.__("Add vote button to posts which are completeley displayed and pinged to",'autoping').' '.$servname);
				if ( $node!==FALSE )
					$form->insertBefore( $node, $submit );
				$node = $doc->createElement('br');
				if ( $node!==FALSE )
					$form->insertBefore( $node, $submit );
				$node = $doc->createElement('br');
				if ( $node!==FALSE )
					$form->insertBefore( $node, $submit );
			}
			$node = $doc->createElement('input');
			if ( $node!==FALSE ) {
				$node->setAttribute( 'type', 'hidden' );
				$node->setAttribute( 'name', 'page_options' );
				$node->setAttribute( 'value', implode(',',$names) );
				$form->appendChild($node);
			}
			$node = $doc->createElement('input');
			if ( $node!==FALSE ) {
				$node->setAttribute( 'type', 'hidden' );
				$node->setAttribute( 'name', 'action' );
				$node->setAttribute( 'value', 'register' );
				$form->appendChild( $node );
			}
			echo $doc->saveXML($form)."\n ";
		}
	} else
		echo '<p>'.$snoopy->error."</p>\n";
//	echo '  <p>&nbsp;</p><p> <strong>'.__("Current settings",'autoping').':</strong><br/> '.__("User name",'autoping').': <code>'.$options[$userid].'</code><br/> '.__("Hash",'autoping').': <code>'.$options[$as]."</code><br/></p>\n ";
	if ( $options )
		echo '  <p class="highlight"> '.__("To delete, just enter an empty",'autoping').' &quot;'.__("Password").'&quot;. '.__("Deleting and saving here may be repeated as many times as you wish",'autoping').".</p>\n ";
	echo '  <p>&nbsp;</p><hr/><address> <small><strong>'.__("Privacy statement",'autoping').'</strong>: '.__("The password entered above will not be stored here as such. It will be sendt to",'autoping')." $servname ".__("only",'autoping').', '.__("and then your user name and a &laquo;salted&raquo; hash of your password will be stored in your WordPress database for use when pinging",'autoping')." $servname.</small></address>\n ";
	echo " </div>\n ";
}

function autoping_register_system() {
	autoping_register_page();
}

function autoping_register_user() {
	autoping_register_page( TRUE );
}

function autoping_other() {
	$plugin   = autoping_plugin_data('Name');
	$names    = explode(' ',$plugin);
	if ( count($names)>1 ) $names[1] = __($names[1],'autoping');
	$plugin   = implode(' ',$names);
	$ping_sites = get_option('ping_sites');
	$ping_sites = explode("\n",$ping_sites);
	
	foreach ( $ping_sites as $key=>$ping_site ) $ping_sites[$key] = trim($ping_sites[$key]," \n\t\r\0");
	if ( $_SERVER['REQUEST_METHOD']=='POST' ) {
		$options = $_POST;
		$all_options = $options['all_options'];
		$all_options = explode(',',$all_options);
		unset ( $options['all_options'] );
		$changed = FALSE;
		foreach ( $all_options as $url ) {
			if ( array_search($url,$options)!==FALSE ) {
				if ( !in_array($url,$ping_sites) ) {
					$ping_sites[] = $url;
					$changed = TRUE;
				}
			} else {
				$key = array_search($url,$ping_sites);
				unset ( $ping_sites[$key] );
				$changed = TRUE;
			}
		}
		if ( $changed ) {
			$ping_sites = implode("\n",$ping_sites);
			update_option( 'ping_sites', $ping_sites );
			echo '<div id="message" class="updated fade"><p><strong>'.__("Settings saved.")."</strong></p></div>\n";
			$ping_sites = explode("\n",$ping_sites);
			foreach ( $ping_sites as $key=>$ping_site )
				$ping_sites[$key] = trim($ping_sites[$key]," \n\t\r\0");
		}
	}
	$services = xml2ary(file_get_contents(dirname(__FILE__).'/other.xml'));
	echo ' <div class="wrap">'."\n ";
	echo ' <div id="icon-edit" class="icon32"><br/></div>'."\n  ";
	echo ' <h2>'.$plugin.': '.__("Settings").' '.__("for RPC pingback services",'autoping')."</h2>\n  ";
	echo ' <h3>'.__("Select the services you want to ping",'autoping')."</h2>\n  ";
	echo ' <form action="" method="post">'."\n ";
	unset ( $all_options );
	foreach ( $services['services']['_c']['service'] as $service ) {
		$name = str_replace(' ','',$service['_a']['name']);
		$url  = $service['_a']['uri'];
		$reg  = $service['_a']['reg'];
		$all_options[] = $url;
		$checked = in_array($url,$ping_sites);
		if ( $checked ) $checked = ' checked="checked"'; else $checked = '';
		echo '  <br/><input type="checkbox" name="'.$name.'" id="'.$name.'"'.$checked.' value="'.$url.'" title='.$url.'"/>'."\n";
		echo '  <label for="'.$name.'" title="'.$url.'">'.$service['_a']['name']."</label>\n";
		if ( isset($reg) ) echo '  &nbsp; <small>(<a href="'.$reg.'">'.__("Register here first",'autoping')."</a>)</small>\n";
	}
	$all_options = implode(',',$all_options);
	echo '  <input type="hidden" name="all_options" value="'.$all_options.'"/>'."\n";
	echo '  <p class="submit"><input type="submit" class="button-primary" value="'.__("Save Changes").'"/></p>'."\n";
	echo " </form>\n";
	echo "</div>";
}

function autoping_add_other_page() {
	global $wp_version;
	$plugin = autoping_plugin_data('Name');
	$names = explode(' ',$plugin);
	$plugin = $names[0].' '.__("Other",'autoping');
	$menu = $plugin;
	if ( version_compare($wp_version,'2.6.999','>') )
		$menu = '<img src="'.plugins_url(dirname(plugin_basename(__FILE__))).'/autoping.gif" alt="'.$menu.'" style="margin-right:2px"/>'.$menu;
	add_submenu_page( 'options-general.php', $plugin, $menu, 'manage_options', 'autoping-other', 'autoping_other' );
}

function autoping_add_register_pages() {
	global $wp_version;
	$plugin = autoping_plugin_data('Name');
	$names = explode(' ',$plugin);
	$plugin = $names[0];
	$services = services('enabled',FALSE);
	foreach ( $services as $service=>$value ) {
		$name = $services[$service]['_a']['name'];
		$menu = $plugin.' '.$name;
		if ( version_compare($wp_version,'2.6.999','>') )
			$menu = '<img src="'.plugins_url(dirname(plugin_basename(__FILE__))).'/autoping.gif" alt="'.$menu.'" style="margin-right:2px"/>'.$menu;
		if ( current_user_can('manage_options') )
			add_submenu_page( 'options-general.php', $plugin.' for '.$name, $menu, 'manage_options', 'autoping-register-system', 'autoping_register_system' );
		add_submenu_page('users.php', $plugin.' for '.$name, $menu, 'edit_posts', 'autoping-register-user', 'autoping_register_user' );
	}
}

function autoping_plugin_action( $links, $file ) {
	global $wp_version;
	static $plugin;
	if ( !isset($plugin) ) $plugin = plugin_basename(__FILE__);
	if ( $file==$plugin) {
		$link = '<a href="edit.php?page=autoping-categories">'.__("Categories").'</a>';
		if ( version_compare($wp_version,'2.7.999','>') )
			$links = array_merge($links,array($link));
		else
			$links = array_merge(array($link),$links);
	}
	return $links;
}

function tb_arkivet_revyen_add_notice() {
	$plugin = 'tb-arkivet-revyen/tb-arkivet-revyen.php';
	if ( is_plugin_active($plugin) ) {
		$plugin_data = get_plugin_data(WP_PLUGIN_DIR.'/'.$plugin);
		$tb_name = $plugin_data['Name'];
		deactivate_plugins($plugin);
		$name = autoping_plugin_data('Name');
		$link = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",$plugin).'readme.txt'; 
		echo '<div id="message" class="updated fade"><p>'.__("Plugin").' &quot;'.$tb_name.'&quot; '.__("is <em>not compatible</em> with","autoping").' '.__("plugin").' &quot;'.$name.'&quot;. '.__("Consult the compatibility issues in","autoping").' <a href="'.$link.'">'.__("the &quot;readme&quot; file","autoping").'</a>. '.__("Plugin").' &quot;'.$tb_name.'&quot; '.__("now <strong>deactivated</strong>","autoping").".</p></div>\n";
	}
}

function autoping_blopp_vote( $content ) {
	global $post;
	$config = get_option('autoping_config');
	$vote = stripos('true|1|on|yes',$config['config']['_c']['services']['_c']['blopp']['_a']['vote'])!==FALSE;
	if ( $vote && stripos($post->pinged,'blopp.no/')!==FALSE && ( is_single() || !preg_match('/<!--more(.*?)?-->/',$post->post_content) ) ) {
		$script = ' <sup>'.__("Vote",'autoping').':</sup> <script type="text/javascript">'."\n  submit_url='".get_permalink($post->ID)."';\n </script>\n ".'<script type="text/javascript" src="http://blopp.no/evb/check_url.js.php"></script>'."\n";
		$content .= $script;
	}
	return $content;
}

function autoping_status( $status = NULL, $status_before = NULL ) {
	global $autoping_form;
	$autoping_form = TRUE; //$status_before!='publish';
}

function autoping_publish( $post_ID = NULL ) {
	global $wp_version, $autoping_form;
	if ( $autoping_form ) {
		$post = get_post($post_ID);
		if ( $post_ID && $post->post_type=='post' ) {
			$plugin  = autoping_plugin_data('Name');
			$version = autoping_plugin_data('Version');
			$url = get_permalink($post_ID);
			$services = services('method','form');
			foreach ( $services as $service_name=>$service ) {
				$service_name = 'autoping_'.$service_name;
				$src = $service['_c']['form']['_a']['src'];
				if ( isset($_POST[$service_name]) )
					$category = $_POST[$service_name];
				else
					$category = NULL;
				if ( $category!='disabled' ) {
					if ( $category == NULL || $category===FALSE || empty($category) ) {
						$service_categories = get_option('autoping_categories');
						$post_categories = wp_get_post_categories($post->ID,array());
						$post_category = $post_categories[0];
//						echo $post_category; print_r($service_categories['autoping_blogglisten_'.$post_category]);
						$category = $service_categories[$service_name.'_'.$post_category];
					}
					if ( !empty($category) ) {
						foreach ( $service['_c']['form']['_c']['field'] as $field )
							eval ( "\$params[\$field['_a']['name']] = {$field['_a']['value']};" );
						$snoopy = new Snoopy();
						$snoopy->agent = str_replace(' v','/',$snoopy->agent)." (WordPress/$wp_version; $plugin/$version)";
						$snoopy->referer = $src;
//						@$snoopy->submit(AUTOPING_DEBUG_URL.$service['_c']['form']['_a']['action'],$params);
//						echo $snoopy->response_code;
//						echo $snoopy->results;exit;
					}
				}
			}
		}
	}
}

function autoping_add_actions() {
//	if ( current_user_can('edit_posts') ) {
//		autoping_initialize_options();
//		add_action( 'admin_menu', 'autoping_add_meta_box' );
//		add_action( 'save_post', 'autoping_save_post', 1, 2 );
//		add_filter( 'plugin_action_links', 'autoping_plugin_action', 10, 2 );
//		add_action( 'admin_menu', 'autoping_add_register_pages' );
//	}
//	if ( current_user_can('manage_categories') ) {
//		add_action( 'admin_menu', 'autoping_add_categories_page' );
//	}
	if ( current_user_can('activate_plugins') ) {
		add_action( 'admin_notices','tb_arkivet_revyen_add_notice' );
	}
	if ( current_user_can('manage_options') ) {
		add_action( 'admin_menu', 'autoping_add_other_page' );
	}
}

function autoping_add_notice() {
	global $wp_version;
	$name = autoping_plugin_data('Name');
	$plugin = plugin_basename(__FILE__);
	deactivate_plugins($plugin);
	$link = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",$plugin).'readme.txt'; 
	echo '<div id="message" class="updated fade"><p>'.__("Plugin").' &quot;'.$name.'&quot; '.__("is <em>not compatible</em> with","autoping").' '.__("this","autoping").' <acronym title="'.phpversion().'">PHP</acronym> '.__("or","autoping").' <span title="'.$wp_version.'">'.__("WordPress</span> version",'autoping').'. '.__("Consult the system requirements in","autoping").' <a href="'.$link.'">'.__("the &quot;readme&quot; file","autoping").'</a>. '.__("Plugin").' '.__("immediately <strong>deactivated</strong>","autoping").'.</p></div>'."\n";
}

function autoping_textdomain() {
	load_plugin_textdomain('autoping',FALSE,dirname(plugin_basename(__FILE__)).'/languages');
}

add_action('init','autoping_textdomain');
if ( is_admin() ) {
	if ( version_compare(phpversion(),AUTOPING_PHP_MIN_VERSION)>=0 && version_compare($wp_version,AUTOPING_WP_MIN_VERSION)>=0 )
		add_action('plugins_loaded','autoping_add_actions');
	else
		add_action('admin_notices','autoping_add_notice');
} else {
	add_filter('the_content', 'autoping_blopp_vote',10,1);
}
// file_get_contents("http://tinyurl.com/api-create.php?url=$url");
?>