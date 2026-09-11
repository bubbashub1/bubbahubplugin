<?php
/**
 * BubbaHub native backend.
 * WordPress is the source of truth for listings, events and users.
 * Google Sheets is an import/sync source only.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BubbaHubPlugin_Backend' ) ) {
class BubbaHubPlugin_Backend {
    const VERSION = '1.0.0';
    const DB_OPTION = 'bubbahub_plugin_backend_version';
    const GOOGLE_OPTION = 'bubbahub_plugin_google_sync';
    const MAIN_URL = 'https://script.google.com/macros/s/AKfycbwL70V_m3FIoxNTuc0MiI_tdAIfMlJrt0Zw62vnefNwctEmg5Pe93UhFaWzaRl3gTv1/exec';
    const TERM_URL = 'https://script.google.com/macros/s/AKfycbzxEfyvcyb9oJ25nvWgrJqRuuvoh5tWoTp5uS1j1R8SUFJgNSVauawY35cVaWo6LBSH/exec';

    public static function boot() {
        add_action( 'init', array( __CLASS__, 'register_types' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest' ) );
        add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 30 );
        add_action( 'admin_post_bubbahub_plugin_import', array( __CLASS__, 'admin_import' ) );
        add_action( 'admin_post_bubbahub_plugin_migrate', array( __CLASS__, 'admin_migrate' ) );
    }

    public static function activate() {
        self::register_types();
        self::create_tables();
        self::create_roles();
        flush_rewrite_rules();
    }

    public static function deactivate() { flush_rewrite_rules(); }

    public static function register_types() {
        register_post_type( 'bh_group', array(
            'labels' => array( 'name'=>'BubbaHub Listings', 'singular_name'=>'BubbaHub Listing', 'add_new'=>'Add Listing', 'add_new_item'=>'Add BubbaHub Listing', 'edit_item'=>'Edit BubbaHub Listing' ),
            'public'=>true, 'show_in_rest'=>true, 'menu_icon'=>'dashicons-groups', 'supports'=>array('title','editor','thumbnail','author','excerpt'),
            'has_archive'=>true, 'rewrite'=>array('slug'=>'groups'), 'capability_type'=>'post', 'map_meta_cap'=>true,
        ) );
        register_post_type( 'bh_event', array(
            'labels' => array( 'name'=>'BubbaHub Events', 'singular_name'=>'BubbaHub Event', 'add_new_item'=>'Add BubbaHub Event', 'edit_item'=>'Edit BubbaHub Event' ),
            'public'=>true, 'show_in_rest'=>true, 'menu_icon'=>'dashicons-calendar-alt', 'supports'=>array('title','editor','thumbnail','author','excerpt'),
            'has_archive'=>true, 'rewrite'=>array('slug'=>'events'), 'capability_type'=>'post', 'map_meta_cap'=>true,
        ) );
        foreach ( array('bh_category'=>'Categories','bh_location'=>'Locations','bh_age'=>'Age Ranges','bh_day'=>'Days') as $tax=>$label ) {
            register_taxonomy( $tax, array('bh_group','bh_event'), array('label'=>$label,'public'=>true,'show_in_rest'=>true,'hierarchical'=>true,'rewrite'=>array('slug'=>sanitize_title($label))) );
        }
    }

    private static function create_roles() {
        if ( ! get_role('bubbahub_parent') ) add_role('bubbahub_parent','BubbaHub Parent',array('read'=>true));
        if ( ! get_role('bubbahub_leader') ) add_role('bubbahub_leader','BubbaHub Leader',array('read'=>true,'edit_posts'=>true,'upload_files'=>true));
        $leader=get_role('bubbahub_leader');
        if($leader){$leader->add_cap('edit_bubbahub_items');$leader->add_cap('publish_posts');}
        $admin=get_role('administrator'); if($admin)$admin->add_cap('manage_bubbahub');
    }

    private static function create_tables() {
        global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php'; $c=$wpdb->get_charset_collate();
        $sql=array(
            "CREATE TABLE {$wpdb->prefix}bubbahub_bookings (id bigint unsigned NOT NULL AUTO_INCREMENT,user_id bigint unsigned NOT NULL,listing_id bigint unsigned NOT NULL,event_id bigint unsigned DEFAULT 0,status varchar(30) NOT NULL DEFAULT 'pending',quantity int unsigned NOT NULL DEFAULT 1,total decimal(12,2) NOT NULL DEFAULT 0,currency varchar(3) NOT NULL DEFAULT 'GBP',created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY user_id(user_id),KEY listing_id(listing_id),KEY event_id(event_id)) $c;",
            "CREATE TABLE {$wpdb->prefix}bubbahub_payments (id bigint unsigned NOT NULL AUTO_INCREMENT,booking_id bigint unsigned DEFAULT 0,user_id bigint unsigned NOT NULL,leader_id bigint unsigned DEFAULT 0,provider varchar(30) NOT NULL DEFAULT '',provider_ref varchar(190) DEFAULT '',amount decimal(12,2) NOT NULL DEFAULT 0,currency varchar(3) NOT NULL DEFAULT 'GBP',status varchar(30) NOT NULL DEFAULT 'pending',created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,paid_at datetime NULL,PRIMARY KEY(id),KEY booking_id(booking_id),KEY user_id(user_id),KEY leader_id(leader_id),KEY provider_ref(provider_ref)) $c;",
            "CREATE TABLE {$wpdb->prefix}bubbahub_wallets (id bigint unsigned NOT NULL AUTO_INCREMENT,user_id bigint unsigned NOT NULL,balance decimal(12,2) NOT NULL DEFAULT 0,currency varchar(3) NOT NULL DEFAULT 'GBP',payout_method varchar(30) DEFAULT 'manual',payout_threshold decimal(12,2) DEFAULT 0,payout_interval varchar(30) DEFAULT 'manual',created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY user_id(user_id)) $c;",
            "CREATE TABLE {$wpdb->prefix}bubbahub_wallet_transactions (id bigint unsigned NOT NULL AUTO_INCREMENT,wallet_id bigint unsigned NOT NULL,user_id bigint unsigned NOT NULL,type varchar(30) NOT NULL,reference_type varchar(30) DEFAULT '',reference_id bigint unsigned DEFAULT 0,amount decimal(12,2) NOT NULL DEFAULT 0,fee decimal(12,2) NOT NULL DEFAULT 0,net decimal(12,2) NOT NULL DEFAULT 0,currency varchar(3) NOT NULL DEFAULT 'GBP',status varchar(30) NOT NULL DEFAULT 'pending',description text NULL,created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY wallet_id(wallet_id),KEY user_id(user_id),KEY reference(reference_type,reference_id)) $c;",
            "CREATE TABLE {$wpdb->prefix}bubbahub_payouts (id bigint unsigned NOT NULL AUTO_INCREMENT,user_id bigint unsigned NOT NULL,amount decimal(12,2) NOT NULL DEFAULT 0,currency varchar(3) NOT NULL DEFAULT 'GBP',method varchar(30) NOT NULL DEFAULT 'manual',status varchar(30) NOT NULL DEFAULT 'pending',provider_ref varchar(190) DEFAULT '',created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,processed_at datetime NULL,PRIMARY KEY(id),KEY user_id(user_id)) $c;"
        ); foreach($sql as $s) dbDelta($s); update_option(self::DB_OPTION,self::VERSION,false);
    }

    public static function admin_menu() {
        add_submenu_page('edit.php?post_type=bh_group','Import BubbaHub Data','Import / Sync Data','manage_bubbahub','bubbahub-plugin-import',array(__CLASS__,'admin_page'));
    }
    public static function admin_page() {
        if(!current_user_can('manage_bubbahub'))return;
        $s=self::settings(); $last=get_option('bubbahub_plugin_last_import',array());
        echo '<div class="wrap"><h1>BubbaHub Data</h1><p><strong>WordPress is the live source of truth.</strong> Google Sheets is used only to import/synchronise data.</p>';
        if(!empty($_GET['imported'])) echo '<div class="notice notice-success"><p>Imported '.absint($_GET['imported']).' listings into WordPress.</p></div>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('bubbahub_plugin_import','bubbahub_plugin_nonce',true,false).'<input type="hidden" name="action" value="bubbahub_plugin_import"><table class="form-table"><tr><th>Listings source</th><td><input class="large-text code" name="main_url" value="'.esc_attr($s['main_url']).'"></td></tr><tr><th>Term-time source</th><td><input class="large-text code" name="term_url" value="'.esc_attr($s['term_url']).'"></td></tr></table><p><button class="button button-primary">Import / Update Listings</button></p></form>';
        echo '<p>Existing WordPress listing IDs are preserved. Matching uses Google ID first, then WordPress username, then title as a final fallback.</p>';
        if($last) echo '<p>Last import: '.esc_html($last['time']??'').' — '.absint($last['count']??0).' records.</p>';
        echo '</div>';
    }
    private static function settings(){return wp_parse_args(get_option(self::GOOGLE_OPTION,array()),array('main_url'=>self::MAIN_URL,'term_url'=>self::TERM_URL));}
    public static function admin_import(){if(!current_user_can('manage_bubbahub')||!check_admin_referer('bubbahub_plugin_import','bubbahub_plugin_nonce'))wp_die('Permission denied.');$s=self::settings();$s['main_url']=esc_url_raw(wp_unslash($_POST['main_url']??$s['main_url']));$s['term_url']=esc_url_raw(wp_unslash($_POST['term_url']??$s['term_url']));update_option(self::GOOGLE_OPTION,$s,false);$n=self::import_google();wp_safe_redirect(add_query_arg(array('post_type'=>'bh_group','page'=>'bubbahub-plugin-import','imported'=>$n),admin_url('edit.php')));exit;}
    public static function admin_migrate(){if(!current_user_can('manage_bubbahub'))wp_die('Permission denied.');$n=self::migrate_existing();wp_safe_redirect(add_query_arg(array('post_type'=>'bh_group','page'=>'bubbahub-plugin-import','imported'=>$n),admin_url('edit.php')));exit;}

    private static function key($v){return sanitize_title_with_dashes(strtolower(trim((string)$v)));}
    private static function val($r,$keys){foreach((array)$keys as $k){$k=self::key($k);if(array_key_exists($k,$r)&&$r[$k]!==''&&$r[$k]!==null)return$r[$k];}return'';}
    private static function rows($d){foreach(array('data','rows','groups','listings') as $c)if(isset($d[$c])&&is_array($d[$c])){$d=$d[$c];break;}if(!is_array($d))return array();$out=array();foreach($d as $r){if(!is_array($r))continue;$x=array();foreach($r as $k=>$v)$x[self::key($k)]=is_scalar($v)?trim((string)$v):$v;$out[]=$x;}return$out;}
    private static function get_json($url){$r=wp_remote_get($url,array('timeout'=>45,'redirection'=>5,'headers'=>array('Accept'=>'application/json'),'user-agent'=>'BubbaHub-WordPress/'.BUBBAHUB_APP_VERSION));if(is_wp_error($r))return array();$code=wp_remote_retrieve_response_code($r);$d=json_decode(wp_remote_retrieve_body($r),true);return$code>=200&&$code<300?self::rows($d):array();}
    private static function merge($a,$b){$index=array();foreach($b as$r){$id=self::val($r,array('id','listing_id','group_id'));$n=self::val($r,array('name','title','group_name'));if($id!=='')$index['i:'.strtolower($id)]=$r;if($n!=='')$index['n:'.sanitize_title($n)]=$r;}foreach($a as&$r){$id=self::val($r,array('id','listing_id','group_id'));$n=self::val($r,array('name','title','group_name'));$e=$id!==''?($index['i:'.strtolower($id)]??array()):($index['n:'.sanitize_title($n)]??array());if($e)$r=array_merge($e,$r);}return$a;}

    public static function import_google(){
        $s=self::settings();$rows=self::get_json($s['main_url']);if(!$rows)return 0;$rows=self::merge($rows,self::get_json($s['term_url']));$count=0;
        foreach($rows as $r){$title=sanitize_text_field(self::val($r,array('title','name','group_name','listing_name')));if(!$title)continue;$gid=sanitize_text_field(self::val($r,array('id','listing_id','group_id')));$username=sanitize_user(self::val($r,array('wp_username','organizer_username','organiser_username','organizer','organiser')));$id=0;
            if($gid)$id=absint(get_posts(array('post_type'=>'bh_group','post_status'=>'any','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_bubbahub_google_id','meta_value'=>$gid))[0]??0);
            if(!$id&&$username)$id=absint(get_posts(array('post_type'=>'bh_group','post_status'=>'any','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_bubbahub_google_wp_username','meta_value'=>$username))[0]??0);
            if(!$id)$id=absint(get_page_by_title($title,OBJECT,'bh_group')->ID??0);
            $p=array('post_title'=>$title,'post_type'=>'bh_group','post_status'=>'publish','post_content'=>wp_kses_post(self::val($r,array('description','content','post_content','about'))));if($username){$u=get_user_by('login',$username);if($u)$p['post_author']=$u->ID;}
            if($id){$p['ID']=$id;wp_update_post(wp_slash($p));}else{$id=wp_insert_post(wp_slash($p),true);if(is_wp_error($id))continue;}
            if($gid)update_post_meta($id,'_bubbahub_google_id',$gid);if($username){update_post_meta($id,'_bubbahub_google_wp_username',$username);update_post_meta($id,'_bubbahub_organizer_username',$username);}
            $map=array('street'=>array('address','street','address1'),'city'=>array('city','town','postal_town'),'region'=>array('region','county'),'zip'=>array('zip','postcode','post_code','postal_code'),'latitude'=>array('manual_lat','latitude','lat'),'longitude'=>array('manual_lng','longitude','lng','lon'),'timetable'=>array('timetable','schedule'),'business_hours'=>array('openinghours','opening hours','business_hours','opening_hours'),'website'=>array('website','url'),'email'=>array('email'),'facebook'=>array('facebook'),'instagram'=>array('instagram'),'price'=>array('price','cost'),'term_time'=>array('term time','term_time','termtime','term-time'),'age_range'=>array('age range','age_range'),'session_length'=>array('session length','session_length'),'day'=>array('day','days'),'category'=>array('category','categories'),'tags'=>array('tags','tag'),'images'=>array('images','image','gallery','gallery_images'),'sen'=>array('sen','special educational needs'));
            foreach($map as $meta=>$keys){$v=self::val($r,$keys);if($v!=='' )update_post_meta($id,'_bubbahub_'.$meta,is_scalar($v)?sanitize_text_field((string)$v):$v);}
            foreach(array('featured'=>'featured','is_free'=>'is_free') as $meta=>$key){$v=self::val($r,array($key,'is_'.$key));if($v!=='')update_post_meta($id,'_bubbahub_'.$meta,in_array(strtolower((string)$v),array('1','true','yes','y','on'),true)?'1':'0');}
            self::set_tax($id,'bh_location',array(self::val($r,array('city','town')),self::val($r,array('region','county'))));self::set_tax($id,'bh_age',self::val($r,array('age range','age_range')));self::set_tax($id,'bh_day',self::val($r,array('day','days')));self::set_tax($id,'bh_category',self::val($r,array('category','categories')));update_post_meta($id,'_bubbahub_google_last_sync',current_time('mysql'));$count++;
        }
        update_option('bubbahub_plugin_last_import',array('time'=>current_time('mysql'),'count'=>$count),false);return$count;
    }
    private static function set_tax($id,$tax,$value){if(!$value)return;$parts=is_array($value)?$value:preg_split('/[,;]+/',(string)$value);$parts=array_values(array_filter(array_map('trim',$parts)));if($parts)wp_set_object_terms($id,$parts,$tax,false);}

    public static function migrate_existing(){
        $q=new WP_Query(array('post_type'=>array('bubba_listing','listing','directorist_listing','gd_place','post'),'post_status'=>'any','posts_per_page'=>-1,'meta_query'=>array(array('key'=>'_bubbahub_google_id','compare'=>'EXISTS'))));$n=0;foreach($q->posts as$p){if($p->post_type==='bh_group')continue;$r=wp_update_post(array('ID'=>$p->ID,'post_type'=>'bh_group'),true);if(!is_wp_error($r)){$n++;update_post_meta($p->ID,'_bubbahub_migrated_from',$p->post_type);}}flush_rewrite_rules(false);return$n;
    }

    public static function register_rest(){
        register_rest_route('bubbahub/v1','/listings',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'rest_listings'),'permission_callback'=>'__return_true'));
        register_rest_route('bubbahub/v1','/events',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'rest_events'),'permission_callback'=>'__return_true'));
        register_rest_route('bubbahub/v1','/me',array('methods'=>WP_REST_Server::READABLE,'callback'=>array(__CLASS__,'rest_me'),'permission_callback'=>function(){return is_user_logged_in();}));
        register_rest_route('bubbahub/v1','/profile',array('methods'=>array('GET','POST'),'callback'=>array(__CLASS__,'rest_profile'),'permission_callback'=>function(){return is_user_logged_in();}));
    }
    private static function api_post($p){$terms=array();foreach(get_object_taxonomies($p->post_type) as$t)$terms=array_merge($terms,wp_get_object_terms($p->ID,$t,array('fields'=>'names')));$m=array();foreach(get_post_meta($p->ID) as$k=>$v)$m[$k]=count($v)===1?$v[0]:$v;return array('ID'=>$p->ID,'id'=>(string)$p->ID,'title'=>get_the_title($p),'description'=>apply_filters('the_content',$p->post_content),'url'=>get_permalink($p),'postType'=>$p->post_type,'image'=>get_the_post_thumbnail_url($p,'large'),'tags'=>$terms,'city'=>get_post_meta($p->ID,'_bubbahub_city',true),'region'=>get_post_meta($p->ID,'_bubbahub_region',true),'Address'=>get_post_meta($p->ID,'_bubbahub_street',true),'zip'=>get_post_meta($p->ID,'_bubbahub_zip',true),'ageRange'=>get_post_meta($p->ID,'_bubbahub_age_range',true),'sessionLength'=>get_post_meta($p->ID,'_bubbahub_session_length',true),'termTime'=>get_post_meta($p->ID,'_bubbahub_term_time',true),'timetable'=>get_post_meta($p->ID,'_bubbahub_timetable',true),'price'=>get_post_meta($p->ID,'_bubbahub_price',true),'isFeatured'=>(bool)get_post_meta($p->ID,'_bubbahub_featured',true),'openingHours'=>get_post_meta($p->ID,'_bubbahub_business_hours',true),'website'=>get_post_meta($p->ID,'_bubbahub_website',true),'email'=>get_post_meta($p->ID,'_bubbahub_email',true),'facebook'=>get_post_meta($p->ID,'_bubbahub_facebook',true),'instagram'=>get_post_meta($p->ID,'_bubbahub_instagram',true),'lat'=>(float)get_post_meta($p->ID,'_bubbahub_latitude',true),'lng'=>(float)get_post_meta($p->ID,'_bubbahub_longitude',true),'meta'=>$m);}
    public static function rest_listings($r){$args=array('post_type'=>'bh_group','post_status'=>'publish','posts_per_page'=>min(100,max(1,absint($r->get_param('per_page')?:50))),'paged'=>max(1,absint($r->get_param('page')?:1)),'orderby'=>'date','order'=>'DESC');if($r->get_param('search'))$args['s']=sanitize_text_field($r->get_param('search'));$q=new WP_Query($args);$out=array();foreach($q->posts as$p)$out[]=self::api_post($p);return rest_ensure_response(array('data'=>$out,'listings'=>$out,'results'=>$out,'total'=>(int)$q->found_posts,'pages'=>(int)$q->max_num_pages));}
    public static function rest_events($r){$q=new WP_Query(array('post_type'=>'bh_event','post_status'=>'publish','posts_per_page'=>100,'orderby'=>'date','order'=>'ASC'));$out=array();foreach($q->posts as$p)$out[]=self::api_post($p);return rest_ensure_response(array('data'=>$out,'events'=>$out,'results'=>$out,'total'=>(int)$q->found_posts));}
    public static function rest_me(){ $u=wp_get_current_user();return rest_ensure_response(array('user'=>array('id'=>$u->ID,'name'=>$u->display_name,'email'=>$u->user_email,'roles'=>$u->roles,'type'=>get_user_meta($u->ID,'bubbahub_user_type',true),'plan'=>get_user_meta($u->ID,'bubbahub_plan_id',true)))); }
    public static function rest_profile($r){$uid=get_current_user_id();if($r->get_method()==='POST'){foreach(array('location','lat','lng','due_date','interests') as$k)if($r->get_param($k)!==null)update_user_meta($uid,'bubbahub_'.$k,is_array($r->get_param($k))?$r->get_param($k):sanitize_text_field($r->get_param($k)));}return rest_ensure_response(array('location'=>get_user_meta($uid,'bubbahub_location',true),'lat'=>get_user_meta($uid,'bubbahub_lat',true),'lng'=>get_user_meta($uid,'bubbahub_lng',true),'due_date'=>get_user_meta($uid,'bubbahub_due_date',true),'interests'=>get_user_meta($uid,'bubbahub_interests',true)));}
}
BubbaHubPlugin_Backend::boot();
}
