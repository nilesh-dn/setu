<?php
final class WhatsApp{
  static function provider(){return setting('wa_provider','manual');}
  static function link($phone,$text){return 'https://wa.me/'.phone_norm($phone).'?text='.rawurlencode($text);}
  // $files = [ ['url'=>..,'name'=>..], ... ]; returns message id or 'manual'
  static function send($phone,$text,$files,$vars){
    $to=phone_norm($phone);
    if(strlen($to)<8)throw new Exception('Invalid WhatsApp number');
    $p=self::provider();
    if($p==='cloud')return self::cloud($to,$text,$files,$vars);
    if($p==='generic')return self::generic($to,$text,$files);
    return 'manual';}
  private static function cloud($to,$text,$files,$vars){
    $id=setting('wa_phone_id');$tk=setting('wa_access_token');
    if(!$id||!$tk)throw new Exception('WhatsApp Cloud API not configured');
    $u='https://graph.facebook.com/'.setting('wa_api_version','v21.0').'/'.$id.'/messages';
    $h=['Authorization: Bearer '.$tk];
    $first=null;
    if($tpl=setting('wa_template')){
      $params=array_map(fn($x)=>['type'=>'text','text'=>tpl(trim($x),$vars)],array_filter(explode('|',setting('wa_template_params','{{business_name}}')),'strlen'));
      $comp=[];
      if($files)$comp[]=['type'=>'header','parameters'=>[['type'=>'document','document'=>['link'=>$files[0]['url'],'filename'=>$files[0]['name']]]]];
      if($params)$comp[]=['type'=>'body','parameters'=>array_values($params)];
      $r=http_json($u,['headers'=>$h,'json'=>['messaging_product'=>'whatsapp','to'=>$to,'type'=>'template','template'=>['name'=>$tpl,'language'=>['code'=>setting('wa_template_lang','en')],'components'=>$comp]]]);
      if($r['code']!==200)throw new Exception('WhatsApp: '.($r['data']['error']['message']??$r['body']));
      $first=$r['data']['messages'][0]['id']??'';
      array_shift($files);}
    else{
      $r=http_json($u,['headers'=>$h,'json'=>['messaging_product'=>'whatsapp','to'=>$to,'type'=>'text','text'=>['body'=>$text,'preview_url'=>true]]]);
      if($r['code']!==200)throw new Exception('WhatsApp: '.($r['data']['error']['message']??$r['body']));
      $first=$r['data']['messages'][0]['id']??'';}
    foreach($files as $f)http_json($u,['headers'=>$h,'json'=>['messaging_product'=>'whatsapp','to'=>$to,'type'=>'document','document'=>['link'=>$f['url'],'filename'=>$f['name']]]]);
    return $first;}
  private static function generic($to,$text,$files){
    $url=setting('wa_api_url');if(!$url)throw new Exception('WhatsApp API URL not configured');
    $hdr=[];foreach(jd(setting('wa_api_headers')) as $k=>$v)$hdr[]=$k.': '.$v;
    $tplb=setting('wa_api_body');
    $esc=fn($s)=>substr(json_encode((string)$s,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),1,-1);
    $ids=[];
    $list=$files?:[['url'=>'','name'=>'']];
    foreach($list as $i=>$f){
      $vars=['phone'=>$to,'message'=>$esc($i===0?$text:''),'file_url'=>$esc($f['url']),'file_name'=>$esc($f['name']),'token'=>setting('wa_api_token')];
      $body=tpl($tplb,$vars);
      $u=tpl($url,['phone'=>$to,'token'=>urlencode(setting('wa_api_token'))]);
      $r=http($u,['method'=>setting('wa_api_method','POST'),'body'=>$body,'headers'=>$hdr,'timeout'=>40]);
      if($r['code']<200||$r['code']>=300)throw new Exception('WhatsApp API HTTP '.$r['code'].': '.substr($r['body'],0,200));
      $ids[]=substr($r['body'],0,60);}
    return 'api:'.md5(implode('|',$ids));}}
