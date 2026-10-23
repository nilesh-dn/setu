<?php
// Vector charts (SVG) for the PDF report
final class Charts{
  static function col($s){return $s>=75?'#2EAD6B':($s>=50?'#F2A41C':'#E8554E');}
  private static function svg($w,$h,$body){return Illus::uri("<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 $w $h' width='$w' height='$h'>$body</svg>");}
  private static function x($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_XML1,'UTF-8');}
  // Ring gauge with the score in the middle
  static function gauge($score,$label,$track='#EEF0F4'){
    $s=max(0,min(100,(int)$score));$c=self::col($s);$r=70;$cx=90;$cy=90;
    $pt=function($f)use($cx,$cy,$r){$a=$f*2*M_PI-M_PI/2;return round($cx+$r*cos($a),2).' '.round($cy+$r*sin($a),2);};
    // Draw as two arcs (no large-arc flag) – renders reliably in PDF
    $d="M$cx ".($cy-$r).($s>50?" A$r $r 0 0 1 ".$pt(.5).' A'.$r.' '.$r.' 0 0 1 '.$pt(min(.999,$s/100)):" A$r $r 0 0 1 ".$pt($s/100));
    $arc=$s>=100?"<circle cx='$cx' cy='$cy' r='$r' fill='none' stroke='$c' stroke-width='18'/>":($s>0?"<path d='$d' fill='none' stroke='$c' stroke-width='18' stroke-linecap='round'/>":'');
    return self::svg(180,180,"<circle cx='$cx' cy='$cy' r='$r' fill='none' stroke='$track' stroke-width='18'/>$arc<text x='$cx' y='".($cy+12)."' font-size='40' font-weight='bold' text-anchor='middle' fill='#1F2937'>$s</text><text x='$cx' y='".($cy+34)."' font-size='12' text-anchor='middle' fill='#6B7280'>".self::x($label)."</text>");}
  // Horizontal score bars per category
  static function bars($items,$w=520){
    $h=count($items)*34+10;$o='';$y=8;
    foreach($items as $label=>$v){$v=(int)$v;$bw=round(($w-210)*$v/100);$c=self::col($v);
      $o.="<text x='0' y='".($y+16)."' font-size='13' fill='#374151'>".self::x($label)."</text><rect x='150' y='$y' width='".($w-210)."' height='20' rx='10' fill='#EEF0F4'/>".($bw>0?"<rect x='150' y='$y' width='".max(20,$bw)."' height='20' rx='10' fill='$c'/>":'')."<text x='".($w-50)."' y='".($y+15)."' font-size='13' font-weight='bold' fill='#111827'>$v</text>";$y+=34;}
    return self::svg($w,$h,$o);}
  // Compare a value across businesses (you highlighted)
  static function compare($rows,$unit='',$w=520,$hl='#F2A41C',$base='#94A3B8'){
    $max=max(1,max(array_map(fn($r)=>(float)$r[1],$rows)));$h=count($rows)*32+10;$o='';$y=6;
    foreach($rows as [$label,$v,$me]){$bw=round(($w-250)*(float)$v/$max);$c=$me?$hl:$base;
      $o.="<text x='0' y='".($y+15)."' font-size='12' fill='".($me?'#111827':'#4B5563')."' font-weight='".($me?'bold':'normal')."'>".self::x(mb_strimwidth($label,0,28,'…'))."</text>".($bw>0?"<rect x='190' y='$y' width='".max(6,$bw)."' height='20' rx='5' fill='$c'/>":'')."<text x='".(196+$bw)."' y='".($y+15)."' font-size='12' font-weight='bold' fill='#111827'>".self::x($v.$unit)."</text>";$y+=32;}
    return self::svg($w,$h,$o);}
  // Investment vs potential return columns
  static function roi($cols,$w=520,$h=230){
    $max=max(1,max(array_map(fn($c)=>(float)$c[1],$cols)));$n=count($cols);$bw=min(110,($w-40)/$n-40);$o="<path d='M20 ".($h-40)."H".($w-10)."' stroke='#CBD5E1' stroke-width='2'/>";
    foreach($cols as $i=>[$label,$v,$c,$txt]){$x=30+$i*(($w-40)/$n)+(($w-40)/$n-$bw)/2;$bh=round(($h-80)*(float)$v/$max);
      $o.="<rect x='$x' y='".($h-40-$bh)."' width='$bw' height='$bh' rx='8' fill='$c'/><text x='".($x+$bw/2)."' y='".($h-48-$bh)."' font-size='14' font-weight='bold' text-anchor='middle' fill='#111827'>".self::x($txt)."</text><text x='".($x+$bw/2)."' y='".($h-18)."' font-size='12' text-anchor='middle' fill='#4B5563'>".self::x($label)."</text>";}
    return self::svg($w,$h,$o);}
  // Pass / warn / fail stacked strip
  static function strip($pass,$warn,$fail,$w=520){
    $t=max(1,$pass+$warn+$fail);$a=round($w*$pass/$t);$b=round($w*$warn/$t);$c=$w-$a-$b;
    return self::svg($w,46,"<rect x='0' y='0' width='$w' height='18' rx='9' fill='#EEF0F4'/>".($a?"<rect x='0' y='0' width='$a' height='18' fill='#2EAD6B'/>":'').($b?"<rect x='$a' y='0' width='$b' height='18' fill='#F2A41C'/>":'').($c?"<rect x='".($a+$b)."' y='0' width='$c' height='18' fill='#E8554E'/>":'')."<text x='0' y='40' font-size='12' fill='#15803D'>✔ $pass passed</text><text x='".round($w/2)."' y='40' font-size='12' text-anchor='middle' fill='#B45309'>! $warn need work</text><text x='$w' y='40' font-size='12' text-anchor='end' fill='#B91C1C'>✘ $fail problems</text>");}}
