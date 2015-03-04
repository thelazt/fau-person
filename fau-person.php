<?php

/**
 * Plugin Name: FAU Person
 * Description: Visitenkarten-Plugin für FAU Webauftritte
 * Version: 1.0.0
 * Author: Karin Kimpan
 * Author URI: http://blogs.fau.de/webworking/
 * License: GPLv2 or later
 */

/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

add_action('plugins_loaded', array('FAU_Person', 'instance'));

register_activation_hook(__FILE__, array('FAU_Person', 'activation'));
register_deactivation_hook(__FILE__, array('FAU_Person', 'deactivation'));

class FAU_Person {

    const version = '1.0.0';
    const option_name = '_fau_person';
    const version_option_name = '_fau_person_version';
    const textdomain = 'fau-person';
    const php_version = '5.3'; // Minimal erforderliche PHP-Version
    const wp_version = '4.0'; // Minimal erforderliche WordPress-Version

    protected static $post_types = 'person';
    
    public static $options;

    protected static $instance = null;

    public static function instance() {

        if (null == self::$instance) {
            self::$instance = new self;
            self::$instance->init();
        }

        return self::$instance;
    }

    private function init() {
        define('FAU_PERSON_ROOT', dirname(__FILE__));
        define('FAU_PERSON_FILE_PATH', FAU_PERSON_ROOT . '/' . basename(__FILE__));
        define('FAU_PERSON_URL', plugins_url('/', __FILE__));
        define('FAU_PERSON_TEXTDOMAIN', self::textdomain);
        require_once('metaboxes/fau-person-metaboxes.php');
        
        load_plugin_textdomain(self::textdomain, false, sprintf('%s/languages/', dirname(plugin_basename(__FILE__))));
        
        self::$options = (object) $this->get_options();
        
        add_action('init', array(__CLASS__, 'update_version'));
        add_action('init', array (__CLASS__, 'register_person_post_type'));
        add_action( 'init', array($this, 'register_persons_taxonomy') );
        add_action( 'init', array($this, 'be_initialize_cmb_meta_boxes'), 9999 );
        //add_action( 'restrict_manage_posts', array($this, 'person_restrict_manage_posts') );
        
        add_filter('single_template', array($this, 'include_template_function'));
        //add_filter('pre_get_posts', array($this, 'person_post_types_admin_order'));

        self::register_widgets();
        self::add_shortcodes();        
    }

    public static function activation() {

        self::version_compare();
        update_option(self::version_option_name, self::version);
        
        self::register_person_post_type();
        flush_rewrite_rules(); // Flush Rewrite-Regeln, so dass CPT und CT auf dem Front-End sofort vorhanden sind

        // CPT-Capabilities für die Administrator-Rolle zuweisen
        /*
        foreach(self::$post_types as $cap_type) {
            $caps = self::get_caps($cap_type);
            self::add_caps('administrator', $caps);
        }    
         */    
    }
    
    public static function deactivation() {       
        // CPT-Capabilities aus der Administrator-Rolle entfernen
        /*
        foreach(self::$post_types as $cap_type) {
            $caps = self::get_caps($cap_type);
            self::remove_caps('administrator', $caps);
        }
         */
    }

    private static function version_compare() {
        $error = '';

        if (version_compare(PHP_VERSION, self::php_version, '<')) {
            $error = sprintf(__('Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', self::textdomain), PHP_VERSION, self::php_version);
        }

        if (version_compare($GLOBALS['wp_version'], self::wp_version, '<')) {
            $error = sprintf(__('Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', self::textdomain), $GLOBALS['wp_version'], self::wp_version);
        }

        if (!empty($error)) {
            deactivate_plugins(plugin_basename(__FILE__), false, true);
            wp_die($error);
        }
    }

    public static function update_version() {
        if (get_option(self::version_option_name, null) != self::version)
            update_option(self::version_option_name, self::version);
    }

