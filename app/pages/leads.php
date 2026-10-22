<?php
$title='Leads';
if(is_post()){flash(e(Outreach::bulk($_POST['a']??'',array_map('intval',$_POST['ids']??[]))));back(url('leads'));}
$q=trim($_GET['q']??'');$st=$_GET['stage']??'';$cid=(int)($_GET['cid']??0);$web=$_GET['web']??'';
$w=['1=1'];$a=[];
if($q!==''){$w[]='(l.name LIKE ? OR l.email LIKE ? OR l.phone LIKE ? OR l.category LIKE ? OR l.address LIKE ?)';array_push($a,...array_fill(0,5,'%'.$q.'%'));}
if(isset(stages()[$st])){$w[]='l.stage=?';$a[]=$st;}
if($st==='replied_any')$w[]='l.replied_at IS NOT NULL';
if($cid){$w[]='l.campaign_id=?';$a[]=$cid;}
if($web==='1')$w[]="l.website<>''";if($web==='0')$w[]="l.website=''";
$where=implode(' AND ',$w);
$total=(int)DB::val("SELECT COUNT(*) FROM leads l WHERE $where",$a);
$per=50;$pg=max(1,(int)($_GET['p']??1));$pages=max(1,(int)ceil($total/$per));
if(isset($_GET['export']))Outreach::csv(DB::all("SELECT l.* FROM leads l WHERE $where ORDER BY l.id DESC",$a));
$leads=DB::all("SELECT l.* FROM leads l WHERE $where ORDER BY COALESCE(l.last_activity,l.created_at) DESC,l.id DESC LIMIT $per OFFSET ".(($pg-1)*$per),$a);
$camps=DB::all('SELECT id,keyword,location FROM campaigns ORDER BY id DESC LIMIT 200');
$qs=fn($x)=>url('leads',array_filter(['q'=>$q,'stage'=>$st,'cid'=>$cid?:'','web'=>$web]+$x,'strlen'));
?>
<form class="fl card" method="get"><input type="hidden" name="r" value="leads"><label class="f2"><?=icon('search')?><input name="q" value="<?=e($q)?>" placeholder="Search name, phone, email, category…"></label><select name="cid"><option value="">All searches</option><?php foreach($camps as $c):?><option value="<?=$c['id']?>" <?=$cid===(int)$c['id']?'selected':''?>><?=e($c['keyword'].($c['location']?' · '.$c['location']:''))?></option><?php endforeach?></select><select name="stage"><option value="">Any stage</option><?php foreach(stages() as $k=>[$l]):?><option value="<?=$k?>" <?=$st===$k?'selected':''?>><?=e($l)?></option><?php endforeach?><option value="replied_any" <?=$st==='replied_any'?'selected':''?>>Has replied</option></select><select name="web"><option value="">Website: any</option><option value="1" <?=$web==='1'?'selected':''?>>Has website</option><option value="0" <?=$web==='0'?'selected':''?>>No website</option></select><button class="btn">Filter</button><a class="btn" href="<?=$qs(['export'=>1])?>"><?=icon('download')?>CSV</a></form>
<div class="card p0"><?php require APP.'/views/bulkbar.php';?><?php $selectable=true;$emptyMsg='No leads match your filters.';require APP.'/views/leads_table.php';?><?php if($pages>1):?><div class="pg"><span class="muted"><?=$total?> leads</span><?php for($i=max(1,$pg-3);$i<=min($pages,$pg+3);$i++):?><a href="<?=$qs(['p'=>$i])?>" class="<?=$i===$pg?'on':''?>"><?=$i?></a><?php endfor?></div><?php endif?></div>
