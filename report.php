<?php
require __DIR__.'/app/bootstrap.php';
$staff=!empty($_SESSION['uid']);
session_write_close();
$t=preg_replace('/[^a-f0-9]/','',$_GET['t']??$_GET['proposal']??'');
$r=$t?DB::row('SELECT * FROM reports WHERE token=?',[$t]):null;
if(!$r){http_response_code(404);exit('Report not found');}
if(isset($_GET['proposal'])){
  $f=ROOT.'/'.setting('proposal_pdf');
  if(!setting('proposal_pdf')||!is_file($f)){http_response_code(404);exit('Not found');}
  $name='Website-Proposal.pdf';}
else{
  $f=ROOT.'/'.$r['pdf'];
  if(!is_file($f)){http_response_code(404);exit('File missing');}
  $l=DB::row('SELECT name,stage FROM leads WHERE id=?',[$r['lead_id']]);
  $name='Audit-Report-'.preg_replace('/[^a-z0-9]+/i','-',$l['name']??'report').'.pdf';
  // count only external views (not logged-in users)
  $bot=preg_match('~bot|crawl|spider|facebookexternalhit|WhatsApp|preview|curl|wget|python~i',$_SERVER['HTTP_USER_AGENT']??'');
  if(!$staff&&!$bot){
    DB::q('UPDATE reports SET views=views+1 WHERE id=?',[$r['id']]);
    if((int)$r['views']===0){event($r['lead_id'],'viewed','Report opened by the business','web');if(in_array($l['stage']??'',['contacted','report_ready'],true))stage($r['lead_id'],'opened');}}}
header('Content-Type: application/pdf');
header('Content-Disposition: '.(isset($_GET['dl'])?'attachment':'inline').'; filename="'.$name.'"');
header('Content-Length: '.filesize($f));
header('X-Robots-Tag: noindex');
readfile($f);
Cron::kick();
