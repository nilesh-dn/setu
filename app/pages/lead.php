<?php
$id=(int)($_GET['id']??0);
$l=DB::row('SELECT l.*,c.keyword,c.location FROM leads l JOIN campaigns c ON c.id=l.campaign_id WHERE l.id=?',[$id]);
if(!$l){flash('Lead not found.','err');redirect(url('leads'));}
if(is_post()){
  $a=$_POST['a']??'';
  switch($a){
    case 'save':
      $u=['phone'=>mb_substr(trim($_POST['phone']??''),0,40),'email'=>mb_substr(trim($_POST['email']??''),0,190),'website'=>mb_substr(trim($_POST['website']??''),0,490),'notes'=>trim($_POST['notes']??'')];
      if($u['phone']!==$l['phone'])$u['phone_source']=$u['phone']?'manual':'';
      DB::update('leads',$u,'id=?',[$id]);event($id,'note','Lead details updated');flash('Saved.');break;
    case 'initiate':Outreach::initiate([$id],['auto'=>!empty($_POST['auto']),'pw'=>$_POST['pw']??0,'pn'=>$_POST['pn']??0]);flash('Audit & report queued.');break;
    case 'report':DB::q("DELETE FROM jobs WHERE ref_id=? AND type='report' AND status='pending'",[$id]);Queue::push('report',$id,['pw'=>$_POST['pw']??0,'pn'=>$_POST['pn']??0]);stage($id,'queued','Report regeneration queued');flash('Report regeneration queued.');break;
    case 'send':
      $ch=array_values(array_intersect($_POST['ch']??[],['email','whatsapp']));
      if(!$ch){flash('Choose at least one channel.','err');break;}
      if(in_array('email',$ch,true))DB::update('leads',['email_status'=>''],'id=? AND email_status=\'failed\'',[$id]);
      if(in_array('whatsapp',$ch,true))DB::update('leads',['wa_status'=>''],'id=? AND wa_status IN (\'failed\',\'queued\')',[$id]);
      DB::q("DELETE FROM jobs WHERE type='contact' AND ref_id=? AND status='pending'",[$id]);
      Queue::push('contact',$id,['channels'=>$ch,'force'=>1]);event($id,'scheduled','Outreach queued manually ('.implode(' + ',$ch).')');flash('Sending now…');break;
    case 'wa_done':
      [,$b]=Outreach::message($l['website']?'whatsapp':'whatsapp_noweb',$l);
      DB::insert('messages',['lead_id'=>$id,'channel'=>'whatsapp','status'=>'manual','body'=>tpl($b,lead_vars($l))]);
      DB::update('leads',['wa_status'=>'manual'],'id=?',[$id]);
      if(!in_array($l['stage'],['opened','replied','won','lost'],true))stage($id,'contacted');
      event($id,'sent','WhatsApp opened for manual sending (wa.me)','whatsapp');json_out(['ok'=>1]);
    case 'replied':Outreach::markReplied($id,in_array($_POST['via']??'',['email','whatsapp','call','other'],true)?$_POST['via']:'other',trim($_POST['msg']??''));flash('Marked as replied.');break;
    case 'won':case 'lost':case 'new':stage($id,$a,'Stage changed to '.stages()[$a][0]);flash('Stage updated.');break;
    case 'note':if($t=trim($_POST['note']??''))event($id,'note',$t);break;}
  redirect(url('lead',['id'=>$id]));}
