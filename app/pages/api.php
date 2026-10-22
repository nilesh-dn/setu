<?php
$do=$_GET['do']??'';
session_write_close();
if($do==='tick'){
  $n=0;
  if(is_post()&&Queue::due())$n=Queue::work();
  $s=DB::row("SELECT SUM(status='pending' AND run_after<=NOW()) due,SUM(status='pending' AND run_after>NOW()) later,SUM(status='running') running FROM jobs WHERE status IN ('pending','running')");
  $next=DB::val("SELECT MIN(run_after) FROM jobs WHERE status='pending' AND run_after>NOW()");
  json_out(['done'=>$n,'due'=>(int)$s['due'],'later'=>(int)$s['later'],'running'=>(int)$s['running'],'next'=>$next?max(1,strtotime($next)-time()):null]);}
json_out(['error'=>'unknown'],404);
