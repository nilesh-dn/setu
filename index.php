<?php
require __DIR__.'/app/bootstrap.php';
$r=preg_replace('/[^a-z_]/','',$_GET['r']??'dashboard');
if(!is_file(APP.'/pages/'.$r.'.php'))$r='dashboard';
if($r!=='login')require_login();
if(is_post())csrf_check();
$title='Anvesha';$active=$r;
ob_start();
require APP.'/pages/'.$r.'.php';
$content=ob_get_clean();
require APP.'/views/layout.php';
if(!setting('cron_tried')){Settings::set('cron_tried',1);Cron::install();}
if(Cron::due()){session_write_close();if(function_exists('fastcgi_finish_request'))fastcgi_finish_request();elseif(function_exists('litespeed_finish_request'))litespeed_finish_request();Cron::kick();}
