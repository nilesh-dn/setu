<?php
final class Maps{
  // Returns ['items'=>[...normalized], 'next'=>token|null]
  static function search($query,$next=null,$source='places'){
    return $source==='serpapi'?self::serp($query,$next):self::places($query,$next);}
  static function places($q,$next){
    $key=setting('google_api_key');
    if(!$key)throw new Exception('Google Places API key missing. Add it in Settings → Data Source.');
    $body=['textQuery'=>$q,'pageSize'=>20,'languageCode'=>setting('maps_language','en'),'regionCode'=>setting('maps_region','in')];
    if($next)$body['pageToken']=$next;
    $mask='places.id,places.displayName,places.formattedAddress,places.addressComponents,places.nationalPhoneNumber,places.internationalPhoneNumber,places.websiteUri,places.rating,places.userRatingCount,places.googleMapsUri,places.primaryTypeDisplayName,places.types,places.regularOpeningHours.weekdayDescriptions,places.businessStatus,places.location,places.photos.name,places.editorialSummary,nextPageToken';
    $r=http_json('https://places.googleapis.com/v1/places:searchText',['json'=>$body,'headers'=>['X-Goog-Api-Key: '.$key,'X-Goog-FieldMask: '.$mask],'timeout'=>40]);
    if($r['code']!==200)throw new Exception('Google Places error '.$r['code'].': '.($r['data']['error']['message']??$r['error']?:substr($r['body'],0,300)).($r['code']===403?' – Check in Google Cloud: (1) "Places API (New)" is enabled, (2) the key\'s API restrictions include "Places API (New)", (3) application restriction is "None" or your server IP (not websites/referrers), (4) billing is enabled.':''));
    $out=[];
    foreach($r['data']['places']??[] as $p){
      $city='';
      foreach($p['addressComponents']??[] as $c)if(in_array('locality',$c['types']??[],true)){$city=$c['longText'];break;}
      $out[]=['place_id'=>$p['id'],'name'=>$p['displayName']['text']??'','category'=>$p['primaryTypeDisplayName']['text']??str_replace('_',' ',$p['types'][0]??''),'address'=>$p['formattedAddress']??'','city'=>$city,'phone'=>$p['internationalPhoneNumber']??$p['nationalPhoneNumber']??'','website'=>$p['websiteUri']??'','rating'=>$p['rating']??null,'reviews'=>$p['userRatingCount']??0,'maps_url'=>$p['googleMapsUri']??'','lat'=>$p['location']['latitude']??null,'lng'=>$p['location']['longitude']??null,'raw'=>['photos'=>count($p['photos']??[]),'hours'=>$p['regularOpeningHours']['weekdayDescriptions']??[],'status'=>$p['businessStatus']??'','description'=>$p['editorialSummary']['text']??'','types'=>$p['types']??[]]];}
    return ['items'=>$out,'next'=>$r['data']['nextPageToken']??null];}
  static function serp($q,$next){
    $key=setting('serpapi_key');
    if(!$key)throw new Exception('SerpAPI key missing. Add it in Settings → Data Source.');
    $start=(int)$next;
    $r=http_json('https://serpapi.com/search.json?'.http_build_query(['engine'=>'google_maps','type'=>'search','q'=>$q,'hl'=>setting('maps_language','en'),'gl'=>setting('maps_region','in'),'start'=>$start,'api_key'=>$key]),['timeout'=>60]);
    if($r['code']!==200||!empty($r['data']['error'])){
      if(!empty($r['data']['error'])&&stripos($r['data']['error'],"hasn't returned any results")!==false)return ['items'=>[],'next'=>null];
      throw new Exception('SerpAPI error: '.($r['data']['error']??$r['error']?:$r['code']));}
    $out=[];
    foreach($r['data']['local_results']??[] as $p){
      $addr=$p['address']??'';
      $parts=array_map('trim',explode(',',$addr));
      $out[]=['place_id'=>$p['place_id']??md5(($p['title']??'').$addr),'name'=>$p['title']??'','category'=>$p['type']??'','address'=>$addr,'city'=>count($parts)>=2?preg_replace('/\s*\d{5,6}$/','',$parts[count($parts)-2]):'','phone'=>$p['phone']??'','website'=>$p['website']??'','rating'=>$p['rating']??null,'reviews'=>$p['reviews']??0,'maps_url'=>isset($p['place_id'])?'https://www.google.com/maps/place/?q=place_id:'.$p['place_id']:'','lat'=>$p['gps_coordinates']['latitude']??null,'lng'=>$p['gps_coordinates']['longitude']??null,'raw'=>['photos'=>(int)($p['photos_count']??0),'hours'=>$p['operating_hours']??[],'status'=>!empty($p['permanently_closed'])?'CLOSED_PERMANENTLY':'OPERATIONAL','description'=>$p['description']??'','types'=>$p['types']??[]]];}
    $more=count($out)>=20&&$start<100;
    return ['items'=>$out,'next'=>$more?(string)($start+20):null];}}
