<?php
$title='Scraped Data';$subtitle='Businesses found by the Map Scraper. Move won or interested ones to Leads.';
if(is_post()){flash(e(Outreach::bulk($_POST['a']??'',array_map('intval',$_POST['ids']??[]))));back(url('prospects'));}
$q=trim($_GET['q']??'');$st=$_GET['stage']??'';$cid=(int)($_GET['cid']??0);$web=$_GET['web']??'';$il=$_GET['il']??'';
$w=["l.source='maps'"];$a=[];
if($q!==''){$w[]='(l.name LIKE ? OR l.email LIKE ? OR l.phone LIKE ? OR l.category LIKE ? OR l.address LIKE ?)';array_push($a,...array_fill(0,5,'%'.$q.'%'));}
if(isset(stages()[$st])){$w[]='l.stage=?';$a[]=$st;}
if($st==='working')$w[]="l.stage IN ('queued','auditing','reporting','contacting')";
if($st==='replied_any')$w[]='l.replied_at IS NOT NULL';
if($cid){$w[]='l.campaign_id=?';$a[]=$cid;}
if($web==='1')$w[]="l.website<>''";if($web==='0')$w[]="l.website=''";
if($il==='1')$w[]='l.is_lead=1';if($il==='0')$w[]='l.is_lead=0';
$where=implode(' AND ',$w);
$total=(int)DB::val("SELECT COUNT(*) FROM leads l WHERE $where",$a);
$per=50;$pg=max(1,(int)($_GET['p']??1));$pages=max(1,(int)ceil($total/$per));
if(isset($_GET['export']))Outreach::csv(DB::all("SELECT l.* FROM leads l WHERE $where ORDER BY l.id DESC",$a));
$leads=DB::all("SELECT l.* FROM leads l WHERE $where ORDER BY COALESCE(l.last_activity,l.created_at) DESC,l.id DESC LIMIT $per OFFSET ".(($pg-1)*$per),$a);
if(isset($_GET['partial'])){$selectable=true;require APP.'/views/leads_table.php';exit;}
$camps=DB::all('SELECT id,keyword,location FROM campaigns ORDER BY id DESC LIMIT 200');
$k=DB::row("SELECT COUNT(*) t,SUM(stage IN ('queued','auditing','reporting','contacting')) wk,SUM(stage='report_ready') rr,SUM(replied_at IS NOT NULL) rp,SUM(is_lead) il FROM leads WHERE source='maps'");
$qs=fn($x)=>url('prospects',array_filter(['q'=>$q,'stage'=>$st,'cid'=>$cid?:'','web'=>$web,'il'=>$il]+$x,'strlen'));
?>
<div class="kpis"><div class="kpi"><span class="hi hi-b"><?=icon('db')?></span><div><span>Scraped businesses</span><b><?=(int)$k['t']?></b><small>from <?=count($camps)?> searches</small></div></div><div class="kpi"><span class="hi hi-o"><?=icon('zap')?></span><div><span>Processing now</span><b><?=(int)$k['wk']?></b><small><a class="lnk" href="<?=url('prospects',['stage'=>'working'])?>">View</a></small></div></div><div class="kpi"><span class="hi hi-p"><?=icon('file')?></span><div><span>Reports ready</span><b><?=(int)$k['rr']?></b><small>waiting to be sent</small></div></div><div class="kpi kpi-n"><span class="hi hi-y"><?=icon('users')?></span><div><span>Moved to Leads</span><b><?=(int)$k['il']?></b><small><?=(int)$k['rp']?> replied</small></div></div></div>
<form class="fl card" method="get"><input type="hidden" name="r" value="prospects"><label class="f2"><?=icon('search')?><input name="q" value="<?=e($q)?>" placeholder="Search name, phone, email, category…"></label><select name="cid"><option value="">All searches</option><?php foreach($camps as $c):?><option value="<?=$c['id']?>" <?=$cid===(int)$c['id']?'selected':''?>><?=e($c['keyword'].($c['location']?' · '.$c['location']:''))?></option><?php endforeach?></select><select name="stage"><option value="">Any status</option><option value="working" <?=$st==='working'?'selected':''?>>Processing now</option><?php foreach(stages() as $kk=>[$lb]):?><option value="<?=$kk?>" <?=$st===$kk?'selected':''?>><?=e($lb)?></option><?php endforeach?><option value="replied_any" <?=$st==='replied_any'?'selected':''?>>Has replied</option></select><select name="web"><option value="">Website: any</option><option value="1" <?=$web==='1'?'selected':''?>>Has website</option><option value="0" <?=$web==='0'?'selected':''?>>No website</option></select><select name="il"><option value="">Leads: any</option><option value="0" <?=$il==='0'?'selected':''?>>Not in Leads</option><option value="1" <?=$il==='1'?'selected':''?>>In Leads</option></select><button class="btn btn-n">Filter</button><a class="btn" href="<?=$qs(['export'=>1])?>"><?=icon('download')?>CSV</a></form>
<div class="card p0"><?php require APP.'/views/bulkbar.php';?><div id="live" <?=(int)$k['wk']?'data-live="'.e($qs(['p'=>$pg,'partial'=>1])).'"':''?>><?php $selectable=true;$emptyMsg='No scraped businesses match. Run a search in Map Scraper.';require APP.'/views/leads_table.php';?></div><?php if($pages>1):?><div class="pg"><span class="muted"><?=$total?> records</span><?php for($i=max(1,$pg-3);$i<=min($pages,$pg+3);$i++):?><a href="<?=$qs(['p'=>$i])?>" class="<?=$i===$pg?'on':''?>"><?=$i?></a><?php endfor?></div><?php endif?></div>