$title=$l['name'];
$au=jd($l['audit']);
$rep=DB::row('SELECT * FROM reports WHERE lead_id=? ORDER BY id DESC LIMIT 1',[$id]);
$ev=DB::all('SELECT * FROM events WHERE lead_id=? ORDER BY id DESC LIMIT 200',[$id]);
$msgs=DB::all('SELECT * FROM messages WHERE lead_id=? ORDER BY id DESC',[$id]);
$jobs=DB::all("SELECT type,status,run_after,error FROM jobs WHERE ref_id=? AND type<>'search' AND status IN ('pending','running','failed') ORDER BY id DESC LIMIT 5",[$id]);
$soc=jd($l['socials']);
$order=['new'=>0,'queued'=>1,'auditing'=>1,'reporting'=>2,'report_ready'=>3,'contacting'=>3,'contacted'=>4,'opened'=>5,'replied'=>6,'won'=>7,'lost'=>7,'failed'=>-1,'skipped'=>-1];
$pos=$order[$l['stage']]??0;if($l['map_score']!==null)$pos=max($pos,2);if($rep)$pos=max($pos,3);
$steps=['Found','Queued','Audited','Report ready','Contacted','Opened','Replied','Won'];
$wa='';
if($l['phone']&&$rep){[,$b]=Outreach::message($l['website']?'whatsapp':'whatsapp_noweb',$l);$wa=WhatsApp::link($l['phone'],tpl($b,lead_vars($l)));}
$pw=DB::all("SELECT id,name,is_active FROM prompts WHERE type='report_website' ORDER BY is_active DESC,name");
$pn=DB::all("SELECT id,name,is_active FROM prompts WHERE type='report_noweb' ORDER BY is_active DESC,name");
$psel=fn($n,$rows)=>'<select name="'.$n.'">'.implode('',array_map(fn($p)=>'<option value="'.$p['id'].'">'.e($p['name']).($p['is_active']?' (in use)':'').'</option>',$rows)).'</select>';
$evic=['found'=>'pin','enriched'=>'globe','audited'=>'activity','stage'=>'zap','sent'=>'send','opened'=>'eye','viewed'=>'eye','replied'=>'msg','error'=>'alert','note'=>'edit','scheduled'=>'clock','wa_manual'=>'wa','delivered'=>'check','read'=>'check'];
$checks=function($a,$t){if(!$a)return '';$o='<div class="ckl"><div class="ckh"><h4>'.$t.'</h4><span class="sc sc-'.($a['score']>=75?'g':($a['score']>=50?'a':'r')).' lg">'.$a['score'].'</span></div>';foreach($a['checks'] as $c)$o.='<div class="ck ck-'.$c['status'].'"><i>'.($c['status']==='pass'?'✓':($c['status']==='warn'?'!':'✕')).'</i><div><b>'.e($c['label']).'</b><small>'.e($c['detail']).'</small></div></div>';return $o.'</div>';};
?>
<div class="ph"><div><a class="lnk" href="<?=url('campaign',['id'=>$l['campaign_id']])?>">← <?=e($l['keyword'])?><?=$l['location']?' · '.e($l['location']):''?></a><div class="phm"><?=badge($l['stage'])?><span class="muted">#<?=$l['position']?> on Google Maps · <?=e($l['category'])?></span></div></div><div class="pha"><?php if($rep):?><a class="btn" href="report.php?t=<?=e($rep['token'])?>" target="_blank"><?=icon('eye')?>View PDF</a><?php endif?><?php if($wa):?><a class="btn btn-wa" href="<?=e($wa)?>" target="_blank" rel="noopener" onclick="A.post({a:'wa_done'})"><?=icon('wa')?>Send on WhatsApp</a><?php endif?><button class="btn btn-g" onclick="A.modal('m-run')"><?=icon('zap')?><?=$rep?'Re-run':'Initiate Process'?></button></div></div>
<div class="pipe"><?php foreach($steps as $i=>$s):?><div class="ps <?=$i<=$pos?'done':''?> <?=$i===$pos?'cur':''?> <?=$l['stage']==='lost'&&$i===7?'lost':''?>"><i><?=$i<$pos?icon('check'):$i+1?></i><span><?=$l['stage']==='lost'&&$i===7?'Not interested':$s?></span></div><?php endforeach?></div>
<?php foreach($jobs as $j):if($j['status']==='failed'):?><div class="al al-err"><?=icon('alert')?><span><b><?=e(ucfirst($j['type']))?> failed:</b> <?=e($j['error'])?></span></div><?php else:?><div class="al al-info"><i class="spin"></i><span><?=e(ucfirst($j['type']))?> <?=$j['status']==='running'?'running now…':'scheduled '.e(date('d M h:i A',strtotime($j['run_after'])))?><?=$j['error']?' – retrying after error: '.e($j['error']):''?></span></div><?php endif;endforeach?>
<div class="g3"><div class="col">
<div class="card"><div class="card-h"><h3>Business</h3><?php if($l['maps_url']):?><a class="lnk" href="<?=e($l['maps_url'])?>" target="_blank" rel="noopener"><?=icon('pin')?>Google Maps</a><?php endif?></div><div class="info"><p><?=icon('pin')?><?=e($l['address'])?></p><p><?=icon('star')?><?=$l['rating']?e($l['rating']).' ★ · '.(int)$l['reviews'].' reviews':'No reviews'?></p><?php foreach($soc as $k=>$u):if($k==='whatsapp')continue;?><p><?=icon('link')?><a href="<?=e($u)?>" target="_blank" rel="noopener"><?=e(ucfirst($k))?></a></p><?php endforeach?></div>
<form method="post" class="frm"><?=csrf_field()?><input type="hidden" name="a" value="save"><label>Phone <?php if($l['phone_source']):?><em class="src"><?=e($l['phone_source'])?></em><?php endif?><input name="phone" value="<?=e($l['phone'])?>"></label><label>Email<input name="email" type="email" value="<?=e($l['email'])?>"></label><label>Website<input name="website" value="<?=e($l['website'])?>"></label><label>Notes<textarea name="notes" rows="3"><?=e($l['notes'])?></textarea></label><button class="btn">Save</button></form></div>
<div class="card"><div class="card-h"><h3>Outreach</h3></div><div class="chs"><div><span><?=icon('mail')?>Email</span><?=chbadge($l['email_status'])?></div><div><span><?=icon('wa')?>WhatsApp</span><?=chbadge($l['wa_status'])?></div><div><span><?=icon('msg')?>Reply</span><?=$l['replied_at']?'<span class="bdg bdg-green">'.e(ucfirst($l['replied_via'])).' · '.ago($l['replied_at']).'</span>':'<span class="muted">No reply yet</span>'?></div><?php if($rep):?><div><span><?=icon('eye')?>Report views</span><b><?=(int)$rep['views']?></b></div><?php endif?></div>
<?php if($rep):?><form method="post" class="frm"><?=csrf_field()?><input type="hidden" name="a" value="send"><div class="row"><label class="chk"><input type="checkbox" name="ch[]" value="email" <?=$l['email']?'checked':'disabled'?>> Email</label><label class="chk"><input type="checkbox" name="ch[]" value="whatsapp" <?=$l['phone']?'checked':'disabled'?>> WhatsApp <?=WhatsApp::provider()==='manual'?'<small class="muted">(manual)</small>':''?></label></div><button class="btn btn-g"><?=icon('send')?>Send now</button></form><?php endif?>
<form method="post" class="frm"><?=csrf_field()?><input type="hidden" name="a" value="replied"><label>Log a reply<div class="row"><select name="via"><option value="whatsapp">WhatsApp</option><option value="email">Email</option><option value="call">Phone call</option><option value="other">Other</option></select><button class="btn">Mark replied</button></div></label><input name="msg" placeholder="What did they say? (optional)"></form>
<div class="row mt"><form method="post"><?=csrf_field()?><input type="hidden" name="a" value="won"><button class="btn btn-ok"><?=icon('check')?>Won</button></form><form method="post"><?=csrf_field()?><input type="hidden" name="a" value="lost"><button class="btn"><?=icon('x')?>Not interested</button></form></div></div>
</div><div class="col col2">
<div class="card"><div class="card-h"><h3>Audit</h3><?php if($rep):?><span class="muted">Report <?=ago($rep['created_at'])?> · <a class="lnk" href="report.php?t=<?=e($rep['token'])?>&dl=1"><?=icon('download')?>PDF</a></span><?php endif?></div><?php if(!$au):?><div class="empty sm"><?=icon('activity')?><p>Not audited yet. Click <b>Initiate Process</b> to audit the <?=$l['website']?'website and ':''?>Google Maps listing and create the PDF report.</p></div><?php else:?><div class="g2 g2t"><?=$checks($au['map']??null,'Google Maps')?><?=$l['website']?$checks($au['website']??null,'Website'):'<div class="ckl"><div class="ckh"><h4>Website</h4><span class="sc sc-r lg">—</span></div><p class="muted">No website – the report pitches a new website'.(setting('proposal_pdf')?' and attaches your website proposal PDF.':'. Upload a proposal PDF in Settings → Proposal to attach it.').'</p></div>'?></div><?php endif?></div>
<div class="g2"><div class="card"><div class="card-h"><h3>Journey</h3><span class="muted"><?=count($ev)?> events</span></div><form method="post" class="row"><?=csrf_field()?><input type="hidden" name="a" value="note"><input name="note" placeholder="Add a note to the journey…"><button class="btn"><?=icon('plus')?></button></form><ul class="tl"><?php foreach($ev as $x):?><li class="ev-<?=e($x['type'])?>"><i><?=icon($evic[$x['type']]??'zap')?></i><div><span><?=e($x['message'])?></span><small><?=e(date('d M Y, h:i A',strtotime($x['created_at'])))?><?=$x['channel']?' · '.e($x['channel']):''?></small></div></li><?php endforeach?></ul></div>
<div class="card"><div class="card-h"><h3>Messages</h3></div><?php if(!$msgs):?><div class="empty sm"><?=icon('msg')?><p>No messages yet.</p></div><?php endif?><?php foreach($msgs as $m):?><div class="msg msg-<?=e($m['direction'])?>"><div class="msg-h"><?=icon($m['channel']==='email'?'mail':'wa')?><b><?=e(ucfirst($m['channel']))?></b><?=chbadge($m['status'])?><small><?=ago($m['created_at'])?></small></div><?php if($m['subject']):?><b class="sm"><?=e($m['subject'])?></b><?php endif?><p><?=nl2br(e(mb_strimwidth((string)$m['body'],0,600,'…')))?></p></div><?php endforeach?></div></div>
</div></div>
<div class="md" id="m-run"><form class="md-b" method="post"><?=csrf_field()?><div class="md-h"><h3><?=icon('zap')?>Audit & report</h3><button type="button" class="ib" onclick="A.close()"><?=icon('x')?></button></div><label>Report prompt (has website)<?=$psel('pw',$pw)?></label><label>Report prompt (no website)<?=$psel('pn',$pn)?></label><label class="chk"><input type="checkbox" name="auto" value="1" <?=setting('auto_contact')==='1'&&!$rep?'checked':''?>> Contact automatically when the report is ready</label><div class="md-f"><?php if($au):?><button class="btn" name="a" value="report"><?=icon('file')?>Only regenerate report</button><?php endif?><button class="btn btn-g" name="a" value="initiate"><?=icon('play')?>Full audit + report</button></div></form></div>
<?php if(in_array($l['stage'],['queued','auditing','reporting','contacting'],true)||array_filter($jobs,fn($j)=>$j['status']!=='failed')):?><span data-reload="8"></span><?php endif?>
