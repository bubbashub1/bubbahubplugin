<?php
defined('ABSPATH') || exit;
final class BHPlugin_Dependencies {
 public static function init():void {
  if(class_exists('ACF')) do_action('bhplugin/acf/available');
  if(class_exists('Ninja_Forms')) do_action('bhplugin/ninja_forms/available');
  if(class_exists('UM')) do_action('bhplugin/ultimate_member/available');
  if(class_exists('GetPaid')) do_action('bhplugin/getpaid/available');
 }
}
