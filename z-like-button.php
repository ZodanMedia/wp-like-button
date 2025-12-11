<?php
/**
 * Plugin Name: Z Like Button
 * Contributors: zodannl, martenmoolenaar
 * Plugin URI: https://plugins.zodan.nl/wordpress-like-button/
 * Tags: like, button, like, like button, custom like
 * Requires at least: 5.5
 * Tested up to: 6.9
 * Description: Displays a simple and customisable like-button for all types of posts.
 * Version: 0.0.6
 * Stable Tag: 0.0.6
 * Author: Zodan
 * Author URI: https://zodan.nl
 * Text Domain: z-like-button
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 */

if ( ! class_exists( 'zLikeButton' ) ) :

    class zLikeButton {
        private $options;
        private $version_number = '0.0.6';

        protected static $instance = null;

        public function __construct(){
            add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts_like_button'));
            add_action('wp_enqueue_scripts', array($this, 'like_button_scripts'));
            add_action('add_meta_boxes', array($this, 'like_button_boxes'));
            add_action('admin_enqueue_scripts', array($this, 'like_button_css'), 11, 1 );

            /**
             * Ajax functions for front-end
             */
            add_action('wp_ajax_like_button', array($this, 'handle_ajax'));
            add_action('wp_ajax_nopriv_like_button', array($this, 'handle_ajax'));

            add_action('wp_ajax_like_button_remove', array($this, 'handle_ajax_removal'));
            add_action('wp_ajax_nopriv_like_button_remove', array($this, 'handle_ajax_removal'));


            /**
             * Shortcode for inserting the button
             */
            add_shortcode('z_like_button',array($this, 'shortcode_like_button'));
            add_shortcode('zlikebutton_likes_list',array($this, 'shortcode_my_likes_list'));

            /**
             * Save post preferences on save
             */
            add_action( 'save_post', array($this, 'save_like_button'));

            /**
             * Admin functions
             */
            if ( is_admin() ){
                add_action( 'admin_menu', array($this, 'like_button_add_admin_menu') );
                add_action( 'admin_init', array($this, 'like_button_settings_init') );
            }

            /**
             * Add shortcodes to content filter
             */
            add_filter( 'the_content', array($this, 'like_button_filter_the_content_for_button') );
            add_filter( 'the_content', array($this, 'like_button_filter_the_content_for_my_list') );

        }

        // All settings
        public function like_button_settings_init() {

            $settings_args = array(
                'type' => 'array',
                'description' => '',
                // 'sanitize_callback' => 'zlikebutton_plugin_options_validate',
                'sanitize_callback' => array($this, 'zlikebutton_plugin_options_validate'),
                'show_in_rest' => false
            );

            register_setting( 'like_button_group', 'zlikebutton_options', $settings_args );

            add_settings_section(
                'zlikebutton_options_page_section',
                esc_html__('Plugin settings', 'z-like-button'),
                array($this, 'zlikebutton_options_page_settings_section_callback'),
                'like_button_settings'
            );

            add_settings_field(
                'zlikebutton_options_field_show',
                esc_html__('Button location', 'z-like-button'),
                array($this, 'zlikebutton_options_field_show_render'),
                'like_button_settings',
                'zlikebutton_options_page_section'
            );

            add_settings_field(
                'zlikebutton_options_field_show_on',
                esc_html__('Display like button on the following post types', 'z-like-button'),
                array($this, 'zlikebutton_options_field_show_on_render'),
                'like_button_settings',
                'zlikebutton_options_page_section'
            );

            add_settings_field(
                'zlikebutton_options_hide_counter',
                esc_html__('Hide the counter display', 'z-like-button'),
                array($this, 'zlikebutton_options_hide_counter_render'),
                'like_button_settings',
                'zlikebutton_options_page_section'
            );

            add_settings_field(
                'zlikebutton_options_show_logged_out',
                esc_html__('Show to visitors', 'z-like-button'),
                array($this, 'zlikebutton_options_show_logged_out_render'),
                'like_button_settings',
                'zlikebutton_options_page_section'
            );



            

            add_settings_field(
                'zlikebutton_options_icon',
                esc_html__('Icon to display', 'z-like-button'),
                array($this, 'zlikebutton_options_field_icon_render'),
                'like_button_settings',
                'zlikebutton_options_page_section'
            );

            add_settings_field(
                'zlikebutton_options_colors',
                esc_html__('Icon colors', 'z-like-button'),
                array($this, 'zlikebutton_options_field_colors_render'),
                'like_button_settings',
                'zlikebutton_options_page_section'
            );

            add_settings_field(
                'zlikebutton_options_my_likes_page',
                esc_html__('My likes page', 'z-like-button') . '<span class="description">' . esc_html__('Which page will have an overview of posts the user liked', 'z-like-button') . '</span>',
                array($this, 'zlikebutton_options_field_my_likes_page_render'),
                'like_button_settings',
                'zlikebutton_options_page_section'
            );

            // add_settings_field(
            //     'zlikebutton_options_active_dashicons',
            //     esc_html__('Disable plugin’s default dashicon', 'z-like-button'),
            //     array($this, 'zlikebutton_options_field_active_dashicons_render'),
            //     'like_button_settings',
            //     'zlikebutton_options_page_section'
            // );
        }

        public function zlikebutton_options_page_settings_section_callback() {
            echo '<p>';
            printf(
                /* translators: %s is a shortcode used by the plugin */
                esc_html__( 'The Like Button can be added anywhere using the shortcode %s', 'z-like-button' ),
                '<code>[z_like_button]</code>'
            );  
            echo '.</p><p>';
            esc_html_e( 'Or automatically before and/or after the content using the "button location" settings', 'z-like-button' );
            echo '.</p>';

        }

        public function zlikebutton_options_field_show_render() {
            $options = get_option( 'zlikebutton_options' );

            $checked = "";
            if (isset($options["show_like_button_before"]) && $options["show_like_button_before"] == true) {
                $checked = " checked='checked' ";
            }
            echo '<label for="show_like_button_before"><input id="show_like_button_before" type="checkbox" name="zlikebutton_options[show_like_button_before]" value="1" '.esc_attr( $checked ).' />'.esc_html__('Before the content', 'z-like-button').'</label><br>';

            $checked = "";
            if (isset($options["show_like_button_after"]) && $options["show_like_button_after"] == true) {
                $checked = " checked='checked' ";
            }
            echo '<label for="show_like_button_after"><input id="show_like_button_after" type="checkbox" name="zlikebutton_options[show_like_button_after]" value="1" '.esc_attr( $checked ).' />'.esc_html__('After the content', 'z-like-button').'</label><br>';

            if ((!isset($options["show_like_button_before"]) || $options["show_like_button_before"] != true) && (!isset($options["show_like_button_after"]) || $options["show_like_button_after"] != true) ) {
                echo '<p class="alert alert-warning"><span class="dashicons dashicons-warning"></span> ';
                printf(
                    /* translators: %s is a shortcode used by the plugin */
                    esc_html__( 'If nothing is checked, the button will not show unless you manually add the %s shortcode somewhere in your content.', 'z-like-button' ),
                    '<code>[z_like_button]</code>'
                );
                echo '</p>';
            }
        }

        public function zlikebutton_options_field_show_on_render() {
            $options = get_option( 'zlikebutton_options' );

            $args = array(
                'public'   => true
            );
            $post_types = get_post_types( $args );
            unset($post_types['attachment']);

            foreach ($post_types as $key => $value) {
                $checked = " ";
                if (isset($options["cpt_".$key]) && $options["cpt_".$key] == true) {
                    $checked = " checked='checked' ";
                }
                echo '<label for="cpt_'.esc_attr( $key ).'"><input id="cpt_'.esc_attr( $key ).'" type="checkbox" name="zlikebutton_options[cpt_'.esc_attr( $key ).']" value="1" '.esc_attr( $checked ).' />'.esc_html( $value ).'<br></label>';
            }
        }

        public function zlikebutton_options_show_logged_out_render() {
            $options = get_option( 'zlikebutton_options' );

            $checked = "";
            if (isset($options["show_to_visitors"]) && $options["show_to_visitors"] == true) {
                $checked = " checked='checked' ";
            }
            echo '<input id="show_to_visitors" type="checkbox" name="zlikebutton_options[show_to_visitors]" value="1" '.esc_attr( $checked ).' /><label for="show_to_visitors">' . esc_html__('Check this to also present the Like Button to visitors (users that are not logged in).', 'z-like-button') . '<br>' . esc_html__('This will registers likes based on ip-address, which is not very precise and may result in odd behaviour).', 'z-like-button') . '</label>';
        }

        public function zlikebutton_options_hide_counter_render() {
            $options = get_option( 'zlikebutton_options' );

            $checked = "";
            if (isset($options["hide_counter_box"]) && $options["hide_counter_box"] == true) {
                $checked = " checked='checked' ";
            }
            echo '<input id="like_hide_counter_box" type="checkbox" name="zlikebutton_options[hide_counter_box]" value="1" '.esc_attr( $checked ).' /><label for="like_hide_counter_box">' . esc_html__('Check this to hide the like counter box', 'z-like-button') . '</label>';
        }

        public function zlikebutton_options_field_icon_render() {
            $options = get_option( 'zlikebutton_options' );

            $current_icon = (isset($options['icon'])) ? $options['icon'] : 'icon-heart';

            $all_icons = $this->getAllIcons();

            foreach( $all_icons as $key => $value ) {
                $checked = "";
                if( $value == $current_icon ) {
                    $checked = ' checked="checked"';
                }
                echo '<label><input type="radio" name="zlikebutton_options[icon]" value="'. esc_attr( $value ) .'"'.esc_attr( $checked ).'><i class="zlb-icon '. esc_attr( $value ) .'"></i></label>';

           }
        }

        public function zlikebutton_options_field_colors_render() {
            $options = get_option( 'zlikebutton_options' );

            $current_color_inactive = (isset($options['color_inactive'])) ? $options['color_inactive'] : '#989898';
            $current_color_active = (isset($options['color_active'])) ? $options['color_active'] : '#ef1d5f';

            echo '<label for="zlikebutton-inactive" class="color-label-faux"><span class="label-text">'. esc_html__( 'Inactive', 'z-like-button' ) .'</span></label>';
            echo '<input id="zlikebutton-inactive" class="zlikebutton-color-field" type="text" name="zlikebutton_options[color_inactive]" value="'. esc_attr( $current_color_inactive ) .'">';
            echo '<label for="id="zlikebutton-active" class="color-label-faux"><span class="label-text">'. esc_html__( 'Active', 'z-like-button' ) .'</span></label>';
            echo '<input id="zlikebutton-active" class="zlikebutton-color-field" type="text" name="zlikebutton_options[color_active]" value="'. esc_attr( $current_color_active ) .'">';

        }

        public function zlikebutton_options_field_my_likes_page_render() {
            $options = get_option( 'zlikebutton_options' );

            $current_page = (isset($options['my_likes_page'])) ? $options['my_likes_page'] : 0;


            // 1. Create select with existing page options
			$args = array(
                'numberposts' => -1,
                'post_type'   => 'page'
            );
            $all_pages = get_posts( $args );

            echo '<select name=zlikebutton_options[my_likes_page]>';
            echo '<option value="0">- '. esc_html__( 'No page', 'z-like-button' ) .' -</option>';
			foreach ( $all_pages as $post ) {
                $selected = "";
                if( $post->ID == $current_page ) {
                    $selected = ' selected="selected"';
                }
				echo '<option value="' . esc_attr( $post->ID ) .'"'.esc_attr( $selected ).'>'. esc_html( $post->post_title ) .'</option>';
			}
            echo '</select>';

            // 2. Create link to add new page
            echo ' <span> '. esc_html__( 'or', 'z-like-button' );
            echo ' <a href="'. esc_url( admin_url( 'post-new.php?post_type=page' ) ) .'" target="_blank">'. esc_html__('add a new page', 'z-like-button') .'</a></span>';

            echo '<br>';
            echo '<p class="alert alert-info"><span class="dashicons dashicons-info"></span> ';
            esc_html_e( 'The list of likes is automatically added to the content of the selected page.', 'z-like-button' );
            echo '<br>';
            printf(
                /* translators: %s is a shortcode used by the plugin */
                esc_html__('If "No page" is selected, you can add this list yourself anywhere using the %s shortcode.', 'z-like-button' ),
                '<code>[zlikebutton_likes_list]</code>'
            );
            echo '</p>';

        }


        // Validate settings on save
        public function zlikebutton_plugin_options_validate( $input ) {
            $output = array();

            if( ! empty( $input ) ) {

                foreach( $input as $key => $value ) {

                    if( $key === 'icon' ) {
                        // the icon must be in the icon range
                        $icons = $this->getAllIcons();
                        if ( in_array( $value, $icons ) ) {
                            $output['icon'] = sanitize_text_field( $input['icon'] );
                        } else {
                            $output['icon'] = 'icon-heart';
                        }

                    } elseif( $key === 'color_inactive' ) {
                        $output['color_inactive'] = sanitize_text_field( $input['color_inactive'] );

                    } elseif( $key === 'color_active' ) {
                        $output['color_active'] = sanitize_text_field( $input['color_active'] );

                    } else {
                        // all other values must be boolean
                        $output[$key] = intval( $input[$key] );

                    }
                }
            } else {
                // return empty array
                $output = array('icon' => 'icon-notifications');
            }

            return $output;

        }

        // Add option page to admin
        public function like_button_add_admin_menu() {
            add_options_page(esc_html__('Configuration like me', 'z-like-button'), esc_html__('Like Button', 'z-like-button'), 'manage_options', 'zlikebutton_options', array($this, 'zlikebutton_options_page') );
        }
        public function zlikebutton_options_page() {
            add_filter('admin_footer_text', array($this, 'z_admin_footer_print_thankyou'), 900);

            ?>
            <div class="wrap like-button-options">
                <h1 class="zlb-title"><?php esc_html_e("Z Like Button Settings", 'z-like-button'); ?></h1>
                <p class="intro"><?php esc_html_e('A simple Like Button to display on any post (page, custom post type)', 'z-like-button'); ?></p>
                <form method="post" action="options.php">
                 <?php
                    settings_fields( 'like_button_group' );
                    do_settings_sections( 'like_button_settings' );
                    submit_button();
                ?>
            
                </form>
            </div><?php

        }


        // Print a thankyou notice
        public function z_admin_footer_print_thankyou( $data ) {
            $data = '<p class="zThanks"><a href="https://zodan.nl" target="_blank" rel="noreferrer">' .
                        esc_html__('Made with', 'z-like-button') . 
                        '<svg id="heart" data-name="heart" xmlns="http://www.w3.org/2000/svg" width="745.2" height="657.6" version="1.1" viewBox="0 0 745.2 657.6"><path class="heart" d="M372,655.6c-2.8,0-5.5-1.3-7.2-3.6-.7-.9-71.9-95.4-159.9-157.6-11.7-8.3-23.8-16.3-36.5-24.8-60.7-40.5-123.6-82.3-152-151.2C0,278.9-1.4,217.6,12.6,158.6,28,93.5,59,44.6,97.8,24.5,125.3,10.2,158.1,2.4,190.2,2.4s.3,0,.4,0c34.7,0,66.5,9,92.2,25.8,22.4,14.6,70.3,78,89.2,103.7,18.9-25.7,66.8-89,89.2-103.7,25.7-16.8,57.6-25.7,92.2-25.8,32.3-.1,65.2,7.8,92.8,22.1h0c38.7,20.1,69.8,69,85.2,134.1,14,59.1,12.5,120.3-3.8,159.8-28.5,69-91.3,110.8-152,151.2-12.8,8.5-24.8,16.5-36.5,24.8-88.1,62.1-159.2,156.6-159.9,157.6-1.7,2.3-4.4,3.6-7.2,3.6Z"></path></svg>' .
                        esc_html__('by Zodan', 'z-like-button') .
                    '</a></p>';

            return $data;
        }

        // Enqueue scripts (front end)
        public function wp_enqueue_scripts_like_button() {
            $options = get_option( 'zlikebutton_options' );

            // if (!isset($options["show_like_active_dashicons"]) || $options["show_like_active_dashicons"] != true) {
            //     wp_enqueue_style( 'dashicons' );
            // }

            if (!isset($options["show_like_active_css"]) || $options["show_like_active_css"] != true) {
                $plugins_url = plugin_dir_url( __FILE__ );
                wp_enqueue_style( 'zlikebutton-css', $plugins_url . 'assets/z-like-button.css', null, $this->version_number );
            }
        }
        // Localize scripts (front-end)
        public function like_button_scripts() {
            $plugins_url = plugin_dir_url( __FILE__ );

            wp_register_script( 'z-like-button', $plugins_url . 'assets/z-like-button.js', array( 'jquery' ), array('jquery'), $this->version_number, array( 'in_footer' => true ) );
            wp_enqueue_script('z-like-button');

            wp_localize_script('z-like-button', 'z_like_button', array(
                    'url'   => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('z-like-button'),
                )
            );
        }

        // Enqueue scripts (admin)
        public function like_button_css($hook) {

            global $current_screen, $typenow;

            $plugins_url = plugin_dir_url( __FILE__ );
            wp_enqueue_style( 'zlikebutton-admin-css', $plugins_url . 'assets/admin-styles.css' , array( 'wp-color-picker' ), $this->version_number );

            if((isset($current_screen) && isset($current_screen->base) && ($current_screen->base == 'settings_page_zlikebutton_options' || $current_screen->base == '' )) || ( $hook == 'post-new.php' || $hook == 'post.php' ) ){

                wp_register_script( 'zlikebutton-admin', $plugins_url . 'assets/admin-scripts.js', array('jquery', 'wp-color-picker'), $this->version_number, array( 'in_footer' => true ) );
                wp_enqueue_script( 'zlikebutton-admin' );

                wp_localize_script('zlikebutton-admin', 'zlikebutton_admin', array(
                        'copiedText' => esc_html__('Shortcode copied!', 'z-like-button')
                    )
                );
            }
        }

        // Settings per post
        public function like_button_boxes() {
            $post_types = $this->get_all_cpt();

            if(isset($post_types) && count($post_types) > 0) {
                add_meta_box( 'meta-box-zlikebutton', '<span>' . esc_html__( 'Z Like Button', 'z-like-button' ) . '  <span class="zlb-icon icon-heart"></span></span> ', array($this, 'like_button_metabox'), $post_types, 'side' );
            }
        }
        public function like_button_metabox($post) {
            $total_likes = get_post_meta( $post->ID, 'zlikebutton_totals', true );

            if(empty($total_likes) || !is_numeric($total_likes)) {
                $total_likes = 0;
                add_post_meta($post->ID, 'zlikebutton_totals', $total_likes, true);
                update_post_meta($post->ID, 'zlikebutton_totals', $total_likes);
            }

            wp_nonce_field( 'zlikebutton_nonce', 'zlikebutton_metabox_nonce');

            echo '<p>' . esc_html__('Current count:', 'z-like-button') . ' <span class="badge badge-info">' . esc_attr( $total_likes ) . '</span></p>';

            // Hide like button for this post
            $like_hide = get_post_meta( $post->ID, 'zlikebutton_hide', true );
            if(empty($like_hide) || !is_numeric($like_hide)) {
                add_post_meta($post->ID, 'zlikebutton_hide', 0, true);
                update_post_meta($post->ID, 'zlikebutton_hide', 0);
            }
            if (isset($like_hide) && $like_hide == 1) {
                $checked = " checked='checked' ";
            } else {
                $checked = "";
            }

            echo '<p><input id="z-hide-like-button" name="zlikebutton_hide" type="checkbox" value="1" '.esc_attr( $checked ).' /> <label for="z-hide-like-button">'.esc_html__('Hide like button for this post', 'z-like-button').'</label></p>';

            echo '<p><input id="zlikebutton-manual-update" name="zlikebutton-manual-update" type="checkbox" value="1" /> <label for="zlikebutton-manual-update">'.esc_html__('Manual update', 'z-like-button').'</label></p>';

            echo '<div id="zlikebutton-update-wrapper"><p><label for="zlikebutton-manual-update-likes">' . esc_html__('New total:', 'z-like-button') . '</label> <input class="small-text" id="zlikebutton-manual-update-likes" name="zlikebutton-manual-update-likes" type="number" value="'.esc_attr( $total_likes ).'" /><br /><small>' . esc_html__('You’ll need to update your post to save changes.', 'z-like-button') . '</small></p></div>';

        } 
        public function save_like_button($post_id) {
            // If the use cannot edit posts, bail out
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }

            // if the nonce is missing or invalid, bail out
            if ( ! isset($_POST['zlikebutton_metabox_nonce']) ) {
                return;
            }
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['zlikebutton_metabox_nonce'] ) ), 'zlikebutton_nonce' ) ) {
                esc_html_e('Sorry, your nonce did not verify.', 'z-like-button');
                exit;
            }

            // If the post type is not supoorted, bail out
            $post_type = get_post_type($post_id);
            if ( !in_array($post_type, $this->get_all_cpt()) ) {
                return;
            }


            if ( isset($_POST['zlikebutton-manual-update']) && $_POST['zlikebutton-manual-update'] == 1 ) {

                $total_likes = get_post_meta( $post_id, 'zlikebutton_totals', true );
                $manual_update_likes = ( isset($_POST['zlikebutton-manual-update-likes']) && is_numeric( sanitize_text_field( wp_unslash( $_POST['zlikebutton-manual-update-likes']) ) ) ) ? intval( wp_unslash( $_POST['zlikebutton-manual-update-likes']) ) : 0;

                if(empty($total_likes) || !is_numeric($total_likes)) {
                    $total_likes = $manual_update_likes;
                    add_post_meta($post_id, 'zlikebutton_totals', $total_likes, true);
                    update_post_meta($post_id, 'zlikebutton_totals', $total_likes);
                } else {
                    update_post_meta($post_id, 'zlikebutton_totals', $manual_update_likes);
                }
            }

            $like_hide = get_post_meta( $post_id, 'zlikebutton_hide', true );
            if(empty($like_hide)) {
                add_post_meta($post_id, 'zlikebutton_hide', 0, true);
            }
            
            $zlikebutton_hide = ( isset($_POST['zlikebutton_hide']) && is_numeric( sanitize_text_field( wp_unslash( $_POST['zlikebutton_hide'] ) ) ) ) ? intval( wp_unslash( $_POST['zlikebutton_hide'] ) ) : 0;
            update_post_meta($post_id, 'zlikebutton_hide', $zlikebutton_hide);

        }
    

        // Use a shortcode to display an overview of liked posts
        public function shortcode_my_likes_list($atts, $content = null) {

            if( is_user_logged_in() ) {
                $user_id = get_current_user_id();
                $my_likes = get_user_meta( $user_id, 'zlikebutton_likes', true );

                $html = '<ul class="zlb-my-likes-list">';
                if( ! empty( $my_likes ) ) {
                    foreach( $my_likes as $post_id ) {
                        $post_type = get_post_type($post_id);
                        $post_nonce = wp_create_nonce('z-like-button-remove');
                        $html .= '<li data-type="'.$post_type.'">';
                        $html .= '<a href="'.esc_url( get_the_permalink( intval( $post_id ) ) ).'">';
                        $html .= esc_html( get_the_title( intval( $post_id) ) );
                        $html .= '</a>';
                        $html .= '<button class="remove-from-list" data-post-id="'.esc_attr(intval( $post_id)).'" data-post-nonce="'.esc_attr( $post_nonce ).'" title="'.esc_html__('Remove this item from my list','z-like-button').'"><span>'.esc_html__('+','z-like-button').'</span></button>';
                        $html .= '</li>';
                    }
                }
                $html .= '</ul>';

                return $html;

            } else {
                $user_id = 0;
                return '';
            }

        }

        // Filter content for shortcodes
        public function like_button_filter_the_content_for_button($content) {

            if ( !in_array(get_post_type(), $this->get_all_cpt()) ) return $content;

            $options = get_option( 'zlikebutton_options' );
            if (isset($options["show_like_button_before"]) && $options["show_like_button_before"] == 1) {
                $content = do_shortcode('[z_like_button]') . $content;
            }
            if (isset($options["show_like_button_after"]) && $options["show_like_button_after"] == 1) {
                $content = $content . do_shortcode('[z_like_button]');
            }
            return $content;

        }
        public function like_button_filter_the_content_for_my_list($content) {

            $options = get_option( 'zlikebutton_options' );

            if ( ! empty( $options["my_likes_page"] ) ) {
                if ( is_singular() && in_the_loop() && is_main_query() ) {
                    if( get_the_ID() === intval( $options["my_likes_page"] ) ) {
                        $content = $content . do_shortcode('[zlikebutton_likes_list]');
                    }
                }
            }
            return $content;

        }












        private function ip_is_in_postmeta( $ip = 0, $post_id = 0) {
            $liked = false;
            if ( !in_array(get_post_type(intval($post_id)), $this->get_all_cpt() ) ) {
                return false;
            }
            $options = get_option( 'zlikebutton_options' );

            // if the option "show_to_visitors" is set, search for ip
            if (isset($options["show_to_visitors"]) && $options["show_to_visitors"] == true) {
                if( isset( $_SERVER['REMOTE_ADDR'] )) {
                    $remote_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
                } else {
                    $remote_address = uniqid();
                }

                $all_likes = get_post_meta( intval($post_id), 'zlikebutton_likes', true );
                $ip_client = sanitize_text_field( wp_unslash( $remote_address ) );

                $liked = self::valueExistsByField( $ip_client, $all_likes, 'ip');

            }
            return $liked;
            
        }
        private function user_is_in_postmeta( $post_id = 0 ) {
            $liked = false;
            if ( !in_array(get_post_type(intval($post_id)), $this->get_all_cpt() ) ) {
                return false;
            }
            if( ! is_user_logged_in() ) {
                return false;
            }

            $user_id = get_current_user_id();

            $all_likes = get_post_meta( intval($post_id), 'zlikebutton_likes', true );
            $liked = self::valueExistsByField( $user_id, $all_likes, 'user_id');
            
            return $liked;

        }
        private function post_is_in_usermeta( $post_id = 0 ) {
            $liked = false;
            $user_id = 0;
            if ( !in_array(get_post_type(intval($post_id)), $this->get_all_cpt() ) ) {
                return false;
            }
            if( ! is_user_logged_in() ) {
                return false;
            }

            $user_id = get_current_user_id();
            
            $my_likes = get_user_meta( $user_id, 'zlikebutton_likes', true );
            if( ! empty( $my_likes ) ) {
                if( in_array(intval($post_id), $my_likes) ) {
                    $liked = true;
                }
            }
            return $liked;
        }

        private function add_ip_and_user_to_postmeta( $post_id = 0 ) {
            if ( !in_array(get_post_type(intval($post_id)), $this->get_all_cpt() ) ) {
                return false;
            }
            $all_likes = get_post_meta( intval($post_id), 'zlikebutton_likes', true );

            if( ! is_array( $all_likes ) ) { // apparently the first item
                $all_likes = array();
            }

            if( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
                $ip_client = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
            } else {
                $ip_client = 0;
            }

            if( is_user_logged_in() ) {
                $user_id = get_current_user_id();
            } else {
                $user_id = 0;
            }
            // if the USER is not already in the post meta, update the post meta,
            $liked_by_user = self::valueExistsByField( intval($user_id), $all_likes, 'user_id');
            if( $liked_by_user === false ) {
                $all_likes[] = array(
                    'user_id' => $user_id,
                    'ip' => $ip_client,
                    'like' => 1,
                );
                return update_post_meta($post_id, 'zlikebutton_likes', $all_likes);
            } else {
                return false;
            }
        }
        private function add_post_to_usermeta( $post_id = 0 ) {
            if ( !in_array(get_post_type(intval($post_id)), $this->get_all_cpt() ) ) {
                return false;
            }
            if( is_user_logged_in() ) {
                $user_id = get_current_user_id();
            } else {
                $user_id = 0;
            }

            if( ! empty( $user_id ) ) {
                $my_likes = get_user_meta( $user_id, 'zlikebutton_likes', true );
                if( ! is_array( $my_likes ) ) {
                    $my_likes = array();
                }
                // only add if not already in there (for some reason)
                if( ! in_array(intval($post_id), $my_likes) ) {
                    $my_likes[] = intval($post_id);
                }
                return update_user_meta( $user_id, 'zlikebutton_likes', $my_likes );
            } else {
                return 0; // not false!!
            }
        }

        private function remove_ip_and_user_from_postmeta( $post_id = 0, $ip = 0 ) {
           if ( !in_array(get_post_type(intval($post_id)), $this->get_all_cpt() ) ) {
                return false;
            }
            $all_likes = get_post_meta( intval($post_id), 'zlikebutton_likes', true );

            if( ! is_array( $all_likes ) ) { // apparently the first item
                return false; // nothing to remove
            }

            if( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
                $ip_client = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
            } else {
                $ip_client = 0;
            }

            if( is_user_logged_in() ) {
                $user_id = get_current_user_id();
            } else {
                $user_id = 0;
            }
            // let's see if ip address and user_id are in the post meta
            $index_liked_by_ip = self::valueExistsByField( $ip_client, $all_likes, 'ip');
            $index_liked_by_user = self::valueExistsByField( intval($user_id), $all_likes, 'user_id');

            // first check if the user existsa
            if ($index_liked_by_user !== false) {
                // entry exists, remove
                unset( $all_likes[$index_liked_by_user] );
            
            // otherwise, check by ip
            // NOTE: this might not be the same visitor
            } elseif ($index_liked_by_ip !== false) {
                // entry exists, remove
                unset( $all_likes[$index_liked_by_ip] );
            
            }

            return update_post_meta($post_id, 'zlikebutton_likes', $all_likes);

        }

        private function remove_post_from_usermeta( $post_id = 0 ) {            
            if ( !in_array(get_post_type(intval($post_id)), $this->get_all_cpt() ) ) {
                return false;
            }
            if( ! is_user_logged_in() ) {
                return false;
            }
            
            $user_id = get_current_user_id();
            
            if( ! empty( $user_id ) ) {
                $my_likes = get_user_meta( $user_id, 'zlikebutton_likes', true );
                if( ! empty( $my_likes ) ) {
                    if( in_array($post_id, $my_likes) ) {
                        $keysToRemove = array_keys($my_likes, $post_id);
                        foreach($keysToRemove as $k) {
                            unset($my_likes[$k]);
                        }
                        return update_user_meta( $user_id, 'zlikebutton_likes', $my_likes );
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }

        }

        

        public function get_total_likes( $post_id = 0) {
           if ( !in_array(get_post_type(intval($post_id)), $this->get_all_cpt() ) ) {
                return false;
            }
            $totals = get_post_meta( intval($post_id), 'zlikebutton_totals', true );
            if(empty($totals) || !is_numeric($totals)) {
                $totals = 0;
            }
            return intval($totals);
        }
        private function update_total_likes( $post_id = 0, $amount = 0 ) {
           if ( !in_array(get_post_type(intval($post_id)), $this->get_all_cpt() ) ) {
                return false;
            }
            $totals = get_post_meta( intval($post_id), 'zlikebutton_totals', true );
            if(empty($totals) || !is_numeric($totals)) {
                $totals = 0;
                $new = true;
            }
            $totals += intval($amount);

            update_post_meta( intval($post_id), 'zlikebutton_totals', intval($totals) );
        } 




























 


        public function shortcode_like_button($atts, $content = null) {

            $options = get_option( 'zlikebutton_options' );

            // if the option "show_to_visitors" is not set
            // and the user is not logged in, bail out
            if ( ! isset($options["show_to_visitors"]) && ! is_user_logged_in() ) {
                return;
            }

            // if the button is disabled for this page, bail out
            $id_post = get_the_ID();
            $like_hide = get_post_meta( $id_post, 'zlikebutton_hide', true );
            if(isset($like_hide) && $like_hide == 1) {
                return;
            }


            // 1. total likes for this post, from post meta
            $totals = get_post_meta( $id_post, 'zlikebutton_totals', true );
            if(empty($totals) || !is_numeric($totals)) {
                $likes_counted = 0;
            } else {
                $likes_counted = $totals;
            }


            // 2. has the visitor / user liked the post?
            $is_liked = false;
            $ip_client = 0;
            // by IP
            if( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
                $ip_client = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
                $is_liked = $this->ip_is_in_postmeta( $ip_client, $id_post );
            }

            if( is_user_logged_in() ) {
                $is_liked_user_post = $this->user_is_in_postmeta( $id_post );
                $is_liked_user_meta = $this->post_is_in_usermeta( $id_post );

                // repair stuff
                if( $is_liked_user_post && ! $is_liked_user_meta ) {
                    $this->add_post_to_usermeta( $id_post );
                    $is_liked = true;
                } elseif( $is_liked_user_meta && ! $is_liked_user_post ) {
                    $this->add_ip_and_user_to_postmeta( $id_post );
                    $is_liked = true;
                }
            }



            // 3. Output
            $current_icon = (isset($options['icon'])) ? $options['icon'] : 'icon-heart';
            $color_inactive =  (isset($options['color_inactive'])) ? $options['color_inactive'] : '#989898';
            $color_active = (isset($options['color_active'])) ? $options['color_active'] : '#ef1d5f';    
            

            $class = '';
            $checked = '';
            if (isset($options['hide_counter_box']) && $options['hide_counter_box'] == true) {
                $class .= ' hide-counter';
            }

            if ($is_liked !== false) {
                $class .= ' liked';
                $checked = 'checked';
            }
            $random_id = wp_rand(50000000,500000000);

            return '<div class="zLikeButton'. esc_html( $class ).'" style="--z-color-inactive: '. esc_attr( $color_inactive ).';--z-color-active: '. esc_attr( $color_active ).'"><input '.esc_html( $checked ).' id="post_'.esc_html( $id_post ).'_'.$random_id.'" type="checkbox" class="likeCheck"/>
                <label for="post_'.esc_html( $id_post ).'_'.$random_id.'" class="zlb-icon '. esc_attr($current_icon) .' zLikeLabel" aria-label="like this"></label><span class="likeCount">'. esc_html( $likes_counted) .'</span></div>';
        }












        public function handle_ajax() {
            if( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'])), 'z-like-button' ) ) {
                die( 'Forbidden !');
            }
            if( empty( $_POST['post'] ) ) {
                die( 'Forbidden !');
            }

            $ip_client = ( ! empty($_SERVER['REMOTE_ADDR'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
            $id_post = intval( str_replace('post_', '', sanitize_text_field( wp_unslash( $_POST['post'] ) ) ) );
            $is_liked = false;
            $return_text = 'nothing done';
            $log = array();


            $is_liked = $this->ip_is_in_postmeta( $ip_client, $id_post );
            
            if( is_user_logged_in() ) {
                $is_liked_user_post = $this->user_is_in_postmeta( $id_post );
                $is_liked_ip_post = $this->ip_is_in_postmeta( $id_post );

                if( $is_liked_user_post !== false  || $is_liked_ip_post !== false ) {
                    $is_liked = true;
                }

            
                // repair stuff
                if( $is_liked_user_post !== false && $is_liked_ip_post === false ) {
                    $add_post_to_usermeta = $this->add_post_to_usermeta( $id_post );
                    $is_liked = true;
                } elseif( $is_liked_user_post === false && $is_liked_ip_post !== false ) {
                    $add_ip_and_user_to_postmeta = $this->add_ip_and_user_to_postmeta( $id_post );
                    $is_liked = true;
                }
            }
            

            if( $is_liked !== false ) {
                // the post is already liked, so remove likes
                $removed_from_post = $this->remove_ip_and_user_from_postmeta( $id_post );
                $removed_from_user = $this->remove_post_from_usermeta( $id_post );

                if( $removed_from_post || $removed_from_user ) {
                    $this->update_total_likes( $id_post, -1 );
                    $return_text = 'One like removed for post ' . $id_post;
                } else {
                    $return_text = 'Wanted to remove for post ' . $id_post . ', but the data did not match.';
                }

            } else {
                // add likes
                $added_to_post = $this->add_ip_and_user_to_postmeta( $id_post );
                $added_to_user = $this->add_post_to_usermeta( $id_post );
 
                if( $added_to_post || $added_to_user ) {
                    $this->update_total_likes( $id_post, 1 );
                    $return_text = 'One like added for post ' . $id_post;
                    
                } else {
                    $return_text = 'Wanted to add for post ' . $id_post . ', but the data did not match.';
                
                }

            }

            $message['likes'] = $this->get_total_likes( $id_post );
            $message['message'] = $return_text;
            $message['log'] = $log;
            echo wp_json_encode($message);
            die();
        }




        public function handle_ajax_removal() {
            if( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'])), 'z-like-button-remove' ) ) {
                die( 'Forbidden !');
            }
            if( empty( $_POST['post'] ) ) {
                die( 'Forbidden !');
            }

            $post_id = intval( sanitize_text_field( wp_unslash( $_POST['post'] ) ) );
            
            $removed_from_post = $this->remove_ip_and_user_from_postmeta( $post_id );
            $removed_from_user = $this->remove_post_from_usermeta( $post_id );

            if( $removed_from_post || $removed_from_user ) {
                $this->update_total_likes( $post_id, -1 );
                $return_text = 'Item ' . $post_id . ' removed successfully';
                $return_id = $post_id;
            } else {
                $return_text = 'An error occurred trying to remove the entry for post ' . $post_id . ': the data did not match.';
                $return_id = 0;
            }

            $message['removed'] = intval( $return_id );
            $message['message'] = $return_text;
            echo wp_json_encode($message);
            die();
        }







        /* ============== Helper methods ==================== */



        /*
         * Check if a value exists, differentiate between 
         * different field types
         * 
         * @param $needle (string) -- the value to search for
         * $param $haystack (array) -- the array to search
         * @param $key (string) -- the field type (column) to search by (ip or user_id)
         * 
         * @return (mixed) -- true if the value exists, false if not
         */
        public function valueExistsByField($needle = false, $haystack = array(), $key = '') {
            if (empty($needle) || empty($haystack) || empty($key)) {
                return false;
            }
            
            $column = array_column($haystack, $key);
            // $index = array_search($needle, $column, true); // strict mode, more secure
            return array_search($needle, $column, true);
            // return $index !== false ? $index : false;
        }


        // Get all available icons
        public function getAllIcons() {
            $icons = array();
            $icons[] = 'icon-heart';
            $icons[] = 'icon-bookmark-outline';
            $icons[] = 'icon-bookmark';
            $icons[] = 'icon-star-full';
            $icons[] = 'icon-tag';
            $icons[] = 'icon-thumbs-up';
            $icons[] = 'icon-thumbs-down';
            $icons[] = 'icon-save-disk';
            $icons[] = 'icon-view-show';
            $icons[] = 'icon-view-hide';
            $icons[] = 'icon-pin';
            $icons[] = 'icon-notifications-outline';
            $icons[] = 'icon-notifications';

            $icons[] = 'icon-document';
            $icons[] = 'icon-document-add';
            $icons[] = 'icon-inbox';
            $icons[] = 'icon-inbox-check';
            $icons[] = 'icon-inbox-download';
            $icons[] = 'icon-inbox-full';
            $icons[] = 'icon-link';
            $icons[] = 'icon-list-add';

            $icons[] = 'icon-badge';
            $icons[] = 'icon-book-reference';
            $icons[] = 'icon-compose';
            $icons[] = 'icon-copy';
            $icons[] = 'icon-date-add';
            $icons[] = 'icon-dial-pad';
            $icons[] = 'icon-edit-pencil';
            $icons[] = 'icon-home';
            $icons[] = 'icon-hour-glass';
            $icons[] = 'icon-light-bulb';
            $icons[] = 'icon-location';
            $icons[] = 'icon-lock-closed';
            $icons[] = 'icon-lock-open';
            $icons[] = 'icon-menu';
            $icons[] = 'icon-mic';
            $icons[] = 'icon-mood-happy-outline';
            $icons[] = 'icon-mood-happy-solid';
            $icons[] = 'icon-mood-neutral-outline';
            $icons[] = 'icon-mood-neutral-solid';
            $icons[] = 'icon-mood-sad-outline';
            $icons[] = 'icon-mood-sad-solid';
            $icons[] = 'icon-network';
            $icons[] = 'icon-news-paper';
            $icons[] = 'icon-portfolio';
            $icons[] = 'icon-time';
            $icons[] = 'icon-timer';
            $icons[] = 'icon-volume-mute';
            $icons[] = 'icon-volume-off';
            $icons[] = 'icon-volume-up';
            $icons[] = 'icon-wallet';

            return $icons;

        }

        // Get all post types with a like button
        private function get_all_cpt() {
            $args = array(
                'public'   => true
            );
            $post_types = get_post_types( $args );
            unset($post_types['attachment']);

            $options = get_option( 'zlikebutton_options' );

            $cpts = array();
            foreach ($post_types as $key => $value) {
                if(isset($options['cpt_' . $key])) {
                    $cpts[$key] = $value;
                }
            }
            return apply_filters( 'zlikebutton_add_cpt', $cpts );
        }


        // Create an instance of the like button
        public static function get_instance() {

            if ( null == self::$instance ) {
                self::$instance = new self;
            }
            return self::$instance;
        }
    }



    zLikeButton::get_instance();


endif;
