<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'BubbaHubPlugin_Meta' ) ) {
class BubbaHubPlugin_Meta {
    public static function boot() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
        add_action( 'save_post_bh_group', array( __CLASS__, 'save' ), 10, 2 );
        add_action( 'init', array( __CLASS__, 'register_meta' ) );
    }
    private static function fields() {
        return array(
            'street'=>'Street / Address','city'=>'City / Town','region'=>'Region / County','zip'=>'Postcode',
            'latitude'=>'Latitude','longitude'=>'Longitude','timetable'=>'Timetable','business_hours'=>'Business Hours',
            'website'=>'Website','email'=>'Email','facebook'=>'Facebook','instagram'=>'Instagram','price'=>'Price',
            'term_time'=>'Term Time','age_range'=>'Age Range','session_length'=>'Session Length','day'=>'Days',
            'category'=>'Category','tags'=>'Tags','images'=>'Images / Gallery','sen'=>'SEN','featured'=>'Featured',
            'is_free'=>'Free','google_id'=>'Google ID','google_wp_username'=>'Google WP Username',
            'organizer_username'=>'Organizer Username','google_last_sync'=>'Last Google Sync'
        );
    }
    public static function register_meta() {
        foreach ( array_keys(self::fields()) as $field ) {
            register_post_meta('bh_group','_bubbahub_'.$field,array(
                'type'=>'string','single'=>true,'show_in_rest'=>true,
                'auth_callback'=>function(){ return current_user_can('edit_posts'); },
            ));
        }
    }
    public static function add_meta_box() {
        add_meta_box('bubbahub_listing_details','BubbaHub Listing Details',array(__CLASS__,'render'),'bh_group','normal','high');
    }
    public static function render($post) {
        wp_nonce_field('bubbahub_listing_meta','bubbahub_listing_meta_nonce');
        echo '<table class="form-table"><tbody>';
        foreach(self::fields() as $key=>$label){
            $value=get_post_meta($post->ID,'_bubbahub_'.$key,true);
            echo '<tr><th><label for="bubbahub_'.$key.'">'.esc_html($label).'</label></th><td>';
            if(in_array($key,array('timetable','business_hours','images'),true)) echo '<textarea class="large-text" rows="3" id="bubbahub_'.$key.'" name="bubbahub_'.$key.'">'.esc_textarea($value).'</textarea>';
            elseif(in_array($key,array('featured','is_free'),true)) echo '<label><input type="checkbox" id="bubbahub_'.$key.'" name="bubbahub_'.$key.'" value="1" '.checked($value,'1',false).'> Yes</label>';
            else echo '<input class="regular-text" type="text" id="bubbahub_'.$key.'" name="bubbahub_'.$key.'" value="'.esc_attr($value).'">';
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }
    public static function save($post_id,$post) {
        if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE) return;
        if(!isset($_POST['bubbahub_listing_meta_nonce'])||!wp_verify_nonce($_POST['bubbahub_listing_meta_nonce'],'bubbahub_listing_meta')) return;
        if(!current_user_can('edit_post',$post_id)) return;
        foreach(self::fields() as $key=>$label){
            $meta='_bubbahub_'.$key;
            if(in_array($key,array('featured','is_free'),true)){update_post_meta($post_id,$meta,isset($_POST['bubbahub_'.$key])?'1':'0');continue;}
            if(array_key_exists('bubbahub_'.$key,$_POST)) update_post_meta($post_id,$meta,sanitize_textarea_field(wp_unslash($_POST['bubbahub_'.$key])));
        }
    }
}
}
