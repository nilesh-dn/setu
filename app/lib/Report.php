<?php
use Dompdf\Dompdf;use Dompdf\Options;
final class Report{
  const SYSTEM="You are a senior local SEO and Google Business Profile consultant writing a persuasive, factual audit report on behalf of a digital marketing agency. The report is sent to the business owner to win them as a client.\nOUTPUT RULES: Return ONLY an HTML fragment (no <html>, <head>, <body>, no markdown, no code fences). Allowed tags: h2, h3, p, ul, ol, li, strong, em, table, thead, tbody, tr, th, td, blockquote, br. No inline styles, no images, no scripts. Do not repeat the score tables – they are added automatically. Use the business name naturally. Never invent facts that are not in the data; when estimating (lead value, enquiries) clearly say it is an estimate.";
  static function competitors($lead){
    $rows=DB::all('SELECT name,rating,reviews,position,website FROM leads WHERE campaign_id=? AND id<>? ORDER BY position LIMIT 3',[$lead['campaign_id'],$lead['id']]);
    return $rows;}
  static function prompt($type,$id=null){
    return $id?DB::row('SELECT * FROM prompts WHERE id=?',[$id]):DB::row('SELECT * FROM prompts WHERE type=? ORDER BY is_active DESC,id LIMIT 1',[$type]);}
  static function compact($audit){return je(array_map(fn($c)=>['check'=>$c['label'],'status'=>$c['status'],'detail'=>$c['detail']],$audit['checks']??[]));}
  static function generate($leadId,$opt=[]){
    $lead=DB::row('SELECT l.*,c.keyword FROM leads l JOIN campaigns c ON c.id=l.campaign_id WHERE l.id=?',[$leadId]);
    if(!$lead)throw new Exception('Lead not found');
    $a=jd($lead['audit']);
    $hasWeb=!empty($lead['website'])&&empty($a['website']['meta']['down']);
    $p=self::prompt($hasWeb?'report_website':'report_noweb',(int)($opt[$hasWeb?'pw':'pn']??0)?:null);
    if(!$p)throw new Exception('No report prompt found');
    $comp=self::competitors($lead);
    $vars=lead_vars($lead,['web_score'=>$lead['web_score']??'N/A','map_score'=>$lead['map_score']??'N/A','website_audit'=>$hasWeb?self::compact($a['website']??[]):'No website','map_audit'=>self::compact($a['map']??[]),'competitors'=>$comp?je($comp):'none found','services'=>str_replace("\n",'; ',setting('services'))]);
    $html=self::clean(AI::complete(self::SYSTEM,tpl($p['content'],$vars)));
    $tok=token(16);
    $full=self::render($lead,$a,$html,$hasWeb,$comp);
    $file='storage/reports/'.date('Ym').'/'.preg_replace('/[^a-z0-9]+/i','-',mb_substr($lead['name'],0,40)).'-'.$tok.'.pdf';
    @mkdir(dirname(ROOT.'/'.$file),0755,true);
    file_put_contents(ROOT.'/'.$file,self::pdf($full));
    $id=DB::insert('reports',['lead_id'=>$leadId,'prompt_id'=>$p['id'],'token'=>$tok,'html'=>$html,'pdf'=>$file]);
    return $id;}
  static function clean($h){
    $h=preg_replace('~^```(?:html)?\s*|\s*```$~i','',trim($h));
    $h=preg_replace('~<(script|style|head)[^>]*>.*?</\1>~is','',$h);
    $h=strip_tags($h,'<h2><h3><p><ul><ol><li><strong><b><em><i><table><thead><tbody><tr><th><td><blockquote><br>');
    return preg_replace('~<(\w+)\s[^>]*>~','<$1>',$h);}
  static function col($s){return $s>=75?'#15803d':($s>=50?'#b45309':'#b91c1c');}
  static function fontFace($name){
    $f=STORAGE.'/fonts/'.preg_replace('/[^a-z0-9_-]/i','',$name).'.ttf';
    return is_file($f)?"@font-face{font-family:'$name';src:url('".str_replace('\\','/',$f)."') format('truetype');}":'';}
  static function logo(){
    $f=ROOT.'/'.ltrim(setting('company_logo','assets/img/default-logo.png'),'/');
    if(!is_file($f))return '';
    $mime=mime_content_type($f)?:'image/png';
    if($mime==='image/webp'&&function_exists('imagecreatefromwebp')){$i=imagecreatefromwebp($f);ob_start();imagepng($i);$d=ob_get_clean();return 'data:image/png;base64,'.base64_encode($d);}
    return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($f));}
  static function checks($title,$a){
    if(!$a||empty($a['checks']))return '';
    $ic=['pass'=>['✔','#15803d','Good'],'warn'=>['!','#b45309','Improve'],'fail'=>['✘','#b91c1c','Problem']];
    $rows='';
    usort($a['checks'],fn($x,$y)=>['fail'=>0,'warn'=>1,'pass'=>2][$x['status']]<=>['fail'=>0,'warn'=>1,'pass'=>2][$y['status']]);
    foreach($a['checks'] as $c){$i=$ic[$c['status']];$rows.='<tr><td class="st" style="color:'.$i[1].'">'.$i[0].'</td><td><b>'.e($c['label']).'</b><br><span class="sm">'.e($c['detail']).'</span></td><td class="tag" style="color:'.$i[1].'">'.$i[2].'</td></tr>';}
    return '<h2 style="page-break-before:always;margin-top:0">'.e($title).'</h2><table class="chk">'.$rows.'</table>';}
  static function render($lead,$a,$body,$hasWeb,$comp){
    $S=fn($k,$d='')=>setting($k,$d);
    $pri=$S('brand_primary','#0B0B0B');$acc=$S('brand_accent','#B8860B');$txt=$S('brand_text','#1A1A1A');
    $fh=$S('font_heading','DejaVu Sans');$fb=$S('font_body','DejaVu Sans');
    $logo=self::logo();
    $ws=$lead['web_score'];$ms=$lead['map_score'];
    $score=fn($label,$v)=>'<td class="sc"><div class="scv" style="color:'.($v===null?'#777':self::col($v)).'">'.($v===null?'<span style="font-size:18pt;color:#b91c1c">No website</span>':(int)$v.'<span>/100</span>').'</div><div class="scl">'.$label.'</div></td>';
    $compRows='';foreach($comp as $c)$compRows.='<tr><td>#'.(int)$c['position'].'</td><td>'.e($c['name']).'</td><td>'.($c['rating']?e($c['rating']).' ★':'—').'</td><td>'.(int)$c['reviews'].'</td><td>'.($c['website']?'Yes':'No').'</td></tr>';
    $you='<tr class="you"><td>#'.(int)$lead['position'].'</td><td>'.e($lead['name']).' (you)</td><td>'.($lead['rating']?e($lead['rating']).' ★':'—').'</td><td>'.(int)$lead['reviews'].'</td><td>'.($lead['website']?'Yes':'No').'</td></tr>';
    $css=self::fontFace($fh).self::fontFace($fb)."@page{margin:70px 46px 70px 46px}body{font-family:'$fb',DejaVu Sans,sans-serif;color:$txt;font-size:10.5pt;line-height:1.55}h1,h2,h3{font-family:'$fh',DejaVu Sans,sans-serif;color:$pri;line-height:1.25}h2{page-break-after:avoid;font-size:15pt;border-bottom:2px solid $acc;padding-bottom:5px;margin:26px 0 10px}h3{font-size:12pt;margin:16px 0 6px}p{margin:0 0 9px}ul,ol{margin:0 0 10px 0;padding-left:18px}li{margin-bottom:4px}table{width:100%;border-collapse:collapse;margin:8px 0 14px}th{background:$pri;color:#fff;text-align:left;padding:7px 8px;font-size:9.5pt}td{padding:7px 8px;border-bottom:1px solid #e6e1d3;vertical-align:top;font-size:9.5pt}strong,b{color:$pri}blockquote{border-left:4px solid $acc;margin:10px 0;padding:6px 12px;background:#faf7ef}.sm{font-size:8.8pt;color:#555}.chk td.st{width:22px;font-size:13pt;font-weight:bold;text-align:center}.chk td.tag{width:62px;font-size:8.5pt;font-weight:bold;text-align:right}.cover{position:absolute;top:-70px;left:-46px;right:-46px;height:1123px;background:$pri;color:#fff}.cv{padding:90px 70px 0}.cv img{height:120px}.cv .k{color:$acc;letter-spacing:3px;font-size:10pt;margin-top:70px;text-transform:uppercase}.cv h1{color:#fff;font-size:30pt;margin:12px 0 10px}.cv .for{font-size:15pt;color:$acc}.cv .meta{margin-top:40px;font-size:10pt;color:#ddd;line-height:1.8}.cv .bar{height:5px;background:$acc;width:120px;margin:26px 0}.cvf{position:absolute;bottom:60px;left:70px;right:70px;font-size:9pt;color:#bbb;border-top:1px solid #444;padding-top:12px}.pb{page-break-after:always}.scs{margin:10px 0 6px}.sc{text-align:center;border:1px solid #e6e1d3;padding:14px;width:50%;background:#fcfbf7}.scv{font-size:34pt;font-weight:bold;font-family:'$fh',DejaVu Sans}.scv span{font-size:12pt;color:#888}.scl{font-size:10pt;color:#555;text-transform:uppercase;letter-spacing:1px}.you td{background:#faf3df;font-weight:bold}#ft{position:fixed;bottom:-48px;left:0;right:0;font-size:8pt;color:#777;border-top:1px solid $acc;padding-top:6px}#hd{position:fixed;top:-50px;left:0;right:0;font-size:8pt;color:#999;text-align:right}.cta{background:$pri;color:#fff;padding:18px 22px;margin-top:24px}.cta b,.cta strong{color:$acc}.cta h3{color:$acc;margin-top:0}";
    $title=$hasWeb?'Website & Google Maps Audit':'Google Maps Audit & Website Proposal';
    $o='<!doctype html><html><head><meta charset="utf-8"><style>'.$css.'</style></head><body>';
    $o.='<div id="hd">'.e($S('company_name')).' · Confidential report for '.e($lead['name']).'</div><div id="ft">'.e($S('company_name')).' &nbsp;|&nbsp; '.e($S('company_phone')).' &nbsp;|&nbsp; '.e($S('company_email')).' &nbsp;|&nbsp; '.e(preg_replace('~^https?://|/$~','',$S('company_website'))).'</div>';
    $o.='<div class="cover"><div class="cv">'.($logo?'<img src="'.$logo.'">':'<h2 style="color:#fff">'.e($S('company_name')).'</h2>').'<div class="k">Free Digital Growth Audit</div><h1>'.e($title).'</h1><div class="for">Prepared for '.e($lead['name']).'</div><div class="bar"></div><div class="meta">'.e($lead['category']).($lead['city']?' · '.e($lead['city']):'').'<br>'.e($lead['address']).'<br>'.($lead['website']?e($lead['website']).'<br>':'').'Date: '.date('d M Y').'</div></div><div class="cvf"><b style="color:'.$acc.'">'.e($S('company_name')).'</b><br>'.e($S('company_address')).'<br>'.e($S('company_phone')).' · '.e($S('company_email')).' · '.e($S('company_website')).'</div></div><div class="pb"></div>';
    $o.='<h2 style="margin-top:0">Your Scores at a Glance</h2><table class="scs"><tr>'.$score('Website',$hasWeb?$ws:null).$score('Google Maps',$ms).'</tr></table>';
    $o.='<h3>Where you stand on Google Maps</h3><table><thead><tr><th>Rank</th><th>Business</th><th>Rating</th><th>Reviews</th><th>Website</th></tr></thead><tbody>'.$you.$compRows.'</tbody></table>';
    $o.='<div>'.$body.'</div>';
    $o.=self::checks('Detailed Google Maps Checks',$a['map']??null);
    if($hasWeb)$o.=self::checks('Detailed Website Checks',$a['website']??null);
    $o.='<div class="cta"><h3>Ready to get more customers from Google?</h3><p>Reply to this message or call <b>'.e($S('company_phone')).'</b> for a free 15-minute strategy call. Email: <b>'.e($S('company_email')).'</b> · '.e($S('company_website')).'</p><p class="sm" style="color:#ccc;margin:0">'.e($S('company_name')).' – '.e($S('company_tagline')).'</p></div>';
    return $o.'</body></html>';}
  static function pdf($html){
    $o=new Options();
    $o->set(['isRemoteEnabled'=>false,'isHtml5ParserEnabled'=>true,'chroot'=>[ROOT],'fontDir'=>STORAGE.'/fonts','fontCache'=>STORAGE.'/fonts','tempDir'=>STORAGE.'/tmp','defaultFont'=>'DejaVu Sans','dpi'=>96]);
    $d=new Dompdf($o);
    $d->setPaper('A4');
    $d->loadHtml($html,'UTF-8');
    $d->render();
    $c=$d->getCanvas();
    $c->page_text($c->get_width()-90,$c->get_height()-34,'Page {PAGE_NUM} of {PAGE_COUNT}','DejaVu Sans',7.5,[.5,.5,.5]);
    return $d->output();}}
