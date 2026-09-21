<?php
defined('ABSPATH') || exit;
final class BHPlugin_My_Hub {
 public static function init():void {
  add_action('init',[__CLASS__,'register_child_profile_type']);
  add_action('wp_enqueue_scripts',[__CLASS__,'enqueue']);
  add_shortcode('bh_my_hub',[__CLASS__,'render']);
  add_action('wp_ajax_bh_toggle_favourite',[__CLASS__,'toggle_favourite']);
  add_action('wp_ajax_bh_toggle_visited',[__CLASS__,'toggle_visited']);
  add_action('wp_ajax_bh_toggle_compare',[__CLASS__,'toggle_compare']);
 }
 public static function register_child_profile_type():void {
  register_post_type('bh_child_profile',[
   'labels'=>['name'=>'Child Profiles','singular_name'=>'Child Profile','add_new_item'=>'Add Child Profile','edit_item'=>'Edit Child Profile'],
   'public'=>false,'show_ui'=>false,'show_in_rest'=>false,'supports'=>['title','author'],'capability_type'=>'post','map_meta_cap'=>true
  ]);
 }
 public static function enqueue():void {
  wp_enqueue_script('bhplugin-my-hub',BHPLUGIN_URL.'assets/js/my-hub.js',[],BHPLUGIN_VERSION,true);
  wp_localize_script('bhplugin-my-hub','BHMyHub',['ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('bh_my_hub')]);
 }
 public static function render():string {
  if(!is_user_logged_in()) return '<div class="bh-my-hub"><p>Please log in to view My Hub.</p></div>';
  $user_id=get_current_user_id();
  $children=get_posts(['post_type'=>'bh_child_profile','author'=>$user_id,'posts_per_page'=>-1,'post_status'=>'private']);
  $favourites=self::ids('bh_favourites',$user_id);
  $visited=self::ids('bh_visited',$user_id);
  $compare=self::ids('bh_compare',$user_id);
  ob_start(); ?>
  <section class="bh-my-hub">
   <header class="bh-my-hub__header"><h2>My Hub</h2><p>Your personal family area for saving and organising activities.</p></header>
   <div class="bh-my-hub__grid">
    <article class="bh-hub-panel"><h3>Child Profiles</h3><p><?php echo esc_html(count($children)); ?> child profile<?php echo count($children)===1?'':'s'; ?> saved.</p><button class="button" type="button" disabled>Manage profiles</button></article>
    <article class="bh-hub-panel"><h3>Favourite Groups</h3><p><?php echo esc_html(count($favourites)); ?> saved activit<?php echo count($favourites)===1?'y':'ies'; ?>.</p><button class="button" type="button" disabled>View favourites</button></article>
    <article class="bh-hub-panel"><h3>Visited Groups</h3><p><?php echo esc_html(count($visited)); ?> visited activit<?php echo count($visited)===1?'y':'ies'; ?>.</p><button class="button" type="button" disabled>View history</button></article>
    <article class="bh-hub-panel"><h3>Compare Groups</h3><p><?php echo esc_html(count($compare)); ?> activit<?php echo count($compare)===1?'y':'ies'; ?> selected for comparison.</p><button class="button" type="button" disabled>Compare</button></article>
   </div>
  </section>
  <?php return (string)ob_get_clean();
 }
 private static function ids(string $key,int $user_id):array { $value=get_user_meta($user_id,$key,true); return is_array($value)?array_values(array_filter(array_map('absint',$value))):[]; }
 private static function toggle(string $key):void {
  check_ajax_referer('bh_my_hub','nonce'); if(!is_user_logged_in()) wp_send_json_error(['message'=>'Login required'],401);
  $id=isset($_POST['activity_id'])?absint($_POST['activity_id']):0; if(!$id || get_post_type($id)!=='bh_activity') wp_send_json_error(['message'=>'Invalid activity'],400);
  $user_id=get_current_user_id(); $ids=self::ids($key,$user_id);
  if(in_array($id,$ids,true)) {$ids=array_values(array_diff($ids,[$id]));$active=false;} else {$ids[]=$id;$active=true;}
  update_user_meta($user_id,$key,$ids); wp_send_json_success(['active'=>$active,'count'=>count($ids)]);
 }
 public static function toggle_favourite():void {self::toggle('bh_favourites');}
 public static function toggle_visited():void {self::toggle('bh_visited');}
 public static function toggle_compare():void {self::toggle('bh_compare');}
}
