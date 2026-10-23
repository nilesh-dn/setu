<?php
if(isset($_GET['logout'])){session_destroy();redirect('index.php?r=login');}
if(user())redirect('index.php');
$err='';
if(is_post()){
  $_SESSION['tries']=($_SESSION['tries']??0)+1;
  if($_SESSION['tries']>8){$err='Too many attempts. Close the browser and try again later.';}
  else{
    $u=DB::row('SELECT * FROM users WHERE email=?',[trim($_POST['email']??'')]);
    if($u&&password_verify($_POST['password']??'',$u['password'])){session_regenerate_id(true);$_SESSION['uid']=$u['id'];$_SESSION['tries']=0;redirect('index.php');}
    $err='Invalid email or password.';}}
$bare=true;$title='Sign in';?>
<div class="auth"><div class="auth-l"><span class="logo-s">S</span><h2>Setu</h2><p>The bridge between you and your next customer. Find businesses on Google Maps, send AI-written audit reports, capture leads from forms &amp; ads, and follow up automatically.</p><ul><li><span class="ni ni-o"><?=icon('map')?></span>Google Maps prospecting &amp; deep audits</li><li><span class="ni ni-p"><?=icon('spark')?></span>Premium AI reports as branded PDFs</li><li><span class="ni ni-g"><?=icon('users')?></span>CRM with forms, Meta &amp; Google Ads leads</li><li><span class="ni ni-y"><?=icon('bot')?></span>WhatsApp, email, AI calls &amp; bookings on autopilot</li></ul></div><form class="auth-r" method="post"><?=csrf_field()?><h1>Welcome back</h1><p class="muted">Sign in to continue</p><?php if($err):?><div class="al al-err"><?=icon('alert')?><span><?=e($err)?></span></div><?php endif?><label>Email<input type="email" name="email" required autofocus value="<?=e($_POST['email']??'')?>"></label><label>Password<input type="password" name="password" required></label><button class="btn btn-y btn-b">Sign in</button></form></div>
