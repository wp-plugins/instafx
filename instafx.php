<?php
/*
Plugin Name: InstaFX
Plugin URI: http://colorlabsproject.com
Description: Power up your WordPress site with InstaFX, Add filtering to your WordPress images.
Version: 1.1.1
Author: ColorLabs & Company
Author URI: htttp://www.colorlabsproject.com
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if ( ! defined( 'ABSPATH' ) )
	die( __("Can't load this file directly") );	

class Colabs_Photofilter
{
	function __construct() {
		add_action( 'init', array( &$this, 'instafx_init' ) );
	}
	
	function instafx_init(){
	
		add_shortcode( 'instafx', array( &$this, 'photo_filter') );
		add_action('wp_print_scripts', array( &$this, 'instafx_equeue') );		
		
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
		
	function instafx_attachment_image_fields_to_edit( $form_fields, $attachment ) {  
		if ( substr( $attachment->post_mime_type, 0, 5 ) == 'image' ){
			$valuefilter='';
			$datavaluefilter = get_post_meta( $attachment->ID, '_instafx_effect', true);
			
			if($datavaluefilter!='')
			foreach($datavaluefilter as $key => $valuef ) $valuefilter .= $valuef.' '; 
		
			$filters = array( 'majesty', 'sunrise', 'cross', 'peel', 'love', 'pinhole', 'glowing', 'hazy', 'nostalgia', 'hemingway', 'boot');
			sort($filters);
			$i=0;
			$data = '';
			foreach($filters as $filter => $value){ 
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
			$filters = array( 'majesty', 'sunrise', 'cross', 'peel', 'love', 'pinhole', 'glowing', 'hazy', 'nostalgia', 'hemingway', 'boot');
			sort($filters);
			$i=0;
			$url='http://demo.colorlabsproject.com/12.png';
			$html .='<div style="padding:5px; float:left; position:relative" class="instafx"><img src="'.$url.'" width="200" height="200" /><div style="position:absolute;top: 0px;background: #000;color:#fff;padding: 0 5px;font-weight: bold;">Original</div></div>';
			foreach($filters as $filter => $value){ 
				$html .='<div style="padding:5px; float:left; position:relative" class="instafx"><img src="'.$url.'" width="200" height="200" data-caman="'.$value.'()" /><div style="position:absolute;top: 0px;background: #000;color:#fff;padding: 0 5px;font-weight: bold;">'.ucfirst($value).'</div></div>';
			}
		
		return $html;
	}
	
	function instafx_equeue() {
		wp_enqueue_script( 'instafxsrc', plugin_dir_url( __FILE__ ).'caman.full.min.js',array('jquery'));
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
		
		if($effect!='') $caman.=' data-caman="'.$effect.'" ';
		
		if(trim($src)!='') $img .= html_entity_decode($before).'<img src='.$src.$size.$caman.' />'.html_entity_decode($after);
				
		if($content!=null){
			$img .= str_ireplace('<img ','<img '.$caman, $content );
		}
		
		if($img!='') return do_shortcode($img);	
		
	}
}

$colabs_instafx = new Colabs_Photofilter();