<?php
$title='Searches';
if(is_post()){
  $a=$_POST['a']??'';
  if($a==='create'){
    $kw=trim($_POST['keyword']??'');$loc=trim($_POST['location']??'');
    $n=(int)($_POST['limit']==='custom'?$_POST['custom']:$_POST['limit']);$n=max(1,min(500,$n?:20));
    $src=in_array($_POST['source']??'',['places','serpapi'],true)?$_POST['source']:setting('maps_source','places');
    if($kw===''){flash('Please enter a keyword.','err');redirect(url('campaigns',['new'=>1]));}
    if($src==='places'&&$n>60)flash('Google Places API returns at most 60 results per search – use SerpAPI for more. Fetching up to 60.','warn');
    $id=DB::insert('campaigns',['keyword'=>mb_substr($kw,0,180),'location'=>mb_substr($loc,0,180),'limit_n'=>$src==='places'?min($n,60):$n,'source'=>$src]);
    Queue::push('search',$id);
    redirect(url('campaign',['id'=>$id]));}
  if($a==='delete'){
    $id=(int)$_POST['id'];
    $ids=array_column(DB::all('SELECT id FROM leads WHERE campaign_id=?',[$id]),'id');
    foreach(DB::all('SELECT pdf FROM reports WHERE lead_id IN ('.DB::in($ids)[0].')',DB::in($ids)[1]) as $r)@unlink(ROOT.'/'.$r['pdf']);
    if($ids){[$in,$v]=DB::in($ids);foreach(['reports','events','messages'] as $t)DB::q("DELETE FROM $t WHERE lead_id IN ($in)",$v);DB::q("DELETE FROM jobs WHERE type<>'search' AND ref_id IN ($in)",$v);}
    DB::q("DELETE FROM jobs WHERE type='search' AND ref_id=?",[$id]);
    DB::q('DELETE FROM leads WHERE campaign_id=?',[$id]);DB::q('DELETE FROM campaigns WHERE id=?',[$id]);
    flash('Search deleted.');redirect(url('campaigns'));}}
$rows=DB::all("SELECT c.*,COUNT(l.id) leads,SUM(l.website<>'') web,SUM(l.stage IN ('report_ready','contacted','opened','replied','won','lost')) rr,SUM(l.email_status IN ('sent','opened') OR l.wa_status IN ('sent','delivered','read','manual')) ct,SUM(l.replied_at IS NOT NULL) rp FROM campaigns c LEFT JOIN leads l ON l.campaign_id=c.id GROUP BY c.id ORDER BY c.id DESC LIMIT 200");
$src=setting('maps_source','places');
?>
<div class="card hero" id="new"><div class="card-h"><h3><?=icon('search')?>Find businesses on Google Maps</h3></div><form method="post" class="sf"><?=csrf_field()?><input type="hidden" name="a" value="create"><label class="f2">Keyword<input name="keyword" placeholder="e.g. dentist, interior designer, CA firm" required <?=isset($_GET['new'])?'autofocus':''?>></label><label class="f2">Location<input name="location" placeholder="e.g. Baner, Pune"></label><label>Listings<select name="limit" onchange="this.form.custom.hidden=this.value!=='custom'"><?php foreach([10,20,30,40,50,100] as $n):?><option value="<?=$n?>" <?=$n===20?'selected':''?>>Top <?=$n?></option><?php endforeach?><option value="custom">Custom…</option></select><input name="custom" type="number" min="1" max="500" placeholder="Number" hidden></label><label>Source<select name="source"><option value="places" <?=$src==='places'?'selected':''?>>Google Places API (max 60)</option><option value="serpapi" <?=$src==='serpapi'?'selected':''?>>SerpAPI Google Maps (100+)</option></select></label><button class="btn btn-g"><?=icon('search')?>Search</button></form><p class="hint">Listings are fetched with website, phone, address, rating & reviews. Missing phone numbers and emails are found automatically from the business website.</p></div>
<div class="card"><div class="card-h"><h3>All searches</h3><span class="muted"><?=count($rows)?></span></div><?php if(!$rows):?><div class="empty"><?=icon('search')?><h4>No searches yet</h4><p>Your searches and their progress will appear here.</p></div><?php else:?><div class="tw"><table class="tbl"><thead><tr><th>Keyword</th><th>Location</th><th>Leads</th><th>With website</th><th>Reports</th><th>Contacted</th><th>Replied</th><th>Status</th><th>Created</th><th></th></tr></thead><tbody><?php foreach($rows as $c):?><tr><td><a href="<?=url('campaign',['id'=>$c['id']])?>"><b><?=e($c['keyword'])?></b></a><br><small class="muted"><?=e(strtoupper($c['source']))?></small></td><td><?=e($c['location'])?></td><td><?=(int)$c['leads']?>/<?=$c['limit_n']?></td><td><?=(int)$c['web']?></td><td><?=(int)$c['rr']?></td><td><?=(int)$c['ct']?></td><td><?=(int)$c['rp']?></td><td><?=cbadge($c['status'])?></td><td class="muted"><?=ago($c['created_at'])?></td><td class="act"><a class="ib" href="<?=url('campaign',['id'=>$c['id']])?>" title="Open"><?=icon('eye')?></a><form method="post" onsubmit="return confirm('Delete this search with all its leads, reports and history?')"><?=csrf_field()?><input type="hidden" name="a" value="delete"><input type="hidden" name="id" value="<?=$c['id']?>"><button class="ib red" title="Delete"><?=icon('trash')?></button></form></td></tr><?php endforeach?></tbody></table></div><?php endif?></div>
