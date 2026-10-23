<?php
// CRM leads: manual entry, captured leads (forms/ads/webhooks), statuses, custom fields
final class CRM{
  const CORE=['name','contact_name','email','phone','alt_phone','website','address','city','category','service','deal_value','message','tags','follow_up_at'];
  static function fields(){static $f=null;return $f??=DB::all('SELECT * FROM fields ORDER BY sort,id');}
  // Map loose incoming keys (from forms, ads, webhooks) to Setu fields
  static function normalize($in){
    $map=['name'=>['company','company_name','business','business_name','organisation','organization','firm'],'contact_name'=>['name','full_name','fullname','your_name','first_name','contact','contact_name','customer_name','person'],'email'=>['email','email_address','e-mail','your_email','mail'],'phone'=>['phone','phone_number','mobile','mobile_number','contact_number','whatsapp','tel','telephone','your_phone','number'],'city'=>['city','location','town'],'address'=>['address','street_address','full_address'],'website'=>['website','url','site','web'],'service'=>['service','services','interested_in','product','requirement_type','enquiry_for','subject'],'message'=>['message','your_message','comments','comment','query','enquiry','details','description','notes'],'deal_value'=>['budget','deal_value','amount','value']];
    $out=[];$rest=[];
    foreach($in as $k=>$v){
      if(is_array($v))$v=implode(', ',array_map('strval',array_filter($v,'is_scalar')));
      $v=trim((string)$v);if($v==='')continue;
      $nk=strtolower(preg_replace('/[^a-z0-9]+/i','_',trim((string)$k)));
      $hit=false;
      foreach($map as $f=>$alts)if(in_array($nk,$alts,true)&&!isset($out[$f])){$out[$f]=$v;$hit=true;break;}
      if(!$hit&&$nk==='last_name'&&isset($out['contact_name'])){$out['contact_name'].=' '.$v;$hit=true;}
      if(!$hit)$rest[$nk]=$v;}
    $custom=[];
    foreach(self::fields() as $f)if(isset($rest[$f['fkey']])){$custom[$f['fkey']]=$rest[$f['fkey']];unset($rest[$f['fkey']]);}
    unset($rest['_csrf'],$rest['k'],$rest['redirect'],$rest['g_recaptcha_response']);
    return [$out,$custom,$rest];}
  // Create (or merge into a recent duplicate) a CRM lead. Returns lead id.
  static function create($d,$source='manual',$detail='',$custom=[],$extra=[]){
    $d=array_intersect_key($d,array_flip(self::CORE));
    $d['name']=trim($d['name']??'')?:trim($d['contact_name']??'')?:'Unnamed lead';
    if(isset($d['deal_value']))$d['deal_value']=(float)preg_replace('/[^\d.]/','',$d['deal_value'])?:null;
    if(isset($d['follow_up_at'])&&!$d['follow_up_at'])unset($d['follow_up_at']);
    $email=strtolower(trim($d['email']??''));$ph=$d['phone']??'';
    $dup=null;
    if($email||$ph){
      $tail=$ph?substr(preg_replace('/\D/','',$ph),-10):'';
      $dup=DB::row("SELECT id,custom,message FROM leads WHERE is_lead=1 AND created_at>NOW()-INTERVAL 30 DAY AND ((?<>'' AND LOWER(email)=?) OR (?<>'' AND RIGHT(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'+',''),10)=?)) ORDER BY id DESC LIMIT 1",[$email,$email,$tail,$tail]);}
    if($extra)$custom['_extra']=$extra;
    if($dup){
      $u=array_filter($d,fn($v)=>$v!==''&&$v!==null);unset($u['name']);
      $c=jd($dup['custom']);$c=$custom+$c;
      if(!empty($d['message']))$u['message']=trim(($dup['message']?$dup['message']."\n---\n":'').$d['message']);
      DB::update('leads',$u+['custom'=>je($c)],'id=?',[$dup['id']]);
      event($dup['id'],'enriched','New enquiry received again via '.(lead_sources()[$source][0]??$source).($detail?' ('.$detail.')':''),$source);
      return $dup['id'];}
    $id=DB::insert('leads',$d+['campaign_id'=>0,'place_id'=>'crm-'.token(8),'is_lead'=>1,'source'=>$source,'source_detail'=>mb_substr($detail,0,180),'status'=>'new','stage'=>'new','custom'=>$custom?je($custom):null,'token'=>token(16),'lead_at'=>date('Y-m-d H:i:s'),'last_activity'=>date('Y-m-d H:i:s'),'phone_source'=>$ph?'form':'']);
    event($id,'found','New lead from '.(lead_sources()[$source][0]??$source).($detail?' · '.$detail:''),$source);
    Automation::fire('lead_created',$id);
    return $id;}
  static function setStatus($id,$st,$note=''){
    $l=DB::row('SELECT status,stage FROM leads WHERE id=?',[$id]);
    if(!$l||$l['status']===$st)return;
    $u=['status'=>$st,'is_lead'=>1];
    if($st==='won')$u['stage']='won';
    if($st==='lost'&&$l['stage']!=='won')$u['stage']='lost';
    DB::update('leads',$u,'id=?',[$id]);
    event($id,'stage','Status: '.(crm_statuses()[$l['status']][0]??$l['status']).' → '.(crm_statuses()[$st][0]??$st).($note?' · '.$note:''));
    Automation::fire('status_changed',$id,['status'=>$st]);}
  static function addTag($id,$tag){
    $tag=trim(str_replace(',','',$tag));if($tag==='')return;
    $t=array_filter(array_map('trim',explode(',',(string)DB::val('SELECT tags FROM leads WHERE id=?',[$id]))));
    if(in_array($tag,$t,true))return;
    $t[]=$tag;DB::update('leads',['tags'=>mb_substr(implode(', ',$t),0,490)],'id=?',[$id]);
    event($id,'note','Tag added: '.$tag);
    Automation::fire('tag_added',$id,['tag'=>$tag]);}
  // Form control for a custom field
  static function input($f,$v=''){
    $n='cf['.e($f['fkey']).']';$req=$f['required']?' required':'';
    switch($f['type']){
      case 'textarea':return '<textarea name="'.$n.'" rows="2"'.$req.'>'.e($v).'</textarea>';
      case 'select':$o='<select name="'.$n.'"'.$req.'><option value="">—</option>';foreach(array_filter(array_map('trim',explode("\n",(string)$f['options']))) as $x)$o.='<option '.($x===$v?'selected':'').'>'.e($x).'</option>';return $o.'</select>';
      case 'checkbox':return '<span class="chk"><input type="hidden" name="'.$n.'" value=""><input type="checkbox" name="'.$n.'" value="Yes" '.($v?'checked':'').'> Yes</span>';
      default:$t=['number'=>'number','date'=>'date','email'=>'email','phone'=>'tel','url'=>'url'][$f['type']]??'text';return '<input type="'.$t.'" name="'.$n.'" value="'.e($v).'"'.$req.'>';}}
  static function saveCustom($id,$in){
    $c=jd(DB::val('SELECT custom FROM leads WHERE id=?',[$id]));
    foreach(self::fields() as $f)if(array_key_exists($f['fkey'],$in))$c[$f['fkey']]=mb_substr(trim((string)$in[$f['fkey']]),0,2000);
    DB::update('leads',['custom'=>je($c)],'id=?',[$id]);}
  // CSV import with header row; returns count
  static function import($file){
    $fh=fopen($file,'r');if(!$fh)throw new Exception('Cannot read file');
    $head=fgetcsv($fh);if(!$head)throw new Exception('Empty CSV');
    $head[0]=preg_replace('/^\xEF\xBB\xBF/','',$head[0]);
    $n=0;
    while(($row=fgetcsv($fh))!==false){
      if(count(array_filter($row,'strlen'))===0)continue;
      $in=[];foreach($head as $i=>$h)$in[$h]=$row[$i]??'';
      [$d,$c,$rest]=self::normalize($in);
      foreach($in as $h=>$v){$k=strtolower(preg_replace('/[^a-z0-9]+/i','_',$h));if(in_array($k,self::CORE,true)&&!isset($d[$k])&&$v!=='')$d[$k]=$v;}
      if($rest)$d['message']=trim(($d['message']??'')."\n".implode("\n",array_map(fn($k,$v)=>"$k: $v",array_keys($rest),$rest)));
      self::create($d,'import','CSV '.date('d M Y'),$c);$n++;}
    fclose($fh);return $n;}}
