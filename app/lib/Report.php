<?php
use Dompdf\Dompdf;use Dompdf\Options;
final class Report{
  const SYSTEM="You are a senior local SEO, Google Business Profile and conversion consultant with 15 years of experience. You write premium, deeply researched audit reports on behalf of a digital marketing agency; the report is sent to the business owner to win them as a client, so it must feel genuine, specific and expert – never generic.\nOUTPUT RULES: Return ONLY an HTML fragment (no <html>, <head>, <body>, no markdown, no code fences). Allowed tags: h2, h3, p, ul, ol, li, strong, em, table, thead, tbody, tr, th, td, blockquote, br. No inline styles, images or scripts. Score gauges, charts and the detailed check list are added automatically – do not repeat them as raw lists. Use the business name naturally. Never invent measurements that are not in the data; when estimating (search demand, customer value, enquiries) clearly label it as an estimate and keep it conservative.";
  const PLACES=['cover'=>'Cover','summary'=>'Executive summary','journey'=>'Customer journey','website'=>'Website','maps'=>'Google Maps','competitors'=>'Competitors','growth'=>'Revenue / ROI','plan'=>'Action plan','tips'=>'Quick wins','why'=>'Why us','cta'=>'Next step / closing','any'=>'Any section'];
  static function competitors($lead){
    if(!$lead['campaign_id'])return [];
    return DB::all('SELECT name,rating,reviews,position,website FROM leads WHERE campaign_id=? AND id<>? ORDER BY position LIMIT 5',[$lead['campaign_id'],$lead['id']]);}
  static function prompt($type,$id=null){
    return $id?DB::row('SELECT * FROM prompts WHERE id=?',[$id]):DB::row('SELECT * FROM prompts WHERE type=? ORDER BY is_active DESC,id LIMIT 1',[$type]);}
  static function compact($audit){return je(array_map(fn($c)=>['area'=>$c['cat']??'','check'=>$c['label'],'status'=>$c['status'],'detail'=>$c['detail']],$audit['checks']??[]));}
  static function generate($leadId,$opt=[],$progress=null){
    $P=fn($p,$m)=>$progress?$progress($p,$m):null;
    $lead=DB::row('SELECT l.*,COALESCE(c.keyword,l.category) keyword FROM leads l LEFT JOIN campaigns c ON c.id=l.campaign_id WHERE l.id=?',[$leadId]);
    if(!$lead)throw new Exception('Lead not found');
    $P(60,'Preparing audit data for the AI');
    $a=jd($lead['audit']);
    $hasWeb=!empty($lead['website'])&&empty($a['website']['meta']['down']);
    $p=self::prompt($hasWeb?'report_website':'report_noweb',(int)($opt[$hasWeb?'pw':'pn']??0)?:null);
    if(!$p)throw new Exception('No report prompt found');
    $comp=self::competitors($lead);
    $cats=array_merge($a['website']['cats']??[],$a['map']['cats']??[]);
    $vars=lead_vars($lead,['web_score'=>$lead['web_score']??'N/A','map_score'=>$lead['map_score']??'N/A','web_categories'=>$cats?implode(', ',array_map(fn($k,$v)=>"$k $v/100",array_keys($cats),$cats)):'n/a','position'=>$lead['position']?:'n/a','website_audit'=>$hasWeb?self::compact($a['website']??[]):'No website','map_audit'=>self::compact($a['map']??[]),'competitors'=>$comp?je($comp):'none found','services'=>str_replace("\n",'; ',setting('services'))]);
    $P(65,'AI is researching & writing the report (usually 1–3 min)');
    $raw=AI::complete(self::SYSTEM,tpl($p['content'],$vars),16000,null,['research'=>setting('ai_web_research','1')==='1']);
    $data=[];if(preg_match('~<!--\s*DATA\s*(\{.*?\})\s*-->~s',$raw,$m))$data=json_decode($m[1],true)?:[];
    $html=self::clean($raw);
    if(mb_strlen(strip_tags($html))<300)throw new Exception('The AI returned an unusually short report – please try again.');
    $P(86,'Designing the PDF: charts, illustrations & layout');
    $tok=token(16);
    $full=self::render($lead,$a,$html,$hasWeb,$comp,$data,$p['id']);
    $P(92,'Rendering PDF pages');
    $pdf=self::pdf($full);
    $P(97,'Saving report');
    $file='storage/reports/'.date('Ym').'/'.preg_replace('/[^a-z0-9]+/i','-',mb_substr($lead['name'],0,40)).'-'.$tok.'.pdf';
    @mkdir(dirname(ROOT.'/'.$file),0755,true);
    file_put_contents(ROOT.'/'.$file,$pdf);
    return DB::insert('reports',['lead_id'=>$leadId,'prompt_id'=>$p['id'],'token'=>$tok,'html'=>$html.($data?'<!--DATA '.je($data).' -->':''),'pdf'=>$file]);}
  static function clean($h){
    $h=preg_replace('~^```(?:html)?\s*|\s*```$~i','',trim($h));
    $h=preg_replace('~<(script|style|head)[^>]*>.*?</\1>~is','',$h);
    $h=preg_replace('~<!--.*?-->~s','',$h);
    $h=strip_tags($h,'<h2><h3><p><ul><ol><li><strong><b><em><i><table><thead><tbody><tr><th><td><blockquote><br>');
    return trim(preg_replace('~<(\w+)\s[^>]*>~','<$1>',$h));}
  static function col($s){return $s>=75?'#15803d':($s>=50?'#b45309':'#b91c1c');}
  static function fontFace($name){
    $f=STORAGE.'/fonts/'.preg_replace('/[^a-z0-9_-]/i','',$name).'.ttf';
    return is_file($f)?"@font-face{font-family:'$name';src:url('".str_replace('\\','/',$f)."') format('truetype');}":'';}
  static function fileUri($f){
    if(!is_file($f))return '';
    $mime=mime_content_type($f)?:'image/png';
    if($mime==='image/webp'&&function_exists('imagecreatefromwebp')){$i=imagecreatefromwebp($f);imagesavealpha($i,true);ob_start();imagepng($i);return 'data:image/png;base64,'.base64_encode(ob_get_clean());}
    if($mime==='text/plain'||$mime==='image/svg')$mime='image/svg+xml';
    return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($f));}
  static function logo(){return self::fileUri(ROOT.'/'.ltrim(setting('company_logo','assets/img/default-logo.png'),'/'));}
  // Uploaded doodle for a section (prompt-specific first), otherwise the built-in illustration
  static function art($key,$pid,$P,$A){
    static $any=null;
    $r=DB::row('SELECT file FROM assets WHERE placement=? AND (prompt_id=? OR prompt_id IS NULL) ORDER BY prompt_id IS NULL,RAND() LIMIT 1',[$key,$pid]);
    if(!$r&&$key!=='cover'){$any??=DB::all('SELECT file FROM assets WHERE placement=\'any\' AND (prompt_id=? OR prompt_id IS NULL)',[$pid]);if($any)$r=$any[array_rand($any)];}
    if($r&&($u=self::fileUri(ROOT.'/'.$r['file'])))return $u;
    return Illus::uri(Illus::get($key,$P,$A));}
  static function render($lead,$a,$body,$hasWeb,$comp,$data=[],$pid=0){
    $S=fn($k,$d='')=>setting($k,$d);
    $pri=$S('brand_primary','#0B0B0B');$acc=$S('brand_accent','#B8860B');$txt=$S('brand_text','#1A1A1A');
    $fh=$S('font_heading','DejaVu Sans');$fb=$S('font_body','DejaVu Sans');
    $pl=Illus::tint($pri,.92);$al=Illus::tint($acc,.85);
    $logo=self::logo();$cur=$S('currency_symbol','₹');
    $ws=$hasWeb?(int)$lead['web_score']:null;$ms=(int)$lead['map_score'];
    $overall=$ws===null?$ms:(int)round($ws*.55+$ms*.45);
    $checks=array_merge(array_map(fn($c)=>$c+['src'=>'Google Maps'],$a['map']['checks']??[]),$hasWeb?array_map(fn($c)=>$c+['src'=>'Website'],$a['website']['checks']??[]):[]);
    $cnt=['pass'=>0,'warn'=>0,'fail'=>0];foreach($checks as $c)$cnt[$c['status']]++;
    $mm=$a['map']['meta']??[];$wm=$a['website']['meta']??[];
    $title=$hasWeb?'Website & Google Maps Audit':'Google Maps Audit & Website Proposal';
    $css=self::fontFace($fh).self::fontFace($fb)."@page{margin:64px 48px 64px 48px}body{font-family:'$fb',DejaVu Sans,sans-serif;color:$txt;font-size:10.2pt;line-height:1.6}h1,h2,h3,h4{font-family:'$fh',DejaVu Sans,sans-serif;color:$pri;line-height:1.25;margin:0}h2{font-size:17pt;margin:0 0 4px}h3{font-size:12pt;margin:16px 0 6px;color:$pri}p{margin:0 0 9px}ul,ol{margin:0 0 10px 0;padding-left:18px}li{margin-bottom:5px}table.t{width:100%;border-collapse:collapse;margin:10px 0 16px}.t th{background:$pri;color:#fff;text-align:left;padding:8px;font-size:9pt}.t td{padding:8px;border-bottom:1px solid #e8e8ee;vertical-align:top;font-size:9.2pt}.t tr:nth-child(even) td{background:#fafafc}strong,b{color:$pri}blockquote{border-left:5px solid $acc;margin:12px 0;padding:10px 16px;background:$al;font-size:11pt;color:$pri}.sm{font-size:8.6pt;color:#6b7280}.pb{page-break-after:always}.np{page-break-before:always}.kick{font-size:8.5pt;letter-spacing:2.5px;text-transform:uppercase;color:$acc;font-weight:bold;margin-bottom:4px}.bar{height:4px;width:60px;background:$acc;margin:8px 0 16px}#hd{position:fixed;top:-44px;left:0;right:0;font-size:7.5pt;color:#9ca3af}#hd td{padding:0}#ft{position:fixed;bottom:-44px;left:0;right:0;font-size:7.5pt;color:#9ca3af;border-top:1px solid #e5e7eb;padding-top:6px}"
      .".cover{position:absolute;top:-64px;left:-48px;width:794px;height:1123px;background:$pri;color:#fff}.cv{padding:70px 64px 0}.cv .lg{height:90px}.cv .k{color:$acc;letter-spacing:4px;font-size:9.5pt;margin-top:36px;text-transform:uppercase;font-weight:bold}.cv h1{color:#fff;font-size:32pt;margin:10px 0 8px;line-height:1.15}.cv .for{font-size:15pt;color:$acc}.cv .meta{margin-top:18px;font-size:9.5pt;color:#d1d5db;line-height:1.8}.cv .art{text-align:center;margin-top:24px}.cv .art img{width:430px}.cvf{position:absolute;bottom:56px;left:64px;right:64px;font-size:8.5pt;color:#9ca3af;border-top:1px solid #374151;padding-top:12px}.cvb{position:absolute;bottom:0;left:0;width:794px;height:10px;background:$acc}"
      .".tile{border:1px solid #ececf2;border-radius:10px;padding:12px 14px;background:#fff}.tile .v{font-size:19pt;font-weight:bold;color:$pri;font-family:'$fh',DejaVu Sans}.tile .l{font-size:8pt;color:#6b7280;text-transform:uppercase;letter-spacing:1px}.tile .s{font-size:8.5pt;color:#6b7280}.card{border:1px solid #ececf2;border-radius:12px;padding:16px 18px;margin-bottom:14px;background:#fff}.soft{background:$pl;border:0}.pri{border-radius:8px;padding:9px 12px;margin-bottom:7px;background:#fff7f7;border-left:4px solid #E8554E}.pri.w{background:#fffbeb;border-left-color:#F2A41C}.pri b{color:#111827}.sec-h td{vertical-align:middle}.sec-h img{width:150px}.chip{display:inline-block;padding:2px 9px;border-radius:10px;font-size:8pt;font-weight:bold}.cat{margin:14px 0 4px;font-size:11pt;font-weight:bold;color:$pri}.ck td{padding:7px 8px;border-bottom:1px solid #f0f0f4;vertical-align:top;font-size:9pt}.ck .ic{width:20px;font-weight:bold;font-size:12pt;text-align:center}.cta{background:$pri;color:#fff;border-radius:14px;padding:22px 24px}.cta h2,.cta h3{color:$acc}.cta b,.cta strong{color:$acc}.svc td{padding:6px 4px;font-size:9.6pt;vertical-align:top}";
    $tile=fn($v,$l,$s='')=>'<td style="width:25%;padding:4px"><div class="tile"><div class="l">'.e($l).'</div><div class="v">'.$v.'</div><div class="s">'.($s?:'&nbsp;').'</div></div></td>';
    $o='<!doctype html><html><head><meta charset="utf-8"><style>'.$css.'</style></head><body>';
    $o.='<div id="hd"><table width="100%"><tr><td>'.e($S('company_name')).'</td><td style="text-align:right">Confidential · prepared for '.e($lead['name']).'</td></tr></table></div><div id="ft">'.e($S('company_name')).' &nbsp;·&nbsp; '.e($S('company_phone')).' &nbsp;·&nbsp; '.e($S('company_email')).' &nbsp;·&nbsp; '.e(preg_replace('~^https?://|/$~','',$S('company_website'))).'</div>';
    // Cover
    $o.='<div class="cover"><div class="cv">'.($logo?'<img class="lg" src="'.$logo.'">':'<h2 style="color:#fff">'.e($S('company_name')).'</h2>').'<div class="k">Free Digital Growth Audit · '.date('F Y').'</div><h1>'.e($title).'</h1><div class="for">Prepared for '.e($lead['name']).'</div><div class="meta">'.e(trim($lead['category'].($lead['city']?' · '.$lead['city']:''),' ·')).'<br>'.e($lead['address']).($lead['website']?'<br>'.e($lead['website']):'').'</div><div class="art"><img src="'.self::art('cover',$pid,$pri,$acc).'"></div></div><div class="cvf"><b style="color:'.$acc.'">'.e($S('company_name')).'</b><br>'.e($S('company_address')).'<br>'.e($S('company_phone')).' · '.e($S('company_email')).' · '.e($S('company_website')).'</div><div class="cvb"></div></div><div class="pb"></div>';
    // Snapshot
    $o.='<div class="kick">Snapshot</div><h2>Your Digital Health Score</h2><div class="bar"></div>';
    $o.='<table width="100%"><tr><td style="width:33%;text-align:center"><img src="'.Charts::gauge($overall,'Overall').'" style="width:150px"></td>'.($hasWeb?'<td style="width:33%;text-align:center"><img src="'.Charts::gauge($ws,'Website').'" style="width:150px"></td>':'<td style="width:33%;text-align:center;vertical-align:middle"><div class="tile" style="text-align:center;border-color:#fecaca;background:#fff5f5"><div class="v" style="color:#b91c1c">No website</div><div class="s">Customers cannot learn more or enquire online</div></div></td>').'<td style="width:33%;text-align:center"><img src="'.Charts::gauge($ms,'Google Maps').'" style="width:150px"></td></tr></table>';
    $o.='<table width="100%" style="margin-top:6px"><tr>'.$tile(($lead['rating']?e($lead['rating']).' ★':'—'),'Google rating',!empty($mm['competitor_avg_rating'])?'Competitors: '.$mm['competitor_avg_rating'].' ★':'').$tile((int)$lead['reviews'],'Reviews',!empty($mm['competitor_avg_reviews'])?'Competitors avg: '.$mm['competitor_avg_reviews']:'').$tile($lead['position']?'#'.(int)$lead['position']:'—','Maps position',$lead['position']&&$lead['position']>3?'Outside the top 3':'').$tile($cnt['pass'].'/'.count($checks),'Checks passed',$cnt['fail'].' problems found').'</tr></table>';
    $cats=array_merge($hasWeb?($a['website']['cats']??[]):[],$a['map']['cats']??[]);
    if($cats)$o.='<h3>Score by area</h3><img src="'.Charts::bars($cats).'" style="width:100%">';
    $o.='<img src="'.Charts::strip($cnt['pass'],$cnt['warn'],$cnt['fail']).'" style="width:100%;margin-top:6px">';
    $pri_=array_filter($checks,fn($c)=>$c['status']!=='pass');usort($pri_,fn($x,$y)=>[$y['status']==='fail',$y['w']]<=>[$x['status']==='fail',$x['w']]);
    if($pri_){$o.='<h3>Top priorities</h3>';foreach(array_slice($pri_,0,6) as $c)$o.='<div class="pri'.($c['status']==='warn'?' w':'').'"><b>'.e($c['label']).'</b> <span class="sm">· '.e($c['src']).'</span><br><span style="font-size:9pt">'.e($c['detail']).'</span></div>';}
    // Competitor benchmark
    if($comp){
      $rows=array_merge([[$lead['name'].' (you)',(int)$lead['reviews'],1]],array_map(fn($c)=>[$c['name'],(int)$c['reviews'],0],$comp));
      usort($rows,fn($x,$y)=>$y[1]<=>$x[1]);
      $rr=array_merge([[$lead['name'].' (you)',(float)$lead['rating'],1]],array_map(fn($c)=>[$c['name'],(float)$c['rating'],0],$comp));
      $o.='<div class="np"></div><table width="100%" class="sec-h"><tr><td><div class="kick">Market position</div><h2>You vs. Your Competitors</h2><div class="bar"></div><p>How '.e($lead['name']).' compares with the businesses Google shows for <b>"'.e($lead['keyword']).'"</b>.</p></td><td style="width:170px;text-align:right"><img src="'.self::art('competitors',$pid,$pri,$acc).'"></td></tr></table>';
      $o.='<div class="card"><h3 style="margin-top:0">Google reviews</h3><img src="'.Charts::compare($rows,'',520,$acc).'" style="width:100%"></div><div class="card"><h3 style="margin-top:0">Average rating</h3><img src="'.Charts::compare($rr,' ★',520,$acc).'" style="width:100%"></div>';
      $tb='<tr style="background:'.$al.'"><td><b>#'.(int)$lead['position'].'</b></td><td><b>'.e($lead['name']).' (you)</b></td><td>'.($lead['rating']?e($lead['rating']).' ★':'—').'</td><td>'.(int)$lead['reviews'].'</td><td>'.($lead['website']?'Yes':'<b style="color:#b91c1c">No</b>').'</td></tr>';
      foreach($comp as $c)$tb.='<tr><td>#'.(int)$c['position'].'</td><td>'.e($c['name']).'</td><td>'.($c['rating']?e($c['rating']).' ★':'—').'</td><td>'.(int)$c['reviews'].'</td><td>'.($c['website']?'Yes':'No').'</td></tr>';
      $o.='<table class="t"><thead><tr><th>Rank</th><th>Business</th><th>Rating</th><th>Reviews</th><th>Website</th></tr></thead><tbody>'.$tb.'</tbody></table>';}
    // AI narrative, split by section with illustrations
    $parts=preg_split('~(<h2>.*?</h2>)~is',$body,-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
    $o.='<div class="np"></div>';$first=true;$used=[];
    for($i=0;$i<count($parts);$i++){
      $pt=$parts[$i];
      if(!preg_match('~^<h2>(.*?)</h2>$~is',$pt,$hm)){$o.='<div>'.self::tables($pt).'</div>';continue;}
      $h=html_entity_decode(strip_tags($hm[1]),ENT_QUOTES,'UTF-8');$key=Illus::forHeading($h);$sec=self::tables($parts[$i+1]??'');$i++;
      $big=in_array($key,['website','maps','plan','why'],true)&&!$first;
      $img=$key&&!isset($used[$key])?'<td style="width:160px;text-align:right"><img src="'.self::art($key,$pid,$pri,$acc).'"></td>':'';if($key)$used[$key]=1;
      $o.=($big?'<div class="np"></div>':'<div style="height:14px"></div>').'<table width="100%" class="sec-h"><tr><td><div class="kick">'.e(self::kicker($key)).'</div><h2>'.e($h).'</h2><div class="bar"></div></td>'.$img.'</tr></table>'.$sec;
      if($key==='growth'&&$data)$o.=self::roiBlock($data,$hasWeb,$cur,$acc,$pri);
      $first=false;}
    // Detailed checks
    $o.='<div class="np"></div><div class="kick">Appendix</div><h2>Detailed Audit Checklist</h2><div class="bar"></div><p class="sm">Every check we ran, grouped by area. ✔ = good · ! = needs work · ✘ = problem.</p>';
    $ic=['pass'=>['✔','#15803d'],'warn'=>['!','#b45309'],'fail'=>['✘','#b91c1c']];
    foreach(['Google Maps'=>$a['map']??null,'Website'=>$hasWeb?($a['website']??null):null] as $src=>$au){
      if(!$au)continue;
      $o.='<h3>'.$src.' · '.(int)$au['score'].'/100</h3>';
      $by=[];foreach($au['checks'] as $c)$by[$c['cat']??'Other'][]=$c;
      foreach($by as $cat=>$list){
        $sc=$au['cats'][$cat]??null;
        $o.='<div class="cat">'.e($cat).($sc!==null?' <span class="chip" style="background:'.Illus::tint(Charts::col($sc),.82).';color:'.self::col($sc).'">'.$sc.'/100</span>':'').'</div><table width="100%" class="ck">';
        usort($list,fn($x,$y)=>['fail'=>0,'warn'=>1,'pass'=>2][$x['status']]<=>['fail'=>0,'warn'=>1,'pass'=>2][$y['status']]);
        foreach($list as $c)$o.='<tr><td class="ic" style="color:'.$ic[$c['status']][1].'">'.$ic[$c['status']][0].'</td><td><b>'.e($c['label']).'</b><br><span class="sm">'.e($c['detail']).'</span></td></tr>';
        $o.='</table>';}}
    // About us + CTA
    $svc='';foreach(array_filter(array_map('trim',explode("\n",$S('services')))) as $s)$svc.='<tr><td style="width:18px;color:'.$acc.';font-weight:bold">✔</td><td>'.e($s).'</td></tr>';
    $o.='<div class="np"></div><table width="100%" class="sec-h"><tr><td><div class="kick">About us</div><h2>'.e($S('company_name')).'</h2><div class="bar"></div><p>'.nl2br(e($S('company_about'))).'</p></td><td style="width:170px;text-align:right"><img src="'.self::art('why',$pid,$pri,$acc).'"></td></tr></table>';
    $o.='<div class="card soft"><h3 style="margin-top:0">What we do</h3><table class="svc">'.$svc.'</table></div>';
    $o.='<div class="cta"><table width="100%"><tr><td><h2>Ready to get more customers from Google?</h2><p style="margin-top:10px">Reply to our message or call <b>'.e($S('company_phone')).'</b> to book a free 20-minute strategy call. We will walk you through this report and show exactly how to fix the gaps.</p><p>Email: <b>'.e($S('company_email')).'</b><br>Web: '.e($S('company_website')).'</p></td><td style="width:170px;text-align:right"><img src="'.self::art('cta',$pid,$pri,$acc).'" style="width:160px"></td></tr></table></div>';
    $o.='<p class="sm" style="margin-top:16px">This report was prepared on '.date('d M Y').' using live checks of the public website and Google Maps listing. Figures marked as estimates are based on typical industry benchmarks and are indicative only.</p>';
    return $o.'</body></html>';}
  private static function kicker($k){return ['summary'=>'Overview','journey'=>'Customer journey','website'=>'Website audit','maps'=>'Local search','competitors'=>'Competition','growth'=>'Opportunity','plan'=>'Roadmap','tips'=>'Do it yourself','why'=>'Our approach','cta'=>"Let's talk"][$k]??'Insight';}
  private static function tables($h){return str_replace('<table>','<table class="t">',$h);}
  private static function roiBlock($d,$hasWeb,$cur,$acc,$pri){
    $cv=(float)($d['customer_value']??0);$ex=(float)($d['extra_enquiries_month']??0);$cr=(float)($d['conversion_rate']??0);$be=(int)($d['break_even_customers']??0);$ms=(int)($d['monthly_searches']??0);
    $fee=(float)setting('yearly_fee','0')+($hasWeb?0:(float)setting('website_fee','0'));
    if($cv<=0)return '';
    if($cr>1)$cr/=100;
    $cust=$ex>0?round($ex*12*($cr>0?$cr:.25)):0;$ret=$cust*$cv;
    $f=fn($n)=>$cur.number_format($n);
    $o='<div class="card soft"><table width="100%"><tr>'.($ms?'<td style="text-align:center"><div class="tile"><div class="l">Est. monthly searches</div><div class="v">'.number_format($ms).'</div></div></td>':'').'<td style="text-align:center"><div class="tile"><div class="l">Value of 1 customer</div><div class="v">'.$f($cv).'</div></div></td><td style="text-align:center"><div class="tile"><div class="l">Break-even</div><div class="v">'.($be?:ceil($fee/$cv)).' customers</div></div></td>'.($ex?'<td style="text-align:center"><div class="tile"><div class="l">Extra enquiries / month</div><div class="v">+'.(int)$ex.'</div></div></td>':'').'</tr></table>';
    if($ret>0)$o.='<img src="'.Charts::roi([['Your investment / yr',$fee,'#94A3B8',$f($fee)],['Potential revenue / yr',$ret,$acc,$f($ret)]]).'" style="width:100%;margin-top:8px"><p class="sm">Potential revenue = '.(int)$ex.' extra enquiries × 12 months × '.round(($cr>0?$cr:.25)*100).'% conversion × '.$f($cv).' per customer (conservative estimate).</p>';
    return $o.'</div>';}
  static function pdf($html){
    $o=new Options();
    $o->set(['isRemoteEnabled'=>false,'isHtml5ParserEnabled'=>true,'chroot'=>[ROOT],'fontDir'=>STORAGE.'/fonts','fontCache'=>STORAGE.'/fonts','tempDir'=>STORAGE.'/tmp','defaultFont'=>'DejaVu Sans','dpi'=>96]);
    $d=new Dompdf($o);
    $d->setPaper('A4');
    $d->loadHtml($html,'UTF-8');
    $d->render();
    $c=$d->getCanvas();
    $c->page_script(function($n,$t,$canvas,$fm){if($n>1)$canvas->text($canvas->get_width()-92,$canvas->get_height()-38,'Page '.$n.' of '.$t,$fm->getFont('DejaVu Sans'),7.5,[.6,.6,.6]);});
    return $d->output();}}
