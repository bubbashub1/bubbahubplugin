<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
$path=trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)??'','/');
$base=trim(dirname($_SERVER['SCRIPT_NAME']),'/');
$route=$base!==''?preg_replace('#^'.preg_quote($base,'#').'/?#','',$path):$path;
$route=trim($route,'/');
if($route===''||$route==='health')json_response(['success'=>true,'system'=>'Bubba Hub Standalone API','version'=>'1.0.0','database'=>'connected']);
if($route==='activities'){require __DIR__.'/routes/activities.php';exit;}
json_response(['success'=>false,'error'=>'Endpoint not found.'],404);