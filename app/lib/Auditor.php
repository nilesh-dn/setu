<?php
final class Auditor{
  private static function c(&$a,$label,$st,$detail,$w=1,$cat='SEO'){$a[]=['label'=>$label,'status'=>$st,'detail'=>$detail,'w'=>$w,'cat'=>$cat];}
  private static function score($checks){
    $t=0;$g=0;
    foreach($checks as $c){$t+=$c['w'];$g+=$c['w']*($c['status']==='pass'?1:($c['status']==='warn'?.5:0));}
    return $t?(int)round($g/$t*100):0;}
  static function website($site){
    $ch=[];$m=[];
    $r=Scraper::fetch($site);
    if($r['error']||$r['code']>=400||$r['code']===0){
      self::c($ch,'Website is reachable','fail','The website could not be opened ('.($r['error']?:'HTTP '.$r['code']).'). Visitors and Google see an error page.',5,'Technical');
      return ['score'=>0,'checks'=>$ch,'meta'=>['url'=>$site,'down'=>true]];}
    $h=$r['body'];$final=$r['url'];
    $m['url']=$final;$m['load_time']=round($r['time'],2);$m['ttfb']=round($r['ttfb'],2);$m['size_kb']=round($r['size']/1024);
    $dom=new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?>'.$h);
    libxml_clear_errors();
    $x=new DOMXPath($dom);
    $q=fn($p)=>$x->query($p);
    $meta=function($n)use($x){$e=$x->query("//meta[translate(@name,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='$n']/@content | //meta[@property='$n']/@content");return $e->length?trim($e->item(0)->nodeValue):null;};
    $https=stripos($final,'https://')===0;
    self::c($ch,'Secure HTTPS (SSL)',$https?'pass':'fail',$https?'Site loads securely over HTTPS.':'Site is not secure (no HTTPS). Browsers show "Not secure" and Google ranks it lower.',3,'Technical');
    $lt=$r['time'];
    self::c($ch,'Page load speed',$lt<2.5?'pass':($lt<5?'warn':'fail'),'Homepage loaded in '.round($lt,1).'s ('.$m['size_kb'].' KB). '.($lt<2.5?'Good.':'Slow pages lose visitors – over 50% leave after 3 seconds on mobile.'),3,'Performance');
    if($m['size_kb']>3000)self::c($ch,'Page weight','warn','Homepage HTML is very heavy ('.$m['size_kb'].' KB).',1,'Performance');
    $t=$q('//title');$title=$t->length?trim(preg_replace('/\s+/',' ',$t->item(0)->textContent)):'';$m['title']=$title;$tl=mb_strlen($title);
    self::c($ch,'SEO title tag',$title===''?'fail':($tl<20||$tl>65?'warn':'pass'),$title===''?'No page title – Google does not know what this page is about.':'"'.mb_substr($title,0,80).'" ('.$tl.' chars). '.($tl<20||$tl>65?'Ideal length is 30-60 characters with main service + city.':'Good length.'),3);
    $d=$meta('description');$m['description']=$d;$dl=mb_strlen((string)$d);
    self::c($ch,'Meta description',!$d?'fail':($dl<70||$dl>170?'warn':'pass'),!$d?'Missing meta description – Google shows random text in search results, lowering clicks.':$dl.' characters. '.($dl<70||$dl>170?'Ideal is 120-160 characters.':'Good.'),2);
    $h1=$q('//h1');$m['h1']=$h1->length?trim($h1->item(0)->textContent):'';
    self::c($ch,'H1 heading',$h1->length===1?'pass':($h1->length===0?'fail':'warn'),$h1->length===0?'No H1 heading – the main topic of the page is unclear to Google.':($h1->length>1?$h1->length.' H1 headings found – use exactly one.':'One H1: "'.mb_substr($m['h1'],0,70).'"'),2);
    $h2=$q('//h2')->length;
    self::c($ch,'Content structure (H2 sub-headings)',$h2>=2?'pass':'warn',$h2.' sub-headings found.',1);
    $vp=$q("//meta[@name='viewport']")->length>0;
    self::c($ch,'Mobile friendly (viewport)',$vp?'pass':'fail',$vp?'Mobile viewport is set.':'Not mobile-optimised – most local searches happen on phones.',3,'Technical');
    $imgs=$q('//img');$noalt=0;foreach($imgs as $i)if(trim($i->getAttribute('alt'))==='')$noalt++;
    if($imgs->length)self::c($ch,'Image alt text',$noalt===0?'pass':($noalt/$imgs->length<.3?'warn':'fail'),$noalt.' of '.$imgs->length.' images have no alt text (missed image-search traffic & accessibility).',1);
    $text=trim(preg_replace('/\s+/',' ',html_entity_decode(strip_tags(preg_replace('~<(script|style|noscript)[^>]*>.*?</\1>~is',' ',$h)))));
    $wc=str_word_count($text);$m['words']=$wc;
    self::c($ch,'Content depth',$wc>=500?'pass':($wc>=250?'warn':'fail'),'About '.$wc.' words on the homepage. '.($wc<500?'Thin content ranks poorly; Google prefers 500+ helpful words.':'Good.'),2);
    $can=$q("//link[@rel='canonical']")->length>0;
    self::c($ch,'Canonical tag',$can?'pass':'warn',$can?'Canonical URL set.':'No canonical tag – risk of duplicate-content issues.',1,'Technical');
    $schema=preg_match('~application/ld\+json~i',$h);$lb=preg_match('~"@type"\s*:\s*"(LocalBusiness|Organization|[A-Za-z]*(Store|Business|Clinic|Restaurant|Dentist|Physician|Attorney|Hotel|Agency|School|Service))"~i',$h);
    self::c($ch,'Local business schema markup',$lb?'pass':($schema?'warn':'fail'),$lb?'LocalBusiness/Organization schema found.':($schema?'Some structured data, but no LocalBusiness schema.':'No structured data – Google cannot read business details (hours, address, reviews) for rich results.'),2);
    $og=$meta('og:title')&&$meta('og:image');
    self::c($ch,'Social sharing tags (Open Graph)',$og?'pass':'warn',$og?'Open Graph tags present.':'Missing Open Graph tags – links shared on WhatsApp/Facebook show no preview image.',1,'Social');
    $ga=preg_match('~googletagmanager\.com|google-analytics\.com|gtag\(|G-[A-Z0-9]{6,}~',$h);
    self::c($ch,'Analytics / tracking',$ga?'pass':'fail',$ga?'Google Analytics/Tag Manager installed.':'No Google Analytics – the business cannot measure visitors or enquiries.',2,'Conversion');
    $tel=preg_match('~href=["\']tel:~i',$h);
    self::c($ch,'Click-to-call button',$tel?'pass':'fail',$tel?'Phone number is clickable.':'Phone number is not clickable on mobile – callers have to copy it manually.',2,'Conversion');
    $wa=preg_match('~wa\.me/|api\.whatsapp\.com|whatsapp://~i',$h);
    self::c($ch,'WhatsApp chat button',$wa?'pass':'warn',$wa?'WhatsApp link present.':'No WhatsApp chat button – a big missed channel for enquiries in India.',1,'Conversion');
    $form=$q('//form')->length>0;
    self::c($ch,'Enquiry / contact form',$form?'pass':'warn',$form?'Enquiry form found.':'No enquiry form on the homepage.',1,'Conversion');
    $map=preg_match('~google\.com/maps|maps\.google|goo\.gl/maps|maps\.app\.goo\.gl~i',$h);
    self::c($ch,'Google Maps embed / link',$map?'pass':'warn',$map?'Website links to Google Maps.':'Website does not embed or link to the Google Maps listing (weak local signal).',1,'Local SEO');
    $fav=$q("//link[contains(@rel,'icon')]")->length>0;
    self::c($ch,'Favicon',$fav?'pass':'warn',$fav?'Favicon present.':'No favicon – looks unprofessional in browser tabs and Google mobile results.',1,'Technical');
    if(preg_match_all('~(?:©|&copy;|copyright)\s*(?:\d{4}\s*[-–]\s*)?(\d{4})~i',$h,$cm)){$y=max(array_map('intval',$cm[1]));if($y&&$y<(int)date('Y')-1)self::c($ch,'Website freshness','warn','Footer copyright says '.$y.' – the site looks outdated to visitors.',1,'Trust');}
    $root=parse_url($final,PHP_URL_SCHEME).'://'.parse_url($final,PHP_URL_HOST);
    $rb=http($root.'/robots.txt',['timeout'=>10,'max'=>200000]);$hasRobots=$rb['code']===200&&stripos($rb['type'],'html')===false;
    self::c($ch,'robots.txt',$hasRobots?'pass':'warn',$hasRobots?'robots.txt found.':'No robots.txt file.',1,'Technical');
    $sm=http($root.'/sitemap.xml',['timeout'=>10,'max'=>200000]);$hasSm=($sm['code']===200&&stripos($sm['body'],'<urlset')!==false||stripos($sm['body'],'<sitemapindex')!==false)||($hasRobots&&stripos($rb['body'],'sitemap:')!==false);
    self::c($ch,'XML sitemap',$hasSm?'pass':'fail',$hasSm?'XML sitemap found.':'No XML sitemap – Google may miss pages.',2,'Technical');
    $noindex=preg_match('~<meta[^>]+name=["\']robots["\'][^>]+noindex~i',$h);
    if($noindex)self::c($ch,'Search engine indexing','fail','Page has a "noindex" tag – it is hidden from Google!',5,'Technical');
    if(setting('pagespeed')==='1'&&($ps=self::pagespeed($final))!==null){$m['mobile_speed']=$ps;self::c($ch,'Google PageSpeed (mobile)',$ps>=80?'pass':($ps>=50?'warn':'fail'),'Google PageSpeed mobile score: '.$ps.'/100.',3,'Performance');}
    $socials=[];foreach(['facebook','instagram','linkedin','youtube'] as $s)if(stripos($h,$s.'.com/')!==false)$socials[]=$s;
    self::c($ch,'Social media links',count($socials)>=2?'pass':(count($socials)?'warn':'fail'),$socials?'Links to: '.implode(', ',$socials).'.':'No social media profiles linked.',1,'Social');
    return ['score'=>self::score($ch),'checks'=>$ch,'meta'=>$m];}
  static function pagespeed($u){
    $q=['url'=>$u,'strategy'=>'mobile','category'=>'performance'];
    if($k=setting('google_api_key'))$q['key']=$k;
    $r=http_json('https://www.googleapis.com/pagespeedonline/v5/runPagespeed?'.http_build_query($q),['timeout'=>60]);
    $s=$r['data']['lighthouseResult']['categories']['performance']['score']??null;
    return $s===null?null:(int)round($s*100);}
  static function map($lead,$competitors){
    $ch=[];$raw=jd($lead['raw']);
    $rating=(float)$lead['rating'];$rev=(int)$lead['reviews'];
    $avgRev=$competitors?(int)round(array_sum(array_column($competitors,'reviews'))/count($competitors)):0;
    self::c($ch,'Google rating',$rating>=4.5?'pass':($rating>=4?'warn':'fail'),$rating?$rating.' ★ average rating. '.($rating<4.5?'Top-ranked competitors usually have 4.5★+. Customers filter by rating.':'Excellent.'):'No rating yet.',3,'Reputation');
    self::c($ch,'Number of reviews',$rev>=max(100,$avgRev)?'pass':($rev>=30?'warn':'fail'),$rev.' reviews'.($avgRev?' vs. top competitors\' average of '.$avgRev:'').'. Review count is one of the strongest Google Maps ranking factors.',3,'Reputation');
    $pos=(int)$lead['position'];
    self::c($ch,'Google Maps ranking position',$pos&&$pos<=3?'pass':($pos<=10?'warn':'fail'),'Ranked #'.$pos.' for "'.($lead['keyword']??'').'". '.($pos>3?'The top 3 (local pack) get most of the calls.':'In the top 3 – great.'),3,'Visibility');
    self::c($ch,'Website linked on profile',$lead['website']?'pass':'fail',$lead['website']?'Website is linked.':'No website on the Google profile – customers can\'t learn more and Google trusts the listing less.',3,'Profile');
    self::c($ch,'Phone number on profile',$lead['phone_source']==='maps'?'pass':'fail',$lead['phone_source']==='maps'?'Phone number listed.':'No phone number on the Google listing – direct calls are lost.',3,'Profile');
    $ph=(int)($raw['photos']??0);
    self::c($ch,'Photos',$ph>=10?'pass':($ph>=4?'warn':'fail'),($ph>=10?'10+':$ph).' photos found. Listings with many fresh photos get far more direction requests and clicks.',2,'Profile');
    $hrs=!empty($raw['hours']);
    self::c($ch,'Business hours',$hrs?'pass':'fail',$hrs?'Opening hours listed.':'No opening hours – Google may hide the listing for "open now" searches.',2,'Profile');
    $desc=!empty($raw['description']);
    self::c($ch,'Business description',$desc?'pass':'warn',$desc?'Description present.':'No visible business description / editorial summary.',1,'Profile');
    $cat=$lead['category']!=='';
    self::c($ch,'Primary category',$cat?'pass':'warn',$cat?'Category: '.$lead['category'].'.':'No clear primary category.',1,'Profile');
    if(($raw['status']??'')&&$raw['status']!=='OPERATIONAL')self::c($ch,'Business status','fail','Listing status is '.$raw['status'].'.',3,'Profile');
    return ['score'=>self::score($ch),'checks'=>$ch,'meta'=>['rating'=>$rating,'reviews'=>$rev,'photos'=>$ph,'position'=>$pos,'competitor_avg_reviews'=>$avgRev]];}}