    private function default_options() {
        return array(); // Standard-Array für zukünftige Optionen
    }

    protected function get_options() {
        $defaults = $this->default_options();
        
        $options = (array) get_option(self::option_name);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return $options;
    }
    
    private static function get_caps($cap_type) {
        $caps = array(
            "edit_" . $cap_type,
            "read_" . $cap_type,
            "delete_" . $cap_type,
            "edit_" . $cap_type . "s",
            "edit_others_" . $cap_type . "s",
            "publish_" . $cap_type . "s",
            "read_private_" . $cap_type . "s",
            "delete_" . $cap_type . "s",
            "delete_private_" . $cap_type . "s",
            "delete_published_" . $cap_type . "s",
            "delete_others_" . $cap_type . "s",
            "edit_private_" . $cap_type . "s",
            "edit_published_" . $cap_type . "s",                
        );

        return $caps;
    }
    
    private static function add_caps($role, $caps) {
        $role = get_role($role);
        foreach($caps as $cap) {
            $role->add_cap($cap);
        }        
    }
    
    private static function remove_caps($role, $caps) {
        $role = get_role($role);
        foreach($caps as $cap) {
            $role->remove_cap($cap);
        }        
    }    
    
    private static function register_widgets() {
        //require_once('widgets/fau-person-widget.php');    
    }
    
    private static function add_shortcodes() {
        require_once('shortcodes/fau-person-shortcodes.php');            
    }

    public static function register_person_post_type() {
        require_once('posttypes/fau-person-posttype.php');
        register_post_type('person', $person_args);
    }

    public function register_persons_taxonomy() {
        register_taxonomy(
                'persons_category', //The name of the taxonomy. Name should be in slug form (must not contain capital letters or spaces).
                'person', //post type name
                array(
            'hierarchical' => true,
            'label' => __('Personen-Kategorien', FAU_PERSON_TEXTDOMAIN), //Display name
            'query_var' => true,
            'rewrite' => array(
                'slug' => 'persons', // This controls the base slug that will display before each term
                'with_front' => false // Don't display the category base before
            )
                )
        );
    }
    
    public function be_initialize_cmb_meta_boxes() {
        if ( !class_exists( 'cmb_Meta_Box' ) ) {
            require_once('cmb/init.php' );
        }
    }    

    public function person_restrict_manage_posts() {
        global $typenow;
        if ($typenow == "person") {
            $filters = get_object_taxonomies($typenow);
            foreach ($filters as $tax_slug) {
                $tax_obj = get_taxonomy($tax_slug);
                wp_dropdown_categories(array(
                    'show_option_all' => sprintf(__('Alle %s anzeigen', FAU_PERSON_TEXTDOMAIN), $tax_obj->label),
                    'taxonomy' => $tax_slug,
                    'name' => $tax_obj->name,
                    'orderby' => 'name',
                    'selected' => isset($_GET[$tax_slug]) ? $_GET[$tax_slug] : '',
                    'hierarchical' => $tax_obj->hierarchical,
                    'show_count' => true,
                    'hide_if_empty' => true
                ));
            }
        }
    }
    
    
    public function include_template_function($template_path) {
        global $post;
        if ($post->post_type == 'person') {
            //if (is_single()) {
                // checks if the file exists in the theme first,
                // otherwise serve the file from the plugin
                if ($theme_file = locate_template(array('single-person.php'))) {
                    $template_path = $theme_file;
                } else {
                    $template_path = FAU_PERSON_ROOT . '/templates/single-person.php';                    
                }
            //}
        }
        return $template_path;
    }

    public function person_post_types_admin_order($wp_query) {
        if (is_admin()) {
            $post_type = $wp_query->query['post_type'];
            if ($post_type == 'person') {
                if (!isset($wp_query->query['orderby'])) {
                    $wp_query->set('orderby', 'title');
                    $wp_query->set('order', 'ASC');
                }
            }
        }
    }    
}
