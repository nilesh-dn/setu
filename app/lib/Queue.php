<?php
final class Queue{
  static function push($type,$ref,$payload=[],$at=null){
    return DB::insert('jobs',['type'=>$type,'ref_id'=>(int)$ref,'payload'=>je($payload),'run_after'=>$at?:date('Y-m-d H:i:s')]);}
  static function pending(){return (int)DB::val("SELECT COUNT(*) FROM jobs WHERE status IN ('pending','running')");}
  static function due(){return (int)DB::val("SELECT COUNT(*) FROM jobs WHERE status='pending' AND run_after<=NOW()");}
  static function work($budget=null){
    $budget=$budget?:(int)setting('queue_budget','20');
    @set_time_limit(max(120,$budget+200));
    @ignore_user_abort(true);
    $t0=microtime(true);$n=0;
    DB::q("UPDATE jobs SET status='pending',locked_at=NULL WHERE status='running' AND locked_at<NOW()-INTERVAL 10 MINUTE");
    while(microtime(true)-$t0<$budget){
      $j=DB::row("SELECT * FROM jobs WHERE status='pending' AND run_after<=NOW() ORDER BY FIELD(type,'search','enrich','audit','report','contact'),id LIMIT 1");
      if(!$j)break;
      if(!DB::q("UPDATE jobs SET status='running',locked_at=NOW(),attempts=attempts+1 WHERE id=? AND status='pending'",[$j['id']])->rowCount())continue;
      try{self::handle($j);DB::q("UPDATE jobs SET status='done',error=NULL WHERE id=? AND status='running'",[$j['id']]);}
      catch(Throwable $e){self::fail($j,$e);}
      $n++;}
    return $n;}
  private static function fail($j,$e){
    $msg=$e->getMessage();
    logmsg("Job {$j['id']} {$j['type']}#{$j['ref_id']}: $msg");
    $att=(int)$j['attempts']+1;
    if($att<3&&$j['type']!=='search'){DB::q("UPDATE jobs SET status='pending',locked_at=NULL,error=?,run_after=NOW()+INTERVAL ? MINUTE WHERE id=?",[$msg,2*$att,$j['id']]);return;}
    DB::q("UPDATE jobs SET status='failed',error=? WHERE id=?",[$msg,$j['id']]);
    if($j['type']==='search')DB::update('campaigns',['status'=>'error','error'=>$msg],'id=?',[$j['ref_id']]);
    elseif($j['type']!=='enrich'){stage($j['ref_id'],'failed');event($j['ref_id'],'error',ucfirst($j['type']).' failed: '.$msg);}}
  private static function handle($j){
    $p=jd($j['payload']);$id=(int)$j['ref_id'];
    switch($j['type']){
      case 'search':self::search($id,$p);break;
      case 'enrich':self::enrich($id);break;
      case 'audit':self::audit($id,$p);break;
      case 'report':self::report($id,$p);break;
      case 'contact':Outreach::contact($id,$p);break;}}
  private static function search($cid,$p){
    $c=DB::row('SELECT * FROM campaigns WHERE id=?',[$cid]);
    if(!$c||$c['status']==='stopped')return;
    $q=trim($c['keyword'].($c['location']?' in '.$c['location']:''));
    $res=Maps::search($q,$p['next']??null,$c['source']);
    $have=(int)$c['found'];
    foreach($res['items'] as $it){
      if($have>=$c['limit_n'])break;
      if(DB::val('SELECT id FROM leads WHERE campaign_id=? AND place_id=?',[$cid,$it['place_id']]))continue;
      $have++;
      $lid=DB::insert('leads',['campaign_id'=>$cid,'position'=>$have,'place_id'=>$it['place_id'],'name'=>mb_substr($it['name'],0,250),'category'=>mb_substr($it['category'],0,180),'address'=>mb_substr($it['address'],0,490),'city'=>mb_substr($it['city']?:$c['location'],0,110),'phone'=>$it['phone'],'phone_source'=>$it['phone']?'maps':'','website'=>mb_substr($it['website'],0,490),'rating'=>$it['rating'],'reviews'=>(int)$it['reviews'],'maps_url'=>mb_substr($it['maps_url'],0,490),'lat'=>$it['lat'],'lng'=>$it['lng'],'raw'=>je($it['raw']),'last_activity'=>date('Y-m-d H:i:s')]);
      event($lid,'found','Found on Google Maps at position #'.$have.' for "'.$q.'"','maps');
      if($it['website'])self::push('enrich',$lid);}
    DB::update('campaigns',['found'=>$have],'id=?',[$cid]);
    if($have<$c['limit_n']&&!empty($res['next'])&&$res['items'])self::push('search',$cid,['next'=>$res['next']],date('Y-m-d H:i:s',time()+($c['source']==='places'?2:0)));
    else DB::update('campaigns',['status'=>'ready'],'id=?',[$cid]);}
  private static function enrich($lid){
    $l=DB::row('SELECT * FROM leads WHERE id=?',[$lid]);
    if(!$l||!$l['website'])return;
    $c=Scraper::contacts($l['website']);
    $u=['socials'=>je($c['socials']+($c['whatsapp']?['whatsapp'=>$c['whatsapp']]:[]))];
    $found=[];
    if(!$l['phone']&&$c['phones']){$u['phone']=mb_substr($c['phones'][0],0,40);$u['phone_source']='website';$found[]='phone '.$u['phone'];}
    if(!$l['email']&&$c['emails']){$u['email']=mb_substr($c['emails'][0],0,190);$found[]='email '.$u['email'];}
    DB::update('leads',$u,'id=?',[$lid]);
    event($lid,'enriched',$found?'Found on website: '.implode(', ',$found):'Website scanned – no extra contact details found','web');}
  private static function audit($lid,$p){
    $l=DB::row('SELECT l.*,c.keyword FROM leads l JOIN campaigns c ON c.id=l.campaign_id WHERE l.id=?',[$lid]);
    if(!$l)return;
    stage($lid,'auditing');
    if($l['website']&&!$l['email'])self::enrich($lid);
    $a=jd($l['audit']);
    if($l['website']){$a['website']=Auditor::website($l['website']);}
    $comp=DB::all('SELECT reviews,rating FROM leads WHERE campaign_id=? AND id<>? ORDER BY position LIMIT 3',[$l['campaign_id'],$lid]);
    $a['map']=Auditor::map($l,$comp);
    DB::update('leads',['audit'=>je($a),'web_score'=>$l['website']?$a['website']['score']:null,'map_score'=>$a['map']['score']],'id=?',[$lid]);
    event($lid,'audited',($l['website']?'Website score '.$a['website']['score'].'/100 · ':'No website · ').'Google Maps score '.$a['map']['score'].'/100');
    self::push('report',$lid,$p);}
  private static function report($lid,$p){
    stage($lid,'reporting');
    Report::generate($lid,$p);
    stage($lid,'report_ready','PDF report generated');
    if(!empty($p['auto']))Outreach::schedule($lid);}}
