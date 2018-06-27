<?php
/*
Plugin Name: PB Formats
Plugin URI: https://github.com/PixelBoom/PB-Formats/
Description: Create custom meta boxes that match the post format used in PixelBoom's WordPress themes.
Author: PixelBoom
Version: 1.0
Author URI: http://www.pixelboom.net/
*/

// If this file is called directly, bail.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

// The PB_Formats classes.
if( ! class_exists('PB_Formats') ) {
	class PB_Formats {
		/**
		 * Constructor.
		 *
		 * @since 1.0
		 * @access public
		 */
		public function __construct() {
			// Translation.
			load_plugin_textdomain( 'pb-formats', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

			// Enqueue scripts & style.
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts') );

			// Adds & save the meta boxes.
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
			add_action( 'save_post',      array( $this, 'save_post' ) );

			// Ajax callback to support the gallery field.
			add_action( 'wp_ajax_pb_formats_ajax', array( $this, 'ajax_callback' ) );
		}

		/**
		 * Enqueue scripts & styles.
		 *
		 * @since 1.0
		 * @access public
		 */
		public function admin_enqueue_scripts() {
			// CSS
			wp_enqueue_style( 'pb-formats-admin-style', plugin_dir_url( __FILE__ ) . 'assets/admin.css', array(), false );

			// JS
			wp_enqueue_media();
			wp_enqueue_script( 'pb-formats-admin-script', plugin_dir_url( __FILE__ ) . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable' ), false, true );
			$l10n = array(
				  'ajax_url'     => admin_url( 'admin-ajax.php' )
				, 'nonce'        => wp_create_nonce( '_pb_formats_nonce' )
				, 'createText'   => esc_html__( 'Create Featured Gallery', 'pb-formats' )
				, 'editText'     => esc_html__( 'Edit Featured Gallery', 'pb-formats' )
				, 'saveText'     => esc_html__( 'Save Featured Gallery', 'pb-formats' )
				, 'savingText'   => esc_html__( 'Saving...', 'pb-formats' )
			);
			wp_localize_script( 'pb-formats-admin-script', 'PB_Formats_Localize', $l10n );
		}

		/**
		 * Save meta boxes.
		 *
		 * @since 1.0
		 * @access public
		 */
		public function save_post($post_id)
		{
			// Verify that the nonce is valid.
			$nonce = isset( $_POST['_pb_formats_nonce'] ) ? (string) $_POST['_pb_formats_nonce'] : '';
			if( ! wp_verify_nonce( $nonce, '_pb_formats_nonce' ) ) {
				return $post_id;
			}

			// If this is an autosave, our form has not been submitted,
			// so we don't want to do anything.
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return $post_id;
			}

			// Check the user's permissions.
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $post_id;
			}

			// Sanitize the user input.
			$_args   = $this->args();
			$_update = array();
			foreach( $_args as $args ) {
				if( isset( $_POST[ $args['field'] ] ) ) {
					if( 'video' == $args['id'] || 'audio' == $args['id'] ) {
						if( current_user_can( 'unfiltered_html' ) ) {
							$_update[ $args['id'] ] = $_POST[ $args['field'] ];
						}else {
							$_update[ $args['id'] ] = wp_kses_post( $_POST[ $args['field'] ] );
						}
						
					}
					elseif( 'link' == $args['id'] || 'quote_url' == $args['id'] ) {
						$_update[ $args['id'] ] = sanitize_url( $_POST[ $args['field'] ] );
					}
					else {
						$_update[ $args['id'] ] = sanitize_text_field( $_POST[ $args['field'] ] );
					}
				}
			}

			// Update the meta field.
			update_post_meta( $post_id, '_pb_formats_args', $_update );

		}

		/**
		 * Ajax callback.
		 *
		 * @since 1.0
		 * @access public
		 */
		public function ajax_callback($post)
		{
			// Verify that the nonce is valid.
			if( ! isset($_POST['nonce']) || ! isset( $_POST['ids']) ) {
				return;
			}
			if( ! wp_verify_nonce( $_POST['nonce'], '_pb_formats_nonce' ) ) {
				return;
			}

			// If this is an autosave, our form has not been submitted,
			// so we don't want to do anything.
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			// Check the user's permissions.
			if ( ! current_user_can( 'edit_posts' ) ) {
				return;
			}

			// Sanitize the user input.
			$image_ids  = sanitize_text_field( $_POST['ids'] );
			$image_ids  = rtrim($image_ids, ',');

			$image_args = explode(',', $image_ids);
			$output     = '';

			// Output the meta field.
			foreach( $image_args as $img ) {
				$output .= '<li>'. wp_get_attachment_image( $img, array(64, 64) ) .'</li>';
			}
			echo $output;

			die();
		}

		/**
		 * Adds meta boxes.
		 *
		 * @since 1.0
		 * @access public
		 */
		public function add_meta_boxes()
		{
			
			$page_supports = $this->page_supports();
			add_meta_box( 'pb-formatsdiv', esc_html__( 'Post Format Settings', 'pb-formats' ), array( $this, 'callback_boxes' ), $page_supports, 'normal', 'high' );
		}

		/**
		 * Callback render content.
		 *
		 * @since 1.0
		 * @access public
		 */
		public function callback_boxes( $post )
		{
			$post_id    = $post->ID;
			$_args      = get_post_meta( $post_id, '_pb_formats_args', true );
			$_args      = wp_parse_args( $_args, array( 'gallery' => '', 'video' => '', 'audio' => '', 'link' => '', 'quote_url' => '', 'quote_name' => '' ) );
			$image_ids  = $_args['gallery'];
			?>

			<div class="clear"></div>
			<div id="pb-formats-wrapper">
				<div id="pb_formats_box_gallery" class="pb-formats-box">
					<?php do_action( 'pb_formats_before_gallery_box' ); ?>

					<div class="pb-formats-box-inner">
						<label for="pb_formats_gallery_upload"><?php esc_html_e( '&mdash; Gallery Images', 'pb-formats' ); ?></label>
						<div class="pb-formats-box-block">
							<input type="hidden" name="_pb_formats_gallery" id="_pb_formats_gallery" value="<?php echo esc_attr( $image_ids ); ?>" />
							<button type="button" id="pb_formats_gallery_upload" class="button"><?php if( $image_ids ) { esc_html_e( 'Edit Gallery', 'pb-formats' ); } else { esc_html_e( 'Upload Images', 'pb-formats' ); } ?></button>
							<p class="description"><?php esc_html_e( 'Edit the gallery by clicking to upload or edit the gallery.', 'pb-formats' ); ?></p>
							<ul id="pb_formats_gallery_input">
								<?php
									if( $image_ids ) {
										$image_args = explode(',', $image_ids);
										$image_output = '';
										foreach( $image_args as $img ) {
											$image_output .= '<li>'. wp_get_attachment_image( $img, array(64, 64) ) .'</li>';
										}
										echo $image_output;
									}
								?>

							</ul>
						</div>
					</div>
					<?php do_action( 'pb_formats_after_gallery_box' ); ?>

				</div>
				<!-- #pb_formats_box_gallery -->
				<div class="clear"></div>

				<div id="pb_formats_box_video" class="pb-formats-box">
					<?php do_action( 'pb_formats_before_video_box' ); ?>

					<div class="pb-formats-box-inner">
						<label for="_pb_formats_video"><?php esc_html_e( ' &mdash; Enter your Video URL (oEmbed) or Embed Code', 'pb-formats' ); ?></label>
						<textarea name="_pb_formats_video" id="_pb_formats_video" autocomplete="off" rows="5"><?php echo esc_textarea($_args['video']); ?></textarea>
					</div>
					<?php do_action( 'pb_formats_after_video_box' ); ?>

				</div>
				<!-- #pb_formats_box_video -->
				<div class="clear"></div>

				<div id="pb_formats_box_audio" class="pb-formats-box">
					<?php do_action( 'pb_formats_before_audio_box' ); ?>

					<div class="pb-formats-box-inner">
						<label for="_pb_formats_audio"><?php esc_html_e( ' &mdash; Enter your Audio URL (oEmbed) or Embed Code', 'pb-formats' ); ?></label>
						<textarea name="_pb_formats_audio" id="_pb_formats_audio" autocomplete="off" rows="5"><?php echo esc_textarea( $_args['audio'] ); ?></textarea>
					</div>
					<?php do_action( 'pb_formats_after_audio_box' ); ?>

				</div>
				<!-- #pb_formats_box_audio -->
				<div class="clear"></div>

				<div id="pb_formats_box_quote" class="pb-formats-box">
					<?php do_action( 'pb_formats_before_quote_box' ); ?>

					<div class="pb-formats-box-inner">
						<div class="pb-formats-box-block">
							<label for="_pb_formats_quote_name"><?php esc_html_e( ' &mdash; Source Name', 'pb-formats' ); ?></label>
							<input type="text" name="_pb_formats_quote_name" id="_pb_formats_quote_name" value="<?php echo esc_attr( $_args['quote_name'] ); ?>" />
						</div>
						<div class="pb-formats-box-block">
							<label for="_pb_formats_quote_url"><?php esc_html_e( ' &mdash; Source URL', 'pb-formats' ); ?></label>
							<input type="text" name="_pb_formats_quote_url" id="_pb_formats_quote_url" value="<?php echo esc_url( $_args['quote_url'] ); ?>" />
						</div>
					</div>
					<?php do_action( 'pb_formats_after_quote_box' ); ?>

				</div>
				<!-- #pb_formats_box_quote -->
				<div class="clear"></div>

				<div id="pb_formats_box_link" class="pb-formats-box">
					<?php do_action( 'pb_formats_before_link_box' ); ?>

					<div class="pb-formats-box-inner">
						<label for="_pb_formats_link"><?php esc_html_e( ' &mdash; Full URL', 'pb-formats' ); ?></label>
						<input type="text" name="_pb_formats_link" id="_pb_formats_link" value="<?php echo esc_url( $_args['link'] ); ?>" />
					</div>
					<?php do_action( 'pb_formats_after_link_box' ); ?>

				</div>
				<!-- #pb-formats-link -->
				<div class="clear"></div>
			</div>
			<!-- #pb-formats-wrapper -->
			<div class="clear"></div>
			<?php
			wp_nonce_field( '_pb_formats_nonce', '_pb_formats_nonce', false, true );
		}

		/**
		 * Custom post type support post formats.
		 *
		 * @since 1.0
		 * @access public
		 */
		public function page_supports()
		{
			$args = array('post');
			return apply_filters( 'pb_formats_post_type_supports', $args );
		}

		/**
		 * Set default args.
		 *
		 * @since 1.0
		 * @access public
		 */
		public function args()
		{
			$args = array(
				array(
					  'id'    => 'gallery'
					, 'field' => '_pb_formats_gallery'
				),
				array(
					  'id'    => 'video'
					, 'field' => '_pb_formats_video'
				),
				array(
					  'id'    => 'audio'
					, 'field' => '_pb_formats_audio'
				),
				array(
					  'id'    => 'link'
					, 'field' => '_pb_formats_link'
				),
				array(
					  'id'    => 'quote_url'
					, 'field' => '_pb_formats_quote_url'
				),
				array(
					  'id'    => 'quote_name'
					, 'field' => '_pb_formats_quote_name'
				)
			);

			return $args;
		}

		// End Classes.
	}
}

// If the theme you are using does not support post formats.
new PB_Formats();


/**
 * Retrieve meta value.
 *
 * @since 1.0
 */
function pb_formats_plugin_get_meta( $post_id, $key = '')
{
	$args = get_post_meta( $post_id, '_pb_formats_args', true );
	$args = wp_parse_args( $args, array( 'gallery' => '', 'video' => '', 'link' => '', 'quote_url' => '', 'quote_name' => '' ) );

	if( isset( $args[$key] ) ) {
		return $args[$key];
	}

	return false;
}