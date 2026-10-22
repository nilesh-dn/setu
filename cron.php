<?php
// Run every minute:  php /path/to/cron.php   or   https://your-site/cron.php?key=CRON_KEY
require __DIR__.'/app/bootstrap.php';
if(PHP_SAPI!=='cli'){
  session_write_close();
  if(!hash_equals(setting('cron_key'),(string)($_GET['key']??''))){http_response_code(403);exit('Forbidden');}}
$lock=STORAGE.'/tmp/cron.lock';
$fp=fopen($lock,'c');
if(!flock($fp,LOCK_EX|LOCK_NB))exit("Already running\n");
Settings::set('cron_last',time());
$n=Queue::work(PHP_SAPI==='cli'?50:25);
$rep=0;
if(setting('imap_host')&&time()-(int)setting('imap_last','0')>600){
  Settings::set('imap_last',time());
  try{$rep=Outreach::checkReplies();}catch(Throwable $e){logmsg('IMAP: '.$e->getMessage());}}
try{Updater::check();}catch(Throwable $e){}
foreach(glob(STORAGE.'/tmp/upd-*',GLOB_ONLYDIR) as $d)if(filemtime($d)<time()-86400)Updater::rrm($d);
DB::q("DELETE FROM jobs WHERE status='done' AND created_at<NOW()-INTERVAL 14 DAY");
flock($fp,LOCK_UN);
echo "Processed $n job(s), $rep new repl(ies)\n";
