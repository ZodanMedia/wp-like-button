<?php
/**
 * Plugin Name: Z Like Button
 * Contributors: zodannl, martenmoolenaar
 * Plugin URI: https://plugins.zodan.nl/wordpress-like-button/
 * Tags: like, button, like, like button, custom like
 * Requires at least: 5.5
 * Tested up to: 6.8
 * Description: Displays a simple and customisable like-button for all types of posts.
 * Version: 0.0.1
 * Stable Tag: 0.0.1
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
        private $version_number = '0.0.1';

        protected static $instance = null;

        public function __construct(){
            load_plugin_textdomain(
                'z-like-button',
                false,
                basename( dirname( __FILE__ ) ) . '/languages/'
            );
            add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts_like_button'));
            add_action('wp_enqueue_scripts', array($this, 'like_button_scripts'));
            add_action('add_meta_boxes', array($this, 'like_button_boxes'));
            add_action('admin_enqueue_scripts', array($this, 'like_button_css'), 11, 1 );

            /**
             * Ajax functions for front-end
             */
            add_action('wp_ajax_like_button', array($this, 'handle_ajax'));
            add_action('wp_ajax_nopriv_like_button', array($this, 'handle_ajax'));

            /**
             * Shortcode for inserting the button
             */
            add_shortcode('z_like_button',array($this, 'shortcode_like_button'));

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
             * Add shortcode to content filter
             */
            add_filter( 'the_content', array($this, 'like_button_filter_the_content') );

        }


        public function wp_enqueue_scripts_like_button() {
            $options = get_option( 'z_like_button_options' );

            if (!isset($options["show_like_active_dashicons"]) || $options["show_like_active_dashicons"] != true) {
                wp_enqueue_style( 'dashicons' );
            }

            if (!isset($options["show_like_active_css"]) || $options["show_like_active_css"] != true) {
                $plugins_url = plugin_dir_url( __FILE__ );
                wp_enqueue_style( 'z-like-button-css', $plugins_url . 'assets/z-like-button.css', null, $this->version_number );
            }
        }


        public function like_button_css($hook) {

            global $current_screen, $typenow;

            $plugins_url = plugin_dir_url( __FILE__ );
            wp_enqueue_style( 'z-like-button-admin-css', $plugins_url . 'assets/admin-styles.css' , null, $this->version_number );

            if((isset($current_screen) && isset($current_screen->base) && ($current_screen->base == 'settings_page_z_like_button_options' || $current_screen->base == '' )) || ( $hook == 'post-new.php' || $hook == 'post.php' ) ){

                wp_register_script( 'z-like-button-admin', $plugins_url . 'assets/admin-scripts.js', array('jquery'), $this->version_number, array( 'in_footer' => true ) );
                wp_enqueue_script( 'z-like-button-admin' );

            }
        }


        public function like_button_settings_init() {

            register_setting( 'like_button_group', 'z_like_button_options' );

            add_settings_section(
                'z_like_button_options_page_section',
                esc_html__('Plugin settings', 'z-like-button'),
                array($this, 'z_like_button_options_page_settings_section_callback'),
                'like_button_settings'
            );

            add_settings_field(
                'z_like_button_options_field_show',
                esc_html__('Button location', 'z-like-button'),
                array($this, 'z_like_button_options_field_show_render'),
                'like_button_settings',
                'z_like_button_options_page_section'
            );

            add_settings_field(
                'z_like_button_options_field_show_on',
                esc_html__('Display like button on the following post types', 'z-like-button'),
                array($this, 'z_like_button_options_field_show_on_render'),
                'like_button_settings',
                'z_like_button_options_page_section'
            );

            add_settings_field(
                'z_like_button_options_hide_counter',
                esc_html__('Hide the counter display', 'z-like-button'),
                array($this, 'z_like_button_options_hide_counter_render'),
                'like_button_settings',
                'z_like_button_options_page_section'
            );




            add_settings_field(
                'z_like_button_options_active_css',
                esc_html__('Disable plugin’s CSS', 'z-like-button'),
                array($this, 'z_like_button_options_field_active_css_render'),
                'like_button_settings',
                'z_like_button_options_page_section'
            );

            add_settings_field(
                'z_like_button_options_active_dashicons',
                esc_html__('Disable plugin’s default dashicon', 'z-like-button'),
                array($this, 'z_like_button_options_field_active_dashicons_render'),
                'like_button_settings',
                'z_like_button_options_page_section'
            );
        }

        public function z_like_button_options_page_settings_section_callback() {
            echo '<p>';
            printf(
                /* translators: %s is a shortcode used by the plugin */
                esc_html__( 'The Like Button can be added anywhere using the shortcode %s.', 'z-like-button' ),
                '<code>[z_like_button]</code>'
            );  
            echo '.</p><p>';
            esc_html_e( 'Or automatically before and/or after the content using the "button location" settings', 'z-like-button' );
            echo '.</p>';

        }


        public function z_like_button_options_field_show_render() {
            $options = get_option( 'z_like_button_options' );

            $checked = "";
            if (isset($options["show_like_button_before"]) && $options["show_like_button_before"] == true) {
                $checked = " checked='checked' ";
            }
            echo '<label for="show_like_button_before"><input id="show_like_button_before" type="checkbox" name="z_like_button_options[show_like_button_before]" value="true" '.esc_attr( $checked ).' />'.esc_html__('Before the content', 'z-like-button').'</label><br>';

            $checked = "";
            if (isset($options["show_like_button_after"]) && $options["show_like_button_after"] == true) {
                $checked = " checked='checked' ";
            }
            echo '<label for="show_like_button_after"><input id="show_like_button_after" type="checkbox" name="z_like_button_options[show_like_button_after]" value="true" '.esc_attr( $checked ).' />'.esc_html__('After the content', 'z-like-button').'</label><br>';

            if ((!isset($options["show_like_button_before"]) || $options["show_like_button_before"] != true) && (!isset($options["show_like_button_after"]) || $options["show_like_button_after"] != true) ) {
                echo '<p class="alert alert-warning"><span class="dashicons dashicons-warning"></span> ';
                printf(
                    /* translators: %s is a shortcode used by the plugin */
                    esc_html__( 'If nothing is checked, the button will not show unless you manually add the %s shortcode somewhere in your content', 'z-like-button' ),
                    '<code>[z_like_button]</code>'
                );
                echo '</p>';
            }
        }


        public function z_like_button_options_field_show_on_render() {
            $options = get_option( 'z_like_button_options' );

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
                echo '<label for="cpt_'.esc_attr( $key ).'"><input id="cpt_'.esc_attr( $key ).'" type="checkbox" name="z_like_button_options[cpt_'.esc_attr( $key ).']" value="true" '.esc_attr( $checked ).' />'.esc_html( $value ).'<br></label>';
            }
        }


        public function z_like_button_options_hide_counter_render() {
            $options = get_option( 'z_like_button_options' );

            $checked = "";
            if (isset($options["hide_counter_box"]) && $options["hide_counter_box"] == true) {
                $checked = " checked='checked' ";
            }
            echo '<input id="like_hide_counter_box" type="checkbox" name="z_like_button_options[hide_counter_box]" value="true" '.esc_attr( $checked ).' /><label for="like_hide_counter_box">' . esc_html__('Check this to hide the like counter box', 'z-like-button') . '</label>';
        }










        public function z_like_button_options_field_active_css_render() {
            $options = get_option( 'z_like_button_options' );

            $checked = "";
            if (isset($options["show_like_active_css"]) && $options["show_like_active_css"] == true) {
                $checked = " checked='checked' ";
            }
            echo '<input id="show_like_active_css" type="checkbox" name="z_like_button_options[show_like_active_css]" value="true" '.esc_attr( $checked ).' /><label for="show_like_active_css">' . esc_html__('Check this to disable our CSS and use your own custom CSS rules.', 'z-like-button') . '</label>';
        }

        public function z_like_button_options_field_active_dashicons_render() {
            $options = get_option( 'z_like_button_options' );

            $checked = "";
            if (isset($options["show_like_active_dashicons"]) && $options["show_like_active_dashicons"] == true) {
                $checked = " checked='checked' ";
            }
            echo '<input id="show_like_active_dashicons" type="checkbox" name="z_like_button_options[show_like_active_dashicons]" value="true" '.esc_attr( $checked ).' /><label for="show_like_active_dashicons">' . esc_html__('Check this to disable our heart dashicon and use your own custom button appearance.', 'z-like-button') . '</label>';

        }

        public function z_like_button_options_page() {
            add_filter('admin_footer_text', array($this, 'z_admin_footer_print_thankyou'), 900);

            ?>
            <div class="wrap like-me-options">
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


        public function like_button_add_admin_menu() {
            add_options_page(esc_html__('Configuration like me', 'z-like-button'), esc_html__('Z Like Button', 'z-like-button'), 'manage_options', 'z_like_button_options', array($this, 'z_like_button_options_page') );
        }



        public function save_like_button($post_id) {
            
            if ( ! isset( $_POST['z_like_button_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['z_like_button_metabox_nonce'] ) ), 'z_like_button_nonce' ) ) {
                esc_html_e('Sorry, your nonce did not verify.', 'z-like-button');
                exit;
            }

            $post_type = get_post_type($post_id);

            if ( !in_array($post_type, $this->get_all_cpt()) ) return;

            if( isset($_POST) && count($_POST) > 0 ) {
                if( isset($_POST['z-like-button-manual-update']) && $_POST['z-like-button-manual-update'] == 1 ) {

                    $total_likes = get_post_meta( $post_id, 'z_like_button_totals', true );

                    $manual_update_likes = ( isset($_POST['z-like-button-manual-update-likes']) && is_numeric( sanitize_text_field( wp_unslash( $_POST['z-like-button-manual-update-likes']) ) ) ) ? intval( wp_unslash( $_POST['z-like-button-manual-update-likes']) ) : 0;

                    if(empty($total_likes) || !is_numeric($total_likes)) {
                        $total_likes = $manual_update_likes;
                        add_post_meta($post_id, 'z_like_button_totals', $total_likes, true);
                        update_post_meta($post_id, 'z_like_button_totals', $total_likes);
                    } else {
                        update_post_meta($post_id, 'z_like_button_totals', $manual_update_likes);
                    }
                }

                $like_hide = get_post_meta( $post_id, 'z_like_button_hide', true );
                if(empty($like_hide)) {
                    add_post_meta($post_id, 'z_like_button_hide', 0, true);
                }
                $z_like_button_hide = ( isset($_POST['z_like_button_hide']) && is_numeric( sanitize_text_field( wp_unslash( $_POST['z_like_button_hide'] ) ) ) ) ? intval( wp_unslash( $_POST['z_like_button_hide'] ) ) : 0;

                update_post_meta($post_id, 'z_like_button_hide', $z_like_button_hide);
            }

        }


        public function like_button_filter_the_content($content) {

            if ( !in_array(get_post_type(), $this->get_all_cpt()) ) return $content;

            $options = get_option( 'z_like_button_options' );
            if (isset($options["show_like_button_before"]) && $options["show_like_button_before"] == true) {
                $content = do_shortcode('[z_like_button]') . $content;
            }
            if (isset($options["show_like_button_after"]) && $options["show_like_button_after"] == true) {
                $content = $content . do_shortcode('[z_like_button]');
            }

            return $content;

        }


        public function shortcode_like_button($atts, $content = null) {

            $id_post = get_the_ID();
            $like_hide = get_post_meta( $id_post, 'z_like_button_hide', true );
            if(isset($like_hide) && $like_hide == 1) {
                return;
            }
    
            $all_likes = get_post_meta( $id_post, 'z_like_button_likes', true );
            $totals = get_post_meta( $id_post, 'z_like_button_totals', true );

            $options = get_option( 'z_like_button_options' );

            
           
            if(empty($totals) || !is_numeric($totals)) {
                $like = 0;
            } else {
                $like = $totals;
            }

            if( is_user_logged_in() ) {
                $user_id = get_current_user_id();
            } else {
                $user_id = 0;
            }

            // by user or ip
            if( $user_id ==! 0 ) {
                $index = self::valueExistsByField( $user_id, $all_likes, 'user_id');
            } else {
                $ip_client = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
                $index = self::valueExistsByField( $ip_client, $all_likes, 'ip');
            }

            $class = '';
            $checked = '';
            if (isset($options['hide_counter_box']) && $options['hide_counter_box'] == true) {
                $class .= ' hide-counter';
            }

            if ($index !== false) {
                $class .= ' liked';
                $checked = 'checked';
            }
            $random_id = rand(50000000,500000000);

            return '<div class="zLikeButton'. esc_html( $class ).'"><input '.esc_html( $checked ).' id="post_'.esc_html( $id_post ).'_'.$random_id.'" type="checkbox" class="likeCheck"/>
                <label for="post_'.esc_html( $id_post ).'_'.$random_id.'" class="dashicons dashicons-heart likeLabel" aria-label="like this"></label><span class="likeCount">'. esc_html( $like) .'</span></div>';
        }



        public function handle_ajax() {
            if( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'])), 'z-like-button' ) ) {
                die( 'Forbidden !' . $_POST['nonce']);
            }
            if( empty( $_POST['post'] ) ) {
                die( 'Forbidden !');
            }
            $ip_client = ( ! empty($_SERVER['REMOTE_ADDR'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

            $id_post = intval( str_replace('post_', '', sanitize_text_field( wp_unslash( $_POST['post'] ) ) ) );

            $all_likes = get_post_meta( $id_post, 'z_like_button_likes', true );
            if( empty( $all_likes ) ) {
                $all_likes = array();
            }
            $totals = get_post_meta( $id_post, 'z_like_button_totals', true );
           
            if( is_user_logged_in() ) {
                $user_id = get_current_user_id();
            } else {
                $user_id = 0;
            }

            if( ( ! isset($totals) || $totals == '' ) ) {

                // this is the first one
                $data = array();
                $data[] = array(
                    'user_id' => $user_id,
                    'ip' => $ip_client,
                    'like' => 1,
                );
                $totals = 1;
                add_post_meta($id_post, 'z_like_button_totals', 1, true);
                add_post_meta($id_post, 'z_like_button_likes', $data, true);

                $where = 'ZnVjayB5b3U';

            } else {

                // by user or ip
                if( ! empty( $user_id ) ) {
                    $index = self::valueExistsByField( $user_id, $all_likes, 'user_id');
                } else {
                    $index = self::valueExistsByField( $ip_client, $all_likes, 'ip');
                }

                if ($index !== false) {
                    // entry exists, remove
                    unset( $all_likes[$index] );
                    update_post_meta( $id_post, 'z_like_button_likes', $all_likes );

                    // fix totals 
                    $totals = $totals - 1;
                    update_post_meta( $id_post, 'z_like_button_totals', $totals, true );

                    $where = 'bG92ZSB5b3U';

                } else {
                    // entry does not exist, add
                    $item = array(
                        'user_id' => $user_id,
                        'ip' => $ip_client,
                        'like' => 1,
                    );
                    $all_likes[] = $item;
                    $totals = $totals + 1;

                    update_post_meta($id_post, 'z_like_button_likes', $all_likes);
                    update_post_meta($id_post, 'z_like_button_totals', $totals);
                
                    $where = 'YnJlZ2pl';
                    
                }
            }
            $message['likes'] = $totals;
            $message['where'] = $where;
            echo json_encode($message);
            die();
        }




        public function like_button_scripts() {
            $plugins_url = plugin_dir_url( __FILE__ );

            wp_register_script( 'z-like-button', $plugins_url . 'assets/z-like-button.js', array( 'jquery' ), array('jquery'), $this->version_number, array( 'in_footer' => true ) );
            wp_enqueue_script('z-like-button');

            wp_localize_script('z-like-button', 'like_button', array(
                    'url'   => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('z-like-button'),
                )
            );

        }



        public function like_button_boxes() {
            $post_types = $this->get_all_cpt();

            if(isset($post_types) && count($post_types) > 0) {
                add_meta_box( 'meta-box-z-like-button', '<span>' . esc_html__( 'Z Like Button', 'z-like-button' ) . '  <span class="dashicons dashicons-heart"></span></span> ', array($this, 'like_button_metabox'), $post_types, 'side' );
            }
        }



        public function like_button_metabox($post) {
            $total_likes = get_post_meta( $post->ID, 'z_like_button_totals', true );

            if(empty($total_likes) || !is_numeric($total_likes)) {
                $total_likes = 0;
                add_post_meta($post->ID, 'z_like_button_totals', $total_likes, true);
                update_post_meta($post->ID, 'z_like_button_totals', $total_likes);
            }

            wp_nonce_field( 'z_like_button_nonce', 'z_like_button_metabox_nonce');

            echo '<p>' . esc_html__('Current count:', 'z-like-button') . ' <span class="badge badge-info">' . esc_attr( $total_likes ) . '</span></p>';

            // Hide like button for this post
            $like_hide = get_post_meta( $post->ID, 'z_like_button_hide', true );
            if(empty($like_hide) || !is_numeric($like_hide)) {
                add_post_meta($post->ID, 'z_like_button_hide', 0, true);
                update_post_meta($post->ID, 'z_like_button_hide', 0);
            }
            if (isset($like_hide) && $like_hide == 1) {
                $checked = " checked='checked' ";
            } else {
                $checked = "";
            }

            echo '<p><input id="z-hide-like-button" name="z_like_button_hide" type="checkbox" value="1" '.esc_attr( $checked ).' /> <label for="z-hide-like-button">'.esc_html__('Hide like button for this post', 'z-like-button').'</label></p>';

            echo '<p><input id="z-like-button-manual-update" name="z-like-button-manual-update" type="checkbox" value="1" /> <label for="z-like-button-manual-update">'.esc_html__('Manual update', 'z-like-button').'</label></p>';

            echo '<div id="z-like-button-update-wrapper"><p><label for="z-like-button-manual-update-likes">' . esc_html__('New total:', 'z-like-button') . '</label> <input class="small-text" id="z-like-button-manual-update-likes" name="z-like-button-manual-update-likes" type="number" value="'.esc_attr( $total_likes ).'" /><br /><small>' . esc_html__('You’ll need to update your post to save changes.', 'z-like-button') . '</small></p></div>';

        }



        public function valueExistsByField($needle = false, $haystack = array(), $key = '') {
            if (empty($needle) || empty($haystack) || empty($key)) {
                return false;
            }

            $column = array_column($haystack, $key);
            $index = array_search($needle, $column, true); // strict mode, more secure

            return $index !== false ? $index : false;
        }



        private function get_all_cpt() {
            $args = array(
                'public'   => true
            );
            $post_types = get_post_types( $args );
            unset($post_types['attachment']);

            $options = get_option( 'z_like_button_options' );

            $cpts = array();
            foreach ($post_types as $key => $value) {
                if(isset($options['cpt_' . $key])) {
                    $cpts[$key] = $value;
                }
            }
            return apply_filters( 'like_button_add_cpt', $cpts );
        }

        public static function get_instance() {

            if ( null == self::$instance ) {
                self::$instance = new self;
            }

            return self::$instance;
        }
    }



    zLikeButton::get_instance();


endif;
