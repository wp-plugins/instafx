<?php
/*
Plugin Name: InstaFX
Plugin URI: http://wordpress.org/extend/plugins/instafx/
Description: Power up your WordPress site with InstaFX, Add filtering to your WordPress images.
Version: 1.1.3
Author: ColorLabs & Company
Author URI: http://www.colorlabsproject.com
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

error_reporting();
if ( ! defined( 'ABSPATH' ) )
	die( __("Can't load this file directly") );	

class Colabs_Photofilter
{
	public $filters = array( 'majesty', 'sunrise', 'cross', 'peel', 'love', 'pinhole', 'glowing', 'hazy', 'nostalgia', 'hemingway', 'boot');
	public $consumer_key = '9oniptvwS1XN16mCar5w';
	public $consumer_secret = 'RqEiNy3RksnYm29T3TCnb1pSbOZUcdIxZrAyS9Fs';


	function __construct() {
		add_action( 'init', array( &$this, 'instafx_init' ) );		
	}
	
	function instafx_init(){ 
		$this->get_instafx_effects();
		add_shortcode( 'instafx', array( &$this, 'photo_filter') );
		add_action('wp_print_scripts', array( &$this, 'instafx_equeue') );
		add_action('wp_footer', array( &$this, 'instafx_equeue_front') );	
		
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'instafx_action_links' ) );
		
		//add_action('admin_notices', array( &$this,'instafx_admin_notice') );
		//add_action('admin_init', array( &$this,'instafx_nag_ignore') );	
		add_action('admin_enqueue_scripts', array( &$this,'instafx_admin_pointers_header' ) );
		//register_deactivation_hook( __FILE__, array( &$this,'instafx_deactivate') );
		
		add_filter( 'media_row_actions', array( &$this, 'add_media_instafx_action' ), 10, 2 );
		$this->capability = apply_filters( 'instafx_cap', 'manage_options' );
		add_action( 'admin_menu', array( &$this, 'add_admin_menu_filter' ) );
		add_action( 'admin_init', array( &$this, 'instafx_options_init') );

		add_filter( 'attachment_fields_to_edit', array( &$this, 'instafx_attachment_image_fields_to_edit'), null, 2 );
		add_filter( 'attachment_fields_to_save', array( &$this, 'instafx_attachment_image_fields_to_save'), null, 2 );
		
		add_filter( 'media_upload_library', array( &$this, 'wp_media_upload_handler_instafx') );
		add_filter( 'media_upload_gallery', array( &$this, 'wp_media_upload_handler_instafx') );
		add_filter( 'media_upload_image', array( &$this, 'wp_media_upload_handler_instafx') );
		
		add_filter( 'instafx_send_to_editor_url', array( &$this, 'media_send_to_editor_instafx'));
		
		add_filter( 'media_send_to_editor', array(&$this, 'media_send_to_editor35'), 10, 8);
		if(get_bloginfo('version') < '3.5' )
		add_filter( 'media_send_to_editor', array(&$this,'media_send_to_editor34'), 20, 3);
	}
	
	function instafx_action_links( $links ) {

		$plugin_links = array(
			'<a href="http://colorlabsproject.com/documentation/instafx/" target="_blank">' . __( 'Documentation' ) . '</a>'
		);

		return array_merge( $plugin_links, $links );
	}
	
	function instafx_deactivate() {
		global $current_user ;
		
		$user_id = $current_user->ID; 
		delete_user_meta($user_id, 'instafx_ignore_notice');
		
		
	}
	function instafx_options_init(){
		register_setting( 'instafx_options', 'instafx_field', array( &$this, 'instafx_validate' ) );
	}	
	
	function instafx_validate($input) {		
		
		$photo = $input['sendimage'];
		
		if (preg_match('/^data:image\/(jpg|jpeg|png)/i', $photo, $matches)) 
			$type = $matches[1];
		else 
			$type = null;		
		
		// Remove the mime-type header
		$data = reset(array_reverse(explode('base64,', $photo)));
		
		// Use strict mode to prevent characters from outside the base64 range
		$image = base64_decode($data, true);
		$image = array(
			'data' => $image,
			'type' => $type
		);
		
		if ($image['data']!='') { 
						
			// get the upload directory and make a test.txt file
			$upload_dir = wp_upload_dir();
			$name = $input['name'] ? $input['name'] : uniqid();
			$filename =  $name. '.' . $image['type'];
			 
			if (is_writable($upload_dir['path']) && !file_exists($upload_dir['path'] . $filename)) {
				if (file_put_contents($upload_dir['path'] . $filename, $image['data']) !== false) {
					$filename = $upload_dir['path'] . $filename;
				
					$wp_filetype = wp_check_filetype(basename($filename), null );
					$attachment = array(
							 'guid' => $upload_dir['baseurl'] . _wp_relative_upload_path( $filename ), 
							 'post_mime_type' => $wp_filetype['type'],
							 'post_title' => $name,//preg_replace('/\.[^.]+$/', '', basename($filename)),
							 'post_content' => '',
							 'post_status' => 'inherit'
					);
					require_once(ABSPATH . 'wp-admin/includes/image.php');
					$attach_id = wp_insert_attachment( $attachment, $filename  );
					$attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
					wp_update_attachment_metadata( $attach_id, $attach_data );
				} 
			} 
		}
		
		if(isset($input['filter']))
			$input['filter'] =  wp_filter_nohtml_kses($input['filter']);
			
		$input['sendimage'] =  '';
		return $input;
		
	}
	function add_media_instafx_action( $actions, $post ) {
		if ( 'image/' != substr( $post->post_mime_type, 0, 6 ) || ! current_user_can( $this->capability ) )
			return $actions;

		$url = wp_nonce_url( admin_url( 'admin.php?page=instafx-page&id=' . $post->ID ), 'instafx-page' );
		$actions['instafx_act'] = '<a href="' . esc_url( $url ) . '" >' . __( 'Filter' ) . '</a>';

		return $actions;
	}

	function add_admin_menu_filter() {
		//add_management_page( __( 'Instafx' ), __( 'Instafx' ), $this->capability, 'instafx-page', array(&$this, 'instafx_interface_advanced') );
		add_menu_page( __( 'Instafx' ), __( 'Instafx' ), $this->capability, 'instafx-page', array(&$this, 'instafx_interface_advanced'), plugin_dir_url( __FILE__ )."images/menu-icon.png", 6 );
		remove_menu_page('instafx-page');
	}
	
	function instafx_admin_notice() {
		global $current_user ;
			$user_id = $current_user->ID; 
			/* Check that the user hasn't already clicked to ignore the message */
		if ( ! get_user_meta($user_id, 'instafx_ignore_notice',true) ) { ?>
			<div id="message" class="updated fade">
				<p><strong><?php _e("Power up your Wordpress site with InstaFX, Add filtering to your Wordpress Images. Mijesty, Sunrise, Cross, Pell, Love, Pinhole, and more."); ?> <a href="upload.php"><?php _e("Media page"); ?></a></strong><a class="close-instafx" href="?instafx_nag_ignore=0"><?php _e('Dismiss'); ?></a></p>
				<style>
					.close-instafx{
						float:right;
					}
				</style>
			</div>
		<?php	
		}
	}

	function instafx_nag_ignore() {
		global $current_user;
			$user_id = $current_user->ID;
			/* If user clicks to ignore the notice, add that to their user meta */
			if ( isset($_GET['instafx_nag_ignore']) && '0' == $_GET['instafx_nag_ignore'] ) {
				 add_user_meta($user_id, 'instafx_ignore_notice', 'true', true);
		}
}
	
	//
	function instafx_interface_advanced($img = ''){
		echo '<div class="outer-wrapper">';
		
		if(isset($_REQUEST['id']) && $_REQUEST['id']!='' ):
			$src = wp_get_attachment_image_src($_REQUEST['id'],'full');
			$src = $src[0];
			$img = '<p><img src="'.$src.'" id="preset-example" /></p>';
			$data_plugin = get_plugin_data(__FILE__); 
			?>
			
			<?php if( isset($_GET['settings-updated']) && $_GET['settings-updated']=='true' ){ ?>
					<div id="message" class="updated fade" style="">
						<p><strong><?php _e("Success"); ?>.</strong></p>
					</div>
					<?php 
				}?>
			<div class="colabs_twitter_stream">

				<div class="stream-label"><?php _e('News On Twitter:','colabsthemes');?></div>				
				
				  <?php $user_timeline = $this->instafx_get_user_timeline( 'colorlabs', 5 );  ?>
				  <?php if( isset( $user_timeline['error'] ) ) : ?>
					<p><?php echo $user_timeline['error']; ?></p>
				  <?php else : ?>
					<?php $this->instafx_build_twitter_markup( $user_timeline ); ?>
				  <?php endif; ?>
				

			</div>
    <!-- .colabs_twitter-stream -->
			<div id="instafx-container" class="wrap">

				<div class="instafx-sidebar">
					<div class="instafx-logo">				
						<h3>
							<img src="<?php echo plugin_dir_url( __FILE__ ); ?>images/logo.png" />
							<a href="http://colorlabsproject.com/plugins/instafx/" target="_blank" title="<?php echo $data_plugin['AuthorName']; ?>"><?php echo $data_plugin['Name']; ?></a> <span class="version"><?php echo $data_plugin['Version']; ?></span>
						</h3>
					</div>
					<ul class="instafx-menu">
						<?php
						sort($this->filters);
						$i=0;
						$data = '';
						foreach($this->filters as $filter => $value) : ?>
							<li>	
								<a id="item-instafx-contact-forms" caman="preset-<?php echo $value; ?>" class="preset-button menu-item">
									<img src="<?php echo plugin_dir_url( __FILE__ ); ?>images/skin-settings.png">
									<span class="menu-text"><?php echo ucfirst($value); ?></span>
									<span class="menu-arrow"></span>
									<span class="menu-hover"></span>
								</a>
							</li>			
						<?php 
						endforeach; ?>			
					</ul>			
				</div>
				
				<div id="instafx-addon" class="module-list">
					<form action="options.php" method="post">	
						<div class="settings-header-fixed">
							<h3 id="name-effect"><?php _e("View Image"); ?></h3>
							<div id="render-in-progress" class="btn btn-close" style="display:none">
								<img src="<?php echo plugin_dir_url( __FILE__ ); ?>images/ajax-loader.gif" />
							</div>
							<input type="submit" name="submit" id="submit" class="btn btn-close button-primary" value="Save Image Changes">
						</div>
						<div class="settings-inner">
							<div class="settings-scroller">						
								<div class="settings-container">			
									
									<?php 
										settings_fields('instafx_options');
										echo $img; 
									?>
									<input type="hidden" name="instafx_field[name]" id="name" class="name-instafx">
									<textarea id="data" name="instafx_field[sendimage]" style="display:none"></textarea>								
								</div>
							</div>
						</div>
						
					</form>
				</div>
				
				<script>
					jQuery('document').ready(function () {	
						jQuery("#submit").hide();				
					
						var image = Caman("#preset-example", function () {});
						
						jQuery('.preset-button').live('click', function () {
							var filter = jQuery(this).attr('caman').split('-')[1];
							jQuery('#name-effect').html(filter);
							jQuery('#name').val( "<?php echo get_the_title($_REQUEST['id']); ?>-" + filter);
							render(filter);
						});
						
						function render(filter) {
							jQuery("#submit").hide();
							jQuery("#render-in-progress").show();
							var type = 'jpeg';
							
							image.revert(function () {
								//this.toBase64(type);
								image[filter]().render(function () {
									jQuery("#render-in-progress").hide();
									jQuery("#submit").show();
									jQuery("#data").val(this.toBase64(type));
									jQuery("#preset-export").attr('src',this.toBase64(type));
								});
							});
						}
					});
				</script>
				<style> </style>
			<?php 
			$this->instafx_equeue_front();
		endif;
		echo '</div>';
		echo '<p id="colabsplugin-trademark">
				<a href="http://colorlabsproject.com/" target="_blank" title="ColorLabs &amp; Company">
				<img src="'.plugin_dir_url( __FILE__ ).'images/colorlabs.png"></a>
			  </p>';
		echo '</div>';
	}

	function instafx_attachment_image_fields_to_edit( $form_fields, $attachment ) {  
		if ( substr( $attachment->post_mime_type, 0, 5 ) == 'image' ){
			$valuefilter='';
			$datavaluefilter = get_post_meta( $attachment->ID, '_instafx_effect', true);
			
			if($datavaluefilter!='')
			foreach($datavaluefilter as $key => $valuef ) $valuefilter .= $valuef.' '; 
		
			sort($this->filters);
			$i=0;
			$data = '';
			foreach($this->filters as $filter => $value){ 
				if(strpos($valuefilter, $value) === false) 
					$check = ''; 
				else  
					$check = ' checked ';
				
				$data .= '<label for="attachments['.$attachment->ID.'][effect]['.$i.']" style="display:block; margin-top: 10px; clear:both;">
							<input style="float:left; width:auto; margin-right:5px;" type="checkbox" value="'.$value.'()" name="attachments['.$attachment->ID.'][effect][]" id="attachments['.$attachment->ID.'][effect]['.$i.']" '.$check.' /> '.ucfirst($value).
						 '</label> ';
				$i++;		 
			}
			if(get_bloginfo('version') < '3.5' )
			if( !strstr($_SERVER['REQUEST_URI'], 'wp-admin/media.php') ) 
			$data .= '<div style="clear:left;"></div>'.get_submit_button( __( 'Insert filter into Post' ), 'primary', 'insertonlybuttoninstafx['.$attachment->ID.']', false );
			
			$form_fields[ 'effect' ] = array();   
			$form_fields[ 'effect' ][ 'label'  ] = __( 'Effect' );	      
			$form_fields[ 'effect' ][ 'helps' ] = '';
			$form_fields[ 'effect' ][ 'input' ] = 'html';  
			$form_fields[ 'effect' ][ 'html' ] = $data;
		}
		return $form_fields;
	}
	
	function instafx_attachment_image_fields_to_save( $post, $attachment ) {
		if ( isset( $attachment[ 'effect' ] ) ){ 
			update_post_meta( $post[ 'ID' ], '_instafx_effect', $attachment[ 'effect' ] );  
		}else{
			update_post_meta( $post[ 'ID' ], '_instafx_effect', '' );  
		} 
		return $post;  
	}

	function wp_media_upload_handler_instafx(){
		if ( !empty($_POST['insertonlybuttoninstafx']) ) {
			if ( isset($_POST['insertonlybuttoninstafx']) ) {
				$keys = array_keys($_POST['insertonlybuttoninstafx']);
				$send_id = (int) array_shift($keys);
			}
			
			$src = $_POST['src'];
			$size = $_POST['attachments'][$send_id]['image-size'];
			
			$effects = $_POST['attachments'][$send_id]['effect'];

			foreach($effects as $effect => $value){ 
				$filter .= ' '.$value;
			}
			
			if($src=='') {
				$src = wp_get_attachment_image_src($send_id,$size);
				$src = $src[0];
			}
			
			$html = apply_filters( 'instafx_send_to_editor_url', '[instafx src="'.$src.'" effect="'.$filter.'"] [/instafx]' );
						
			return media_send_to_editor_instafx($html);
		}
	}
	
	function media_send_to_editor_instafx($html) {
		?>
		<script type="text/javascript">
		/* <![CDATA[ */
		var win = window.dialogArguments || opener || parent || top;
		win.send_to_editor('<?php echo addslashes($html); ?>');
		/* ]]> */
		</script>
		<?php
			exit;
	}
	
	function media_send_to_editor35($html, $send_id, $attachment) {
	
		$valuefilter='';
		$datavaluefilter = get_post_meta( $send_id, '_instafx_effect', true);
			
		if($datavaluefilter!='') foreach($datavaluefilter as $key => $valuef ) $valuefilter .= $valuef.' '; 
		
		if( '' != trim($valuefilter) )
		$html = str_ireplace('src=', 'data-caman="'.$valuefilter.'" src=', $html);
		
		
		return $html;
		
	}
	
	function media_send_to_editor34($html, $send_id, $attachment) {
		$attachment = get_post($send_id); 

		$mime_type = $attachment->post_mime_type; 
		if (substr($mime_type, 0, 5) == 'image') { 
				
				$effects = $_POST['attachments'][$send_id]['effect'];

				foreach($effects as $effect => $value){ 
					$filter .= ' '.$value;
				}
						
			//$html = str_ireplace('src=', 'data-caman="'.$filter.'" src=', $html);
			
		}	
			sort($this->filters);
			$i = 0;
			$url = plugin_dir_url( __FILE__ )."images/logo.png";
			$html .= '<div style="padding:5px; float:left; position:relative" class="instafx"><img src="'.$url.'" width="200" height="200" /><div style="position:absolute;top: 0px;background: #000;color:#fff;padding: 0 5px;font-weight: bold;">Original</div></div>';
			foreach($this->filters as $filter => $value){ 
				$html .= '<div style="padding:5px; float:left; position:relative" class="instafx"><img src="'.$url.'" width="200" height="200" data-caman="'.$value.'()" /><div style="position:absolute;top: 0px;background: #000;color:#fff;padding: 0 5px;font-weight: bold;">'.ucfirst($value).'</div></div>';
			}
		
		return $html;
	}
	
	function instafx_equeue() {
		wp_enqueue_script( 'instafxsrc', plugin_dir_url( __FILE__ ).'js/caman.full.min.js',array('jquery'));
		
		if(isset($_GET['page']) && $_GET['page'] == 'instafx-page'){
			wp_enqueue_style( 'instafxadminstyle', plugin_dir_url( __FILE__ ).'css/admin-style.css' );
			wp_enqueue_script( 'instafxscripts', plugin_dir_url( __FILE__ ).'js/scripts.js',array('jquery','instafxsrc'));
		}
	}
	
	function instafx_equeue_front() {
		foreach ( glob( plugin_dir_path( __FILE__ )."effects/*.js" ) as $file ) {

			if ( ! preg_match( '|Effect Name:(.*)$|mi', file_get_contents( $file ), $header ) )
				continue;

			echo '<script>
				jQuery("document").ready(function () {	';
				include_once($file);
				echo '});
			</script>';

		}		
	}    
	
	function photo_filter( $atts, $content=null ) {
		extract( shortcode_atts( array(
			'src' => '',
			'width' => '',
			'height' => '',
			'title' => '',
			'alt' => '',
			'rel' => '',
			'class' => '',
			'id' => '',
			'before' => '',
			'after' => '',
			'effect' => '',
		), $atts ) );
		if($src=='' && $content==null) return;
		
		$size='';
		$caman='';
		$img='';
		
		if($width!='') 	$size.=' width="'.$width.'" ';
		if($height!='') $size.=' height="'.$height.'" ';
		if($title!='') 	$size.=' title="'.$title.'" ';
		if($alt!='') 	$size.=' alt="'.$alt.'" ';
		if($rel!='') 	$size.=' rel="'.$rel.'" ';
		if($class!='') 	$size.=' class="'.$class.'" ';
		if($id!='') 	$size.=' height="'.$id.'" ';
		
		if($effect!='') {
			
			foreach ($this->filters as $val) {
				//if(strpos($effect,$val))
				$effect = str_ireplace($val, $val.'()', $effect);				
			}	
		
			$caman.=' data-caman="'.$effect.'" ';
			
		}	
		
		if(trim($src)!='') $img .= html_entity_decode($before).'<img src='.$src.$size.$caman.' />'.html_entity_decode($after);
				
		if($content!=null){
			$img .= str_ireplace('<img ','<img '.$caman, do_shortcode($content) );
		}
		
		if($img!='') return do_shortcode($img);	
		
	}
	
	function get_instafx_effects() {

		$instafx_effects = array();

		foreach ( glob( plugin_dir_path( __FILE__ )."effects/*.js" ) as $file ) {

			if ( ! preg_match( '|Effect Name:(.*)$|mi', file_get_contents( $file ), $header ) )
				continue;

			$instafx_effects[ $file ] = sanitize_title(_cleanup_header_comment( $header[1] ));

		}

		$this->filters = array_merge($this->filters, $instafx_effects);

	}
	
	//pointer

	function instafx_admin_pointers_header() {
		if ( $this->instafx_admin_pointers_check() ) {
			add_action( 'admin_print_footer_scripts', array(&$this,'instafx_admin_pointers_footer') );

			wp_enqueue_script( 'wp-pointer' );
			wp_enqueue_style( 'wp-pointer' );
		}
	}

	function instafx_admin_pointers_check() {
		$admin_pointers = $this->instafx_admin_pointers();
		foreach ( $admin_pointers as $pointer => $array ) {
			if ( $array['active'] )
				return true;
		}
	}

	function instafx_admin_pointers_footer() {
		$admin_pointers = $this->instafx_admin_pointers();
		?>
		<script type="text/javascript">
		/* <![CDATA[ */
		( function($) {
		   <?php
		   foreach ( $admin_pointers as $pointer => $array ) {
			  if ( $array['active'] ) {
				 ?>
				 $( '<?php echo $array['anchor_id']; ?>' ).pointer( {
					content: '<?php echo $array['content']; ?>',
					pointerWidth : 550,
					position: {
					edge: '<?php echo $array['edge']; ?>',
					align: '<?php echo $array['align']; ?>'
				 },
					close: function() {
					   $.post( ajaxurl, {
						  pointer: '<?php echo $pointer; ?>',
						  action: 'dismiss-wp-pointer'
					   } );
					}
				 } ).pointer( 'open' );
				 <?php
			  }
		   }
		   ?>
		} )(jQuery);
		/* ]]> */
		</script>
		<?php
	}

	function instafx_admin_pointers() {
		$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
		$version = '1_0'; // replace all periods in 1.0 with an underscore
		$prefix = 'instafx_admin_pointers' . $version . '_';

		$new_pointer_content = '<h3><a href="http://colorlabsproject.com/plugins/instafx/" target="_blank" style="color:#fff!important">InstaFX</a></h3>';
		$new_pointer_content .= '<p>' . __("Power up your Wordpress site with InstaFX, Add filtering to your Wordpress Images. <br><strong>Mijesty, Sunrise, Cross, Pell, Love, Pinhole, and more.</strong><br><br><a href=\"http://colorlabsproject.com/documentation/instafx-a-photo-filter-wordpress-plugin\" target=\"_blank\">View Documentation</a>").'</p>';

		return array(
			$prefix . 'new_items' => array(
			 'content' => $new_pointer_content,
			 'anchor_id' => '#menu-media',
			 'edge' => 'left',
			 'align' => 'left',
			 'active' => ( ! in_array( $prefix . 'new_items', $dismissed ) )
			),
		);
	}
	
	//
	
	//twitter

	/**
	* Linkify Twitter Text
	* 
	* @param string s Tweet
	* 
	* @return string a Tweet with the links, mentions and hashtags wrapped in <a> tags 
	*/
	function instafx_linkify_twitter_text($tweet){
		$url_regex = '/((https?|ftp|gopher|telnet|file|notes|ms-help):((\/\/)|(\\\\))+[\w\d:#@%\/\;$()~_?\+-=\\\.&]*)/';
		$tweet = preg_replace($url_regex, '<a href="$1" target="_blank">'. "$1" .'</a>', $tweet);
		$tweet = preg_replace( array(
		  '/\@([a-zA-Z0-9_]+)/', # Twitter Usernames
		  '/\#([a-zA-Z0-9_]+)/' # Hash Tags
		), array(
		  '<a href="http://twitter.com/$1" target="_blank">@$1</a>',
		  '<a href="http://twitter.com/search?q=%23$1" target="_blank">#$1</a>'
		), $tweet );
		
		return $tweet;
	}

	/**
	* Get User Timeline
	* 
	*/
	function instafx_get_user_timeline( $username = '', $limit = 5 ) {
		$key = "twitter_user_timeline_{$username}_{$limit}";

		// Check if cache exists
		$timeline = get_transient( $key );
		if ($timeline !== false) {
		  return $timeline;
		} else {
		  $headers = array( 'Authorization' => 'Bearer ' . $this->instafx_get_access_token() );
		  $response = wp_remote_get("https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name={$username}&count={$limit}", array( 
			'headers' => $headers, 
			'timeout' => 40,
			'sslverify' => false 
		  ));
		  if ( is_wp_error($response) ) {
			// In case Twitter is down we return error
			dbgx_trace_var($response);
			return array('error' => __('There is problem fetching twitter timeline', 'colabsthemes'));
		  } else {
			// If everything's okay, parse the body and json_decode it
			$json = json_decode(wp_remote_retrieve_body($response));

			// Check for error
			if( !count( $json ) ) {
			  return array('error' => __('There is problem fetching twitter timeline', 'colabsthemes'));
			} elseif( isset( $json->errors ) ) {
			  return array('error' => $json->errors[0]->message);
			} else {
			  set_transient( $key, $json, 60 * 60 );
			  return $json;
			}
		  }
		}
	}

	/**
	* Get Twitter application-only access token
	* @return string Access token
	*/
	function instafx_get_access_token() {
		$consumer_key = urlencode( $this->consumer_key );
		$consumer_secret = urlencode( $this->consumer_secret );
		$bearer_token = base64_encode( $consumer_key . ':' . $consumer_secret );

		$oauth_url = 'https://api.twitter.com/oauth2/token';

		$headers = array( 'Authorization' => 'Basic ' . $bearer_token );
		$body = array( 'grant_type' => 'client_credentials' );

		$response = wp_remote_post( $oauth_url, array(
		  'headers' => $headers,
		  'body' => $body,
		  'timeout' => 40,
		  'sslverify' => false
		) );

		if( !is_wp_error( $response ) ) {
		  $response_json = json_decode( $response['body'] );
		  return $response_json->access_token;
		} else {
		  return false;
		}
	}

	/**
	* Builder Twitter timeline HTML markup
	*/
	function instafx_build_twitter_markup( $timelines ) { ?>
		<ul class="tweets">
		<?php foreach( $timelines as $item ) : ?>
		  <?php 
			$screen_name = $item->user->screen_name;
			$profile_link = "http://twitter.com/{$screen_name}";
			$status_url = "http://twitter.com/{$screen_name}/status/{$item->id}";
		  ?>
		  <li>
			<span class="content">
			  <?php echo $this->instafx_linkify_twitter_text( $item->text ); ?>
			  <a href="<?php echo $status_url; ?>" style="font-size:85%" class="time" target="_blank">
				<?php echo date('M j, Y', strtotime($item->created_at)); ?>
			  </a>
			</span>
		  </li>
		<?php endforeach; ?>
		</ul>
		<?php 
	}

}

$colabs_instafx = new Colabs_Photofilter();
