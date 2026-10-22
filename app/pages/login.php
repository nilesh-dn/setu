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
<div class="auth"><div class="auth-l"><img src="<?=e(setting('company_logo','assets/img/default-logo.png'))?>" alt=""><h2>Anvesha</h2><p>Find businesses on Google Maps, audit their website & listing with AI, and send personalised proposals on Email & WhatsApp – automatically.</p><ul><li><?=icon('search')?>Google Maps lead discovery</li><li><?=icon('spark')?>AI audit reports as branded PDFs</li><li><?=icon('send')?>Email + WhatsApp outreach with full journey tracking</li></ul></div><form class="auth-r" method="post"><?=csrf_field()?><h1>Welcome back</h1><p class="muted">Sign in to continue</p><?php if($err):?><div class="al al-err"><?=icon('alert')?><span><?=e($err)?></span></div><?php endif?><label>Email<input type="email" name="email" required autofocus value="<?=e($_POST['email']??'')?>"></label><label>Password<input type="password" name="password" required></label><button class="btn btn-g btn-b">Sign in</button></form></div>
