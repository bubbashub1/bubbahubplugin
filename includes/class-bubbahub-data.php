<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class BubbaHub_Data {
    const LISTING = 'bubba_listing';
    const EVENT = 'bubba_event';

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_content_types' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
    }

    public static function register_content_types() {
        register_post_type( self::LISTING, array(
            'labels' => array( 'name'=>'Bubba Hub Listings', 'singular_name'=>'Bubba Hub Listing' ),
            'public'=>true, 'show_in_rest'=>true, 'menu_icon'=>'dashicons-groups',
            'supports'=>array('title','editor','thumbnail','author','excerpt'), 'has_archive'=>true,
            'rewrite'=>array('slug'=>'activities'),
        ) );
        register_post_type( self::EVENT, array(
            'labels'=>array('name'=>'Bubba Hub Events','singular_name'=>'Bubba Hub Event'),
            'public'=>true,'show_in_rest'=>true,'menu_icon'=>'dashicons-calendar-alt',
            'supports'=>array('title','editor','thumbnail','author','excerpt'),'has_archive'=>true,
            'rewrite'=>array('slug'=>'events'),
        ) );
        foreach ( array(
            'bubba_category'=>array('Categories','Category'), 'bubba_location'=>array('Locations','Location'),
            'bubba_age'=>array('Age Ranges','Age Range'), 'bubba_day'=>array('Activity Days','Activity Day'),
        ) as $taxonomy=>$labels ) {
            register_taxonomy($taxonomy,array(self::LISTING,self::EVENT),array(
                'labels'=>array('name'=>$labels[0],'singular_name'=>$labels[1]),'public'=>true,
                'show_in_rest'=>true,'hierarchical'=>true,
            ));
        }
    }

    public static function activate() {
        self::register_content_types(); self::create_tables(); flush_rewrite_rules();
    }
    public static function deactivate() { flush_rewrite_rules(); }

    public static function create_tables() {
        global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $c=$wpdb->get_charset_collate(); $p=$wpdb->prefix.'bubbahub_';
        $tables=array();
        $tables[]="CREATE TABLE {$p}bookings (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,user_id bigint(20) unsigned NOT NULL DEFAULT 0,listing_id bigint(20) unsigned NOT NULL DEFAULT 0,event_id bigint(20) unsigned NOT NULL DEFAULT 0,quantity int unsigned NOT NULL DEFAULT 1,status varchar(30) NOT NULL DEFAULT 'pending',total decimal(12,2) NOT NULL DEFAULT 0.00,currency char(3) NOT NULL DEFAULT 'GBP',payment_id bigint(20) unsigned NOT NULL DEFAULT 0,booking_data longtext NULL,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY(id),KEY user_id(user_id),KEY listing_id(listing_id),KEY event_id(event_id),KEY status(status)) $c;";
        $tables[]="CREATE TABLE {$p}payments (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,user_id bigint(20) unsigned NOT NULL DEFAULT 0,leader_user_id bigint(20) unsigned NOT NULL DEFAULT 0,booking_id bigint(20) unsigned NOT NULL DEFAULT 0,provider varchar(30) NOT NULL DEFAULT '',provider_reference varchar(191) NOT NULL DEFAULT '',status varchar(30) NOT NULL DEFAULT 'pending',gross decimal(12,2) NOT NULL DEFAULT 0.00,third_party_fee decimal(12,2) NOT NULL DEFAULT 0.00,net decimal(12,2) NOT NULL DEFAULT 0.00,currency char(3) NOT NULL DEFAULT 'GBP',metadata longtext NULL,created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY(id),KEY user_id(user_id),KEY leader_user_id(leader_user_id),KEY booking_id(booking_id),KEY provider_reference(provider_reference),KEY status(status)) $c;";
        $tables[]="CREATE TABLE {$p}wallets (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,user_id bigint(20) unsigned NOT NULL,balance decimal(12,2) NOT NULL DEFAULT 0.00,currency char(3) NOT NULL DEFAULT 'GBP',payout_method varchar(30) NOT NULL DEFAULT 'manual',payout_threshold decimal(12,2) NOT NULL DEFAULT 0.00,payout_delay_days int unsigned NOT NULL DEFAULT 0,stripe_account_id varchar(191) NOT NULL DEFAULT '',paypal_account varchar(191) NOT NULL DEFAULT '',bank_reference varchar(191) NOT NULL DEFAULT '',created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY(id),UNIQUE KEY user_id(user_id)) $c;";
        $tables[]="CREATE TABLE {$p}wallet_transactions (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,wallet_id bigint(20) unsigned NOT NULL,user_id bigint(20) unsigned NOT NULL,payment_id bigint(20) unsigned NOT NULL DEFAULT 0,type varchar(30) NOT NULL,amount decimal(12,2) NOT NULL DEFAULT 0.00,balance_after decimal(12,2) NOT NULL DEFAULT 0.00,reference varchar(191) NOT NULL DEFAULT '',description text NULL,created_at datetime NOT NULL,PRIMARY KEY(id),KEY wallet_id(wallet_id),KEY user_id(user_id),KEY payment_id(payment_id),KEY type(type)) $c;";
        $tables[]="CREATE TABLE {$p}payouts (id bigint(20) unsigned NOT NULL AUTO_INCREMENT,user_id bigint(20) unsigned NOT NULL,wallet_id bigint(20) unsigned NOT NULL,amount decimal(12,2) NOT NULL DEFAULT 0.00,method varchar(30) NOT NULL DEFAULT '',status varchar(30) NOT NULL DEFAULT 'pending',provider_reference varchar(191) NOT NULL DEFAULT '',requested_at datetime NOT NULL,processed_at datetime NULL,metadata longtext NULL,PRIMARY KEY(id),KEY user_id(user_id),KEY wallet_id(wallet_id),KEY status(status)) $c;";
        foreach($tables as $sql) dbDelta($sql); update_option('bubbahub_schema_version','1.0.0');
    }

    public static function register_rest() {
        register_rest_route('bubbahub/v1','/events',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'events'),'permission_callback'=>'__return_true'));
        register_rest_route('bubbahub/v1','/events/(?P<id>\d+)',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'event'),'permission_callback'=>'__return_true'));
        register_rest_route('bubbahub/v1','/events',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array(__CLASS__,'save_event'),'permission_callback'=>function(){return current_user_can('edit_posts');}));
        register_rest_route('bubbahub/v1','/me',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'me'),'permission_callback'=>function(){return is_user_logged_in();}));
        register_rest_route('bubbahub/v1','/me',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array(__CLASS__,'save_me'),'permission_callback'=>function(){return is_user_logged_in();}));
    }

    public static function event_item($post) {
        $id=$post->ID; return array(
            'id'=>$id,'title'=>get_the_title($id),'description'=>apply_filters('the_content',$post->post_content),'url'=>get_permalink($id),
            'image'=>get_the_post_thumbnail_url($id,'large'),'startDate'=>get_post_meta($id,'start_date',true),'endDate'=>get_post_meta($id,'end_date',true),
            'startTime'=>get_post_meta($id,'start_time',true),'endTime'=>get_post_meta($id,'end_time',true),'venue'=>get_post_meta($id,'venue',true),
            'address'=>get_post_meta($id,'address',true),'city'=>get_post_meta($id,'city',true),'region'=>get_post_meta($id,'region',true),
            'postcode'=>get_post_meta($id,'postcode',true),'price'=>get_post_meta($id,'price',true),'capacity'=>(int)get_post_meta($id,'capacity',true),
            'bookingUrl'=>get_post_meta($id,'booking_url',true),'organizer'=>(int)$post->post_author,
        );
    }
    public static function events($request) {
        $args=array('post_type'=>self::EVENT,'post_status'=>'publish','posts_per_page'=>min(100,max(1,absint($request->get_param('per_page')?:50))),'paged'=>max(1,absint($request->get_param('page')?:1)));
        if($request->get_param('search'))$args['s']=sanitize_text_field($request->get_param('search')); $q=new WP_Query($args);
        return rest_ensure_response(array_map(array(__CLASS__,'event_item'),$q->posts));
    }
    public static function event($request) {
        $post=get_post(absint($request['id']));
        if(!$post||self::EVENT!==$post->post_type||'publish'!==$post->post_status)return new WP_Error('not_found','Event not found',array('status'=>404));
        return rest_ensure_response(self::event_item($post));
    }
    public static function save_event($request) {
        $d=$request->get_json_params(); $id=absint($d['id']??0);
        if($id&&!current_user_can('edit_post',$id))return new WP_Error('forbidden','You cannot edit this event',array('status'=>403));
        $post=array('post_type'=>self::EVENT,'post_title'=>sanitize_text_field($d['title']??'Untitled Event'),'post_content'=>wp_kses_post($d['description']??''),'post_status'=>current_user_can('publish_posts')?'publish':'draft');
        if($id)$post['ID']=$id;else$post['post_author']=get_current_user_id(); $saved=$id?wp_update_post(wp_slash($post),true):wp_insert_post(wp_slash($post),true);
        if(is_wp_error($saved))return $saved;
        foreach(array('startDate'=>'start_date','endDate'=>'end_date','startTime'=>'start_time','endTime'=>'end_time','venue'=>'venue','address'=>'address','city'=>'city','region'=>'region','postcode'=>'postcode','price'=>'price','capacity'=>'capacity','bookingUrl'=>'booking_url') as $s=>$m)if(array_key_exists($s,$d))update_post_meta($saved,$m,is_scalar($d[$s])?sanitize_text_field((string)$d[$s]):$d[$s]);
        return rest_ensure_response(array('success'=>true,'event'=>self::event_item(get_post($saved))));
    }
    public static function me() {
        $u=wp_get_current_user(); $meta=get_user_meta($u->ID); $clean=array();
        foreach($meta as $k=>$v)$clean[$k]=count($v)===1?maybe_unserialize($v[0]):array_map('maybe_unserialize',$v);
        return rest_ensure_response(array('id'=>$u->ID,'username'=>$u->user_login,'email'=>$u->user_email,'displayName'=>$u->display_name,'roles'=>$u->roles,'meta'=>$clean));
    }
    public static function save_me($request) {
        $uid=get_current_user_id(); $d=$request->get_json_params();
        if(isset($d['displayName']))wp_update_user(array('ID'=>$uid,'display_name'=>sanitize_text_field($d['displayName'])));
        foreach(array('first_name','last_name','nickname','description','phone','city','region','postcode','user_type','children','preferences','avatar_url') as $k)if(array_key_exists($k,$d))update_user_meta($uid,$k,is_scalar($d[$k])?sanitize_text_field((string)$d[$k]):$d[$k]);
        return self::me();
    }
}
BubbaHub_Data::init();
