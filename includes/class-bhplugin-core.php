<?php
defined('ABSPATH') || exit;
final class BHPlugin_Core {
 public static function init():void {
  add_action('init',[__CLASS__,'register_content']);
  add_action('admin_menu',[__CLASS__,'admin_menu']);
  add_action('wp_enqueue_scripts',[__CLASS__,'enqueue_frontend']);
  add_action('admin_enqueue_scripts',[__CLASS__,'enqueue_admin']);
 }
 public static function activate():void { self::register_content(); flush_rewrite_rules(); }
 public static function deactivate():void { flush_rewrite_rules(); }
 public static function register_content():void {
  register_post_type('bh_activity',['labels'=>['name'=>'Activities','singular_name'=>'Activity','add_new_item'=>'Add Activity','edit_item'=>'Edit Activity','menu_name'=>'Bubba Hub'],'public'=>true,'show_ui'=>true,'show_in_rest'=>true,'has_archive'=>true,'rewrite'=>['slug'=>'activities'],'menu_icon'=>'dashicons-groups','supports'=>['title','editor','excerpt','thumbnail','author'],'capability_type'=>'post','map_meta_cap'=>true]);
  foreach(['bh_category'=>['Categories',true],'bh_region'=>['Regions',true],'bh_town'=>['Towns',true],'bh_age'=>['Age ranges',true],'bh_day'=>['Days',false]] as $taxonomy=>[$label,$hierarchical]) register_taxonomy($taxonomy,['bh_activity'],['label'=>$label,'public'=>true,'show_in_rest'=>true,'hierarchical'=>$hierarchical,'rewrite'=>['slug'=>sanitize_title($label)]]);
 }
 public static function admin_menu():void { add_submenu_page('edit.php?post_type=bh_activity','Bubba Hub Settings','Settings','manage_options','bhplugin-settings',[__CLASS__,'settings_page']); }
 public static function settings_page():void {
  if(!current_user_can('manage_options')) return;
  $available=array_filter([class_exists('ACF')?'ACF':'',class_exists('Ninja_Forms')?'Ninja Forms':'',class_exists('UM')?'Ultimate Member':'',class_exists('GetPaid')?'GetPaid':'']);
  echo '<div class="wrap"><h1>Bubba Hub</h1><p>Step 2: My Hub foundation.</p><div class="notice notice-info inline"><p><strong>Integrations detected:</strong> '.esc_html(implode(', ',$available?:['None'])).'</p></div></div>';
 }
 public static function enqueue_frontend():void {
  wp_enqueue_style('bhplugin-frontend',BHPLUGIN_URL.'assets/css/frontend.css',[],BHPLUGIN_VERSION);
  wp_enqueue_style('bhplugin-my-hub',BHPLUGIN_URL.'assets/css/my-hub.css',['bhplugin-frontend'],BHPLUGIN_VERSION);
 }
 public static function enqueue_admin(string $hook):void { $screen=get_current_screen(); if(strpos($hook,'bhplugin')===false && (!$screen || $screen->post_type!=='bh_activity')) return; wp_enqueue_style('bhplugin-admin',BHPLUGIN_URL.'assets/css/admin.css',[],BHPLUGIN_VERSION); }
}
