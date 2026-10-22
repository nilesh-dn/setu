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
