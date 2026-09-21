<?php
defined('ABSPATH') || exit;
final class BHPlugin_My_Hub {
 public static function init():void {
  add_action('init',[__CLASS__,'register_child_profile_type']);
  add_action('wp_enqueue_scripts',[__CLASS__,'enqueue']);
  add_shortcode('bh_my_hub',[__CLASS__,'render']);
  foreach(['favourite','visited','compare'] as $action) add_action('wp_ajax_bh_toggle_'.$action,[__CLASS__,'toggle_'.$action]);
  add_action('wp_ajax_bh_save_child',[__CLASS__,'save_child']);
  add_action('wp_ajax_bh_delete_child',[__CLASS__,'delete_child']);
 }
 public static function register_child_profile_type():void {
  register_post_type('bh_child_profile',['labels'=>['name'=>'Child Profiles','singular_name'=>'Child Profile'],'public'=>false,'show_ui'=>false,'show_in_rest'=>false,'supports'=>['title','author'],'capability_type'=>'post','map_meta_cap'=>true]);
 }
 public static function enqueue():void {
  wp_enqueue_script('bhplugin-my-hub',BHPLUGIN_URL.'assets/js/my-hub.js',[],BHPLUGIN_VERSION,true);
  wp_localize_script('bhplugin-my-hub','BHMyHub',['ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('bh_my_hub')]);
 }
 public static function render():string {
  if(!is_user_logged_in()) return '<div class="bh-my-hub"><p>Please log in to view My Hub.</p></div>';
  $uid=get_current_user_id(); $children=get_posts(['post_type'=>'bh_child_profile','author'=>$uid,'posts_per_page'=>-1,'post_status'=>'private','orderby'=>'title','order'=>'ASC']);
  $fav=self::valid_ids('bh_favourites',$uid); $vis=self::valid_ids('bh_visited',$uid); $cmp=self::valid_ids('bh_compare',$uid);
  ob_start(); ?>
  <section class="bh-my-hub" aria-labelledby="bh-my-hub-title">
   <header class="bh-my-hub__header"><h2 id="bh-my-hub-title">My Hub</h2><p>Your personal family area for saving and organising activities.</p></header>
   <div class="bh-hub-panel bh-hub-children"><div class="bh-hub-panel__heading"><div><h3>Child Profiles</h3><p>Add children so activities can be organised around your family.</p></div><button class="button button-primary" type="button" data-bh-child-new>Add child</button></div>
    <div class="bh-child-form-wrap" hidden><form class="bh-child-form"><input type="hidden" name="child_id" value=""><p><label>Child's name<br><input type="text" name="name" maxlength="80" required></label></p><p><label>Date of birth<br><input type="date" name="dob"></label></p><p><label>Notes (optional)<br><textarea name="notes" rows="3" maxlength="500"></textarea></label></p><p><button class="button button-primary" type="submit">Save child</button> <button class="button" type="button" data-bh-child-cancel>Cancel</button></p><p class="bh-form-message" aria-live="polite"></p></form></div>
    <div class="bh-child-list"><?php if(!$children): ?><p>No child profiles yet.</p><?php else: foreach($children as $child): ?><div class="bh-child-row" data-child-id="<?php echo esc_attr($child->ID); ?>" data-name="<?php echo esc_attr($child->post_title); ?>" data-dob="<?php echo esc_attr(get_post_meta($child->ID,'bh_dob',true)); ?>" data-notes="<?php echo esc_attr(get_post_meta($child->ID,'bh_notes',true)); ?>"><div><strong><?php echo esc_html($child->post_title); ?></strong><?php $dob=get_post_meta($child->ID,'bh_dob',true); if($dob) echo '<span class="bh-child-meta">Born '.esc_html($dob).'</span>'; ?></div><div><button class="button" type="button" data-bh-child-edit="<?php echo esc_attr($child->ID); ?>">Edit</button> <button class="button" type="button" data-bh-child-delete="<?php echo esc_attr($child->ID); ?>">Delete</button></div></div><?php endforeach; endif; ?></div>
   </div>
   <?php self::activity_panel('Favourite Groups','Activities you have saved.',$fav,'No favourite activities yet.'); ?>
   <?php self::activity_panel('Visited Groups','Activities you have marked as visited.',$vis,'No visited activities yet.'); ?>
   <?php self::activity_panel('Compare Groups','Activities selected to compare.',$cmp,'Add activities from Find Activities to compare them.'); ?>
  </section>
  <?php return (string)ob_get_clean();
 }
 private static function activity_panel(string $title,string $intro,array $ids,string $empty):void { ?>
  <div class="bh-hub-panel bh-hub-activities"><h3><?php echo esc_html($title); ?></h3><p><?php echo esc_html($intro); ?></p>
   <?php if(!$ids): ?><p class="bh-hub-empty"><?php echo esc_html($empty); ?></p><?php else: ?><div class="bh-hub-activity-list"><?php foreach($ids as $id): ?><article class="bh-hub-activity"><div><h4><a href="<?php echo esc_url(get_permalink($id)); ?>"><?php echo esc_html(get_the_title($id)); ?></a></h4></div><div class="bh-hub-activity__actions"><a class="button" href="<?php echo esc_url(get_permalink($id)); ?>">View</a><button class="button" type="button" data-bh-hub-action="<?php echo $title==='Favourite Groups'?'favourite':($title==='Visited Groups'?'visited':'compare'); ?>" data-activity-id="<?php echo esc_attr($id); ?>" aria-pressed="true">Remove</button></div></article><?php endforeach; ?></div><?php endif; ?>
  </div><?php
 }
 private static function valid_ids(string $key,int $uid):array { $ids=self::ids($key,$uid); return array_values(array_filter($ids,static fn($id)=>get_post_type($id)==='bh_activity'&&get_post_status($id)==='publish')); }
 private static function ids(string $key,int $uid):array { $v=get_user_meta($uid,$key,true); return is_array($v)?array_values(array_unique(array_filter(array_map('absint',$v)))):[]; }
 private static function verify():void { check_ajax_referer('bh_my_hub','nonce'); if(!is_user_logged_in()) wp_send_json_error(['message'=>'Login required'],401); }
 private static function toggle(string $key):void { self::verify(); $id=absint($_POST['activity_id']??0); if(!$id||get_post_type($id)!=='bh_activity'||get_post_status($id)!=='publish') wp_send_json_error(['message'=>'Invalid activity'],400); $uid=get_current_user_id();$ids=self::ids($key,$uid);$active=!in_array($id,$ids,true);$ids=$active?array_values(array_unique(array_merge($ids,[$id]))):array_values(array_diff($ids,[$id]));update_user_meta($uid,$key,$ids);wp_send_json_success(['active'=>$active,'count'=>count(self::valid_ids($key,$uid))]); }
 public static function toggle_favourite():void{self::toggle('bh_favourites');}
 public static function toggle_visited():void{self::toggle('bh_visited');}
 public static function toggle_compare():void{self::toggle('bh_compare');}
 public static function save_child():void { self::verify(); $uid=get_current_user_id();$id=absint($_POST['child_id']??0);$name=sanitize_text_field(wp_unslash($_POST['name']??''));$dob=sanitize_text_field(wp_unslash($_POST['dob']??''));$notes=sanitize_textarea_field(wp_unslash($_POST['notes']??''));if($name==='')wp_send_json_error(['message'=>'Please enter a name.'],400);if($dob!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dob))wp_send_json_error(['message'=>'Please enter a valid date.'],400);if($id){$post=get_post($id);if(!$post||$post->post_type!=='bh_child_profile'||(int)$post->post_author!==$uid)wp_send_json_error(['message'=>'Child profile not found.'],404);wp_update_post(['ID'=>$id,'post_title'=>$name]);}else{$id=wp_insert_post(['post_type'=>'bh_child_profile','post_status'=>'private','post_title'=>$name,'post_author'=>$uid],true);if(is_wp_error($id))wp_send_json_error(['message'=>'Unable to save child profile.'],500);}update_post_meta($id,'bh_dob',$dob);update_post_meta($id,'bh_notes',$notes);wp_send_json_success(['id'=>$id,'name'=>$name,'dob'=>$dob]);}
 public static function delete_child():void { self::verify();$uid=get_current_user_id();$id=absint($_POST['child_id']??0);$post=get_post($id);if(!$post||$post->post_type!=='bh_child_profile'||(int)$post->post_author!==$uid)wp_send_json_error(['message'=>'Child profile not found.'],404);wp_delete_post($id,true);wp_send_json_success();}
}
