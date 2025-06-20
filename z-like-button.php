<?php
/**
 * Plugin Name: Z Like Button
 * Contributors: zodannl, martenmoolenaar
 * Plugin URI: https://plugins.zodan.nl/wordpress-like-button/
 * Tags: like, button, like, like button, custom like
 * Requires at least: 5.5
 * Tested up to: 6.8
 * Description: Displays a simple and customisable like-button for all types of posts.
 * Version: 0.0.4
 * Stable Tag: 0.0.4
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
        private $version_number = '0.0.4';

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
            add_shortcode('z_my_likes_list',array($this, 'shortcode_my_likes_list'));

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


        public function wp_enqueue_scripts_like_button() {
            $options = get_option( 'z_like_button_options' );

            // if (!isset($options["show_like_active_dashicons"]) || $options["show_like_active_dashicons"] != true) {
            //     wp_enqueue_style( 'dashicons' );
            // }

            if (!isset($options["show_like_active_css"]) || $options["show_like_active_css"] != true) {
                $plugins_url = plugin_dir_url( __FILE__ );
                wp_enqueue_style( 'z-like-button-css', $plugins_url . 'assets/z-like-button.css', null, $this->version_number );
            }
        }


        public function like_button_css($hook) {

            global $current_screen, $typenow;

            $plugins_url = plugin_dir_url( __FILE__ );
            wp_enqueue_style( 'z-like-button-admin-css', $plugins_url . 'assets/admin-styles.css' , array( 'wp-color-picker' ), $this->version_number );

            if((isset($current_screen) && isset($current_screen->base) && ($current_screen->base == 'settings_page_z_like_button_options' || $current_screen->base == '' )) || ( $hook == 'post-new.php' || $hook == 'post.php' ) ){

                wp_register_script( 'z-like-button-admin', $plugins_url . 'assets/admin-scripts.js', array('jquery', 'wp-color-picker'), $this->version_number, array( 'in_footer' => true ) );
                wp_enqueue_script( 'z-like-button-admin' );

                wp_localize_script('z-like-button-admin', 'z_like_button_admin', array(
                        'copiedText' => esc_html__('Shortcode copied!', 'z-like-button')
                    )
                );
            }
        }


        public function like_button_settings_init() {

            $settings_args = array(
                'type' => 'array',
                'description' => '',
                // 'sanitize_callback' => 'z_like_button_plugin_options_validate',
                'sanitize_callback' => array($this, 'z_like_button_plugin_options_validate'),
                'show_in_rest' => false
            );

            register_setting( 'like_button_group', 'z_like_button_options', $settings_args );

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
                'z_like_button_options_icon',
                esc_html__('Icon to display', 'z-like-button'),
                array($this, 'z_like_button_options_field_icon_render'),
                'like_button_settings',
                'z_like_button_options_page_section'
            );

            add_settings_field(
                'z_like_button_options_colors',
                esc_html__('Icon colors', 'z-like-button'),
                array($this, 'z_like_button_options_field_colors_render'),
                'like_button_settings',
                'z_like_button_options_page_section'
            );

            add_settings_field(
                'z_like_button_options_my_likes_page',
                esc_html__('My likes page', 'z-like-button') . '<span class="description">' . esc_html__('Which page will have an overview of posts the user liked', 'z-like-button') . '</span>',
                array($this, 'z_like_button_options_field_my_likes_page_render'),
                'like_button_settings',
                'z_like_button_options_page_section'
            );



            

            // add_settings_field(
            //     'z_like_button_options_active_dashicons',
            //     esc_html__('Disable plugin’s default dashicon', 'z-like-button'),
            //     array($this, 'z_like_button_options_field_active_dashicons_render'),
            //     'like_button_settings',
            //     'z_like_button_options_page_section'
            // );
        }

        public function z_like_button_options_page_settings_section_callback() {
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


        public function z_like_button_options_field_show_render() {
            $options = get_option( 'z_like_button_options' );

            $checked = "";
            if (isset($options["show_like_button_before"]) && $options["show_like_button_before"] == true) {
                $checked = " checked='checked' ";
            }
            echo '<label for="show_like_button_before"><input id="show_like_button_before" type="checkbox" name="z_like_button_options[show_like_button_before]" value="1" '.esc_attr( $checked ).' />'.esc_html__('Before the content', 'z-like-button').'</label><br>';

            $checked = "";
            if (isset($options["show_like_button_after"]) && $options["show_like_button_after"] == true) {
                $checked = " checked='checked' ";
            }
            echo '<label for="show_like_button_after"><input id="show_like_button_after" type="checkbox" name="z_like_button_options[show_like_button_after]" value="1" '.esc_attr( $checked ).' />'.esc_html__('After the content', 'z-like-button').'</label><br>';

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
                echo '<label for="cpt_'.esc_attr( $key ).'"><input id="cpt_'.esc_attr( $key ).'" type="checkbox" name="z_like_button_options[cpt_'.esc_attr( $key ).']" value="1" '.esc_attr( $checked ).' />'.esc_html( $value ).'<br></label>';
            }
        }


        public function z_like_button_options_hide_counter_render() {
            $options = get_option( 'z_like_button_options' );

            $checked = "";
            if (isset($options["hide_counter_box"]) && $options["hide_counter_box"] == true) {
                $checked = " checked='checked' ";
            }
            echo '<input id="like_hide_counter_box" type="checkbox" name="z_like_button_options[hide_counter_box]" value="1" '.esc_attr( $checked ).' /><label for="like_hide_counter_box">' . esc_html__('Check this to hide the like counter box', 'z-like-button') . '</label>';
        }

        public function z_like_button_options_field_icon_render() {
            $options = get_option( 'z_like_button_options' );

            $current_icon = (isset($options['icon'])) ? $options['icon'] : 'icon-heart';

            $all_icons = $this->getAllIcons();

            foreach( $all_icons as $key => $value ) {
                $checked = "";
                if( $value == $current_icon ) {
                    $checked = ' checked="checked"';
                }
                echo '<label><input type="radio" name="z_like_button_options[icon]" value="'. esc_attr( $value ) .'"'.esc_attr( $checked ).'><i class="zlb-icon '. esc_attr( $value ) .'"></i></label>';

           }
        }


        public function z_like_button_options_field_colors_render() {
            $options = get_option( 'z_like_button_options' );

            $current_color_inactive = (isset($options['color_inactive'])) ? $options['color_inactive'] : '#989898';
            $current_color_active = (isset($options['color_active'])) ? $options['color_active'] : '#ef1d5f';

            echo '<label for="z-like-button-inactive" class="color-label-faux"><span class="label-text">'. esc_html__( 'Inactive', 'z-like-button' ) .'</span></label>';
            echo '<input id="z-like-button-inactive" class="z-like-button-color-field" type="text" name="z_like_button_options[color_inactive]" value="'. esc_attr( $current_color_inactive ) .'">';
            echo '<label for="id="z-like-button-active" class="color-label-faux"><span class="label-text">'. esc_html__( 'Active', 'z-like-button' ) .'</span></label>';
            echo '<input id="z-like-button-active" class="z-like-button-color-field" type="text" name="z_like_button_options[color_active]" value="'. esc_attr( $current_color_active ) .'">';

        }



        public function z_like_button_options_field_my_likes_page_render() {
            $options = get_option( 'z_like_button_options' );

            $current_page = (isset($options['my_likes_page'])) ? $options['my_likes_page'] : 0;


            // 1. Create select with existing page options
			$args = array(
                'numberposts' => -1,
                'post_type'   => 'page'
            );
            $all_pages = get_posts( $args );

            echo '<select name=z_like_button_options[my_likes_page]>';
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

            echo '<p class="alert alert-info"><span class="dashicons dashicons-info"></span> ';
            esc_html_e( 'The list of likes is automatically added to the content of the selected page.', 'z-like-button' );
            echo '<br>';
            printf(
                /* translators: %s is a shortcode used by the plugin */
                esc_html__('If "No page" is selected, you can add this list yourself anywhere using the %s shortcode.', 'z-like-button' ),
                '<code>[z_my_likes_list]</code>'
            );
            echo '</p>';

        }






        // public function z_like_button_options_field_active_dashicons_render() {
        //     $options = get_option( 'z_like_button_options' );

        //     $checked = "";
        //     if (isset($options["show_like_active_dashicons"]) && $options["show_like_active_dashicons"] == true) {
        //         $checked = " checked='checked' ";
        //     }
        //     echo '<input id="show_like_active_dashicons" type="checkbox" name="z_like_button_options[show_like_active_dashicons]" value="true" '.esc_attr( $checked ).' /><label for="show_like_active_dashicons">' . esc_html__('Check this to disable our heart dashicon and use your own custom button appearance.', 'z-like-button') . '</label>';

        //     echo '<i class="zlb-icon icon-bookmark"></i>';
        // }


        public function z_like_button_options_page() {
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


        public function z_like_button_plugin_options_validate( $input ) {
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
            add_options_page(esc_html__('Configuration like me', 'z-like-button'), esc_html__('Like Button', 'z-like-button'), 'manage_options', 'z_like_button_options', array($this, 'z_like_button_options_page') );
        }



        public function save_like_button($post_id) {
            
            if ( isset( $_POST['z_like_button_metabox_nonce'] ) ) {
                if( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['z_like_button_metabox_nonce'] ) ), 'z_like_button_nonce' ) ) {
                    esc_html_e('Sorry, your nonce did not verify.', 'z-like-button');
                    exit;
                }
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


        public function like_button_filter_the_content_for_button($content) {

            if ( !in_array(get_post_type(), $this->get_all_cpt()) ) return $content;

            $options = get_option( 'z_like_button_options' );
            if (isset($options["show_like_button_before"]) && $options["show_like_button_before"] == 1) {
                $content = do_shortcode('[z_like_button]') . $content;
            }
            if (isset($options["show_like_button_after"]) && $options["show_like_button_after"] == 1) {
                $content = $content . do_shortcode('[z_like_button]');
            }
            return $content;

        }


        public function like_button_filter_the_content_for_my_list($content) {

            $options = get_option( 'z_like_button_options' );

            if ( ! empty( $options["my_likes_page"] ) ) {
                if ( is_singular() && in_the_loop() && is_main_query() ) {
                    if( get_the_ID() === intval( $options["my_likes_page"] ) ) {
                        $content = $content . do_shortcode('[z_my_likes_list]');
                    }
                }
            }
            return $content;

        }


        public function shortcode_like_button($atts, $content = null) {

            $id_post = get_the_ID();
            $like_hide = get_post_meta( $id_post, 'z_like_button_hide', true );
            if(isset($like_hide) && $like_hide == 1) {
                return;
            }
            
            $options = get_option( 'z_like_button_options' );
            $current_icon = (isset($options['icon'])) ? $options['icon'] : 'icon-heart';
            $color_inactive =  (isset($options['color_inactive'])) ? $options['color_inactive'] : '#989898';
            $color_active = (isset($options['color_active'])) ? $options['color_active'] : '#ef1d5f';    
            $all_likes = get_post_meta( $id_post, 'z_like_button_likes', true );
            $totals = get_post_meta( $id_post, 'z_like_button_totals', true );

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
            } elseif( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
                $ip_client = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
                $index = self::valueExistsByField( $ip_client, $all_likes, 'ip');
            } else {
                $index = false;
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
            $random_id = wp_rand(50000000,500000000);

            return '<div class="zLikeButton'. esc_html( $class ).'" style="--z-color-inactive: '. esc_attr( $color_inactive ).';--z-color-active: '. esc_attr( $color_active ).'"><input '.esc_html( $checked ).' id="post_'.esc_html( $id_post ).'_'.$random_id.'" type="checkbox" class="likeCheck"/>
                <label for="post_'.esc_html( $id_post ).'_'.$random_id.'" class="zlb-icon '. esc_attr($current_icon) .' zLikeLabel" aria-label="like this"></label><span class="likeCount">'. esc_html( $like) .'</span></div>';
        }
    


      public function shortcode_my_likes_list($atts, $content = null) {

            if( is_user_logged_in() ) {
                $user_id = get_current_user_id();
                $my_likes = get_user_meta( $user_id, 'z_like_button_likes', true );

                $html = '<ul class="zlb-my-likes-list">';
                if( ! empty( $my_likes ) ) {
                    foreach( $my_likes as $post_id ) {
                        $html .= '<li>';
                        $html .= '<a href="'.esc_url( get_the_permalink( intval( $post_id ) ) ).'">';
                        $html .= esc_html( get_the_title( intval( $post_id) ) );
                        $html .= '</a>';
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





        public function handle_ajax() {
            if( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'])), 'z-like-button' ) ) {
                die( 'Forbidden !');
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


                // 1. Set/unset personal likes
                if( ! empty( $user_id ) ) {
                    $my_likes = get_user_meta( $user_id, 'z_like_button_likes', true );
                    if( ! empty( $my_likes ) ) {
                        if( in_array($id_post, $my_likes) ) {
                            $keysToRemove = array_keys($my_likes, $id_post);
                            foreach($keysToRemove as $k) {
                                unset($my_likes[$k]);
                            }
                        } else {
                            $my_likes[] = $id_post;
                        }
                    } else {
                        $my_likes = array($id_post);
                    }
                    update_user_meta( $user_id, 'z_like_button_likes', $my_likes );
                }

                // 2. Set/unset total likes

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
            $message['user'] = $my_likes;
            echo json_encode($message);
            die();
        }




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



        public function like_button_boxes() {
            $post_types = $this->get_all_cpt();

            if(isset($post_types) && count($post_types) > 0) {
                add_meta_box( 'meta-box-z-like-button', '<span>' . esc_html__( 'Z Like Button', 'z-like-button' ) . '  <span class="zlb-icon icon-heart"></span></span> ', array($this, 'like_button_metabox'), $post_types, 'side' );
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
