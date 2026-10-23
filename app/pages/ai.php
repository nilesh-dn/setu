<?php
$title='AI Connection';
$P=AI::providers();
if(is_post()){
  $p=$_POST['p']??'';
  if($p==='research'){Settings::set('ai_web_research',empty($_POST['on'])?'0':'1');flash('Web research '.(empty($_POST['on'])?'off':'on').'.');redirect(url('ai'));}
  if(isset($P[$p])){
    if(($_POST['key']??'')!=='')Settings::set('ai_key_'.$p,trim($_POST['key']));
    if(!empty($_POST['clear']))Settings::set('ai_key_'.$p,'');
    if(isset($_POST['model']))Settings::set('ai_model_'.$p,trim($_POST['model']));
    if($p==='custom'&&isset($_POST['url']))Settings::set('ai_custom_url',trim($_POST['url']));
    if(($_POST['a']??'')==='use'||!setting('ai_key_'.setting('ai_provider')))Settings::set('ai_provider',$p);
    if(($_POST['a']??'')==='test'){
      $t0=microtime(true);
      try{$r=AI::complete('You are a helpful assistant.','Reply with exactly: Connection OK',200,$p);flash('<b>'.e($P[$p]['name']).'</b> replied in '.round(microtime(true)-$t0,1).'s: “'.e(mb_strimwidth(trim($r),0,120,'…')).'”');}
      catch(Throwable $e){flash(e($e->getMessage()),'err');}}
    else flash(e($P[$p]['name']).' saved.');}
  redirect(url('ai').'#'.$p);}
$act=setting('ai_provider','anthropic');
?>
<div class="card hero"><div class="card-h"><h3><span class="hi hi-y"><?=icon('spark')?></span>How AI is used in Setu</h3></div><div class="flow"><div><b>1</b><span>We audit the website & Google Maps listing (no AI cost)</span></div><div><b>2</b><span>The audit data + your report prompt are sent to the active AI</span></div><div><b>3</b><span>AI writes the report: issues, competitor gaps, ROI & pitch</span></div><div><b>4</b><span>Setu designs your branded PDF with charts &amp; illustrations</span></div></div><p class="hint">Active provider: <b><?=e($P[$act]['name']??$act)?></b> · model <code><?=e(AI::model())?></code>. One report uses roughly 8-14k input tokens and 4-6k output tokens.</p><form method="post" class="row mt" style="position:relative;z-index:1"><?=csrf_field()?><input type="hidden" name="p" value="research"><label class="chk" style="color:#fff"><span class="sw"><input type="checkbox" name="on" value="1" <?=setting('ai_web_research','1')==='1'?'checked':''?> onchange="this.form.submit()"><i></i></span> Let Claude research the web (search demand, industry benchmarks) while writing reports</label></form></div>
<div class="aig"><?php foreach($P as $k=>$p):$has=setting('ai_key_'.$k)!=='';?><form method="post" class="card frm aic <?=$act===$k?'on':''?>" id="<?=$k?>"><?=csrf_field()?><input type="hidden" name="p" value="<?=$k?>"><div class="card-h"><h3><?=e($p['name'])?></h3><?=$act===$k?'<span class="bdg bdg-green">Active</span>':($has?'<span class="bdg bdg-teal">Connected</span>':'<span class="bdg bdg-gray">Not connected</span>')?></div><p class="hint"><?=e($p['note'])?></p><ol class="ol"><?php foreach($p['steps'] as $s):?><li><?=e($s)?></li><?php endforeach?></ol><?php if($p['url']):?><a class="lnk" href="<?=e($p['url'])?>" target="_blank" rel="noopener"><?=icon('link')?>Get API key</a><?php endif?><?php if($k==='custom'):?><label>Base URL<input name="url" value="<?=e(setting('ai_custom_url'))?>" placeholder="https://api.example.com/v1"></label><?php endif?><label>API key<input type="password" name="key" autocomplete="new-password" placeholder="<?=$has?'•••••••• saved – type to replace':'Paste your API key'?>"></label><label>Model<input name="model" value="<?=e(setting('ai_model_'.$k))?>" placeholder="<?=e($p['models'])?>"><small>Examples: <?=e($p['models'])?></small></label><?php if($has):?><label class="chk sm"><input type="checkbox" name="clear" value="1"> Remove saved key</label><?php endif?><div class="row"><button class="btn" name="a" value="save">Save</button><button class="btn" name="a" value="test"><?=icon('zap')?>Test</button><?php if($act!==$k):?><button class="btn btn-y" name="a" value="use">Use this</button><?php endif?></div></form><?php endforeach?></div>
