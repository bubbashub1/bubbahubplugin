<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$configPath=__DIR__.'/config.php';
if(!is_file($configPath)){http_response_code(500);echo json_encode(['success'=>false,'error'=>'API is not configured.']);exit;}
$config=require $configPath;
$origin=$_SERVER['HTTP_ORIGIN']??'';
if($origin!==''&&in_array($origin,$config['app']['allowed_origins']??[],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
try{$db=$config['db'];$pdo=new PDO(sprintf('mysql:host=%s;dbname=%s;charset=%s',$db['host'],$db['name'],$db['charset']),$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
catch(Throwable $e){http_response_code(500);echo json_encode(['success'=>false,'error'=>'Database connection failed.']);exit;}
function json_input():array{$raw=file_get_contents('php://input');if(!$raw)return[];$data=json_decode($raw,true);return is_array($data)?$data:[];}
function json_response(array $data,int $status=200):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
function query_string(string $key,string $default=''):string{return trim((string)($_GET[$key]??$default));}