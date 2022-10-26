<?php
# Aplikace HledámBoha
# (c) 2022 Martin Smidek <martin@smidek.eu>
# ----------------------------------------------------------------------------------==> ch ban_zmena
# ASK
# $idc_new je buďto '*' pokud se ID plátce nemá měnit, nebo nové ID plátce
# povolené kombinace old(typ,idc)->new(typ,idc) 
#   jedar     (6,0)->(5,0)
#   nedar     (5/8,0)->(6,0)
#   spojit    (5/7/8,0/x)->(8/9,x)
#   rozpojit  (9,x)->(5/6,0) nebo (8,x)->(8,0)
# upozornění na chybu obsluhy
#   napřed rozpoj (8/9,x)->(8/9,y)
#   napřed rozpoj (8/9,x)->(5/6,0)
#   už je dar (8/9,x)->(5,0)
# možné hodnoty old (1/5/6/7/8,0),(8/9,x)
function ch_ban_zmena($idd,$typ_new,$idc_new='*') {  trace();
  $y= (object)array('err'=>'','msg'=>'ok');
  $upd= array();
  // zjištění starých hodnot 
  list($typ_old,$idc_old)= select('typ,id_clen','dar',"id_dar=$idd");
  // test zakázaných kombinací
  $ok_kombinace= 
         $idc_old==$idc_new && $idc_new && $typ_old==7 && $typ_new==9 
      || !$idc_old && in_array($typ_old,array(1,5,6,7,8)) 
      || $idc_old && in_array($typ_old,array(8,9));
  if (!$ok_kombinace) {
    $y->err= "chybná vazba ($typ_old,$idc_old) => ($typ_new,$idc_new) - je nutné opravit! Martin)";
    goto end;
  }
  // test upozornění na chybný požadavek
  if ( $idc_old && in_array($typ_old,array(8,9)) && $idc_new && in_array($typ_new,array(8,9)) ) {
    $y->err= "napřed rozpoj od již zapsaného dárce";
    goto end;
  }
  // rozbor povolených operací
  if ( $typ_old==6 && !$idc_old && $typ_new==5 && !$idc_new ) { // jen změna typu
    $upd[]= (object)array('fld'=>'typ', 'op'=>'u','val'=>$typ_new,'old'=>$typ_old);
  }
  elseif ( $typ_old==5 && !$idc_old && $typ_new==6 && !$idc_new ) { // jen změna typu
    $upd[]= (object)array('fld'=>'typ', 'op'=>'u','val'=>$typ_new,'old'=>$typ_old);
  }
  elseif ( in_array($typ_old,array(5,7,8)) && (!$idc_old || $idc_old==$idc_new) // spojit
      && in_array($typ_new,array(8,9)) && $idc_new ) { 
    $upd[]= (object)array('fld'=>'typ', 'op'=>'u','val'=>$typ_new,'old'=>$typ_old);
    $upd[]= (object)array('fld'=>'id_clen', 'op'=>'u','val'=>$idc_new,'old'=>$idc_old);
  }
  elseif ( $typ_old==9 && $idc_old && in_array($typ_new,array(5,6)) && !$idc_new ) { 
    $upd[]= (object)array('fld'=>'typ', 'op'=>'u','val'=>$typ_new,'old'=>$typ_old);
    $upd[]= (object)array('fld'=>'id_clen', 'op'=>'u','val'=>$idc_new,'old'=>$idc_old);
  }
  elseif ( $typ_old==8 && $idc_old && $typ_new==8 && !$idc_new ) { 
    $upd[]= (object)array('fld'=>'id_clen', 'op'=>'u','val'=>$idc_new,'old'=>$idc_old);
  }
  else {
    $y->err= "nepřípustný požadavek na změnu ($typ_old,$idc_old) => ($typ_new,$idc_new)";
    goto end;
  }
  // proveď změnu se zápisem do _track
  ezer_qry("UPDATE",'dar',$idd,$upd);
end:
  return $y;
}
# -------------------------------------------------------------------------------==> ch search_popis
# viz https://php.vrana.cz/vyhledani-textu-bez-diakritiky.php
function ch_search_popis($popis) { 
  $popis= utf2ascii($popis,' .');
  $popis= strtr($popis,array(
      'mgr'=>'', 'mudr'=>'', 'mvdr'=>'', 'rndr'=>'', 'ing'=>'', 'bc'=>'', 
      '_'=>'','.'=>''));
  $popis= trim($popis);
  $cond1= "'$popis' RLIKE CONCAT(ascii_prijmeni,' ',ascii_jmeno)";
  $cond2= "'$popis' RLIKE CONCAT(ascii_jmeno,' ',ascii_prijmeni)";
  $cond3= "CONCAT(ascii_prijmeni,' ',ascii_jmeno) LIKE '%$popis%'";
  $cond4= "CONCAT(ascii_jmeno,' ',ascii_prijmeni) LIKE '%$popis%'";
  $cond= "($cond1 OR $cond2 OR $cond3 OR $cond4) AND NOT (jmeno='' AND prijmeni='') 
      AND NOT (ascii_jmeno='' AND ascii_prijmeni='') ";
//                        display("ch_search_popis($popis) => $cond");
  return $cond;
}
# ----------------------------------------------------------------------------==> ch search_popis_fy
function ch_search_popis_fy($popis) { 
  $popis= strtoupper(strtr($popis,array(' '=>'',','=>'')));
  $firma= "UPPER(REPLACE(REPLACE(firma,' ',''),',',''))";
  $cond1= "'$popis' RLIKE $firma";
  $cond2= "$firma LIKE '%$popis%'";
  $cond= "(firma!='' AND ($cond1 OR $cond2)) ";
//                        display("ch_search_popis_fy($popis) => $cond");
  return $cond;
}
# ------------------------------------------------------------------------==> ch remake_ascii_fields
# zajistí korektní nastavení ascii-položek
function ch_remake_ascii_fields($given_idc=0) {
  $n= 0;
  $only_one= $given_idc ? "AND id_clen=$given_idc" : '';
  $rc= pdo_qry("SELECT id_clen,prijmeni,ascii_prijmeni,jmeno,ascii_jmeno 
    FROM clen WHERE deleted='' $only_one");
  while ($rc && (list($idc,$p,$ap,$j,$aj)=pdo_fetch_row($rc))) {
    $change= 0;
    $oap= trim(utf2ascii($p,' .'));
    $oap= str_replace('.','\.',$oap);
    if ($oap!=$ap) {
      query("UPDATE clen SET ascii_prijmeni='$oap' WHERE id_clen=$idc");
      $change++;
    }
    $oaj= trim(utf2ascii($j,' .'));
    $oaj= str_replace('.','\.',$oaj);
    if ($oaj!=$aj) {
      query("UPDATE clen SET ascii_jmeno='$oaj' WHERE id_clen=$idc");
      $change++;
    }
    if ($change) $n++;
  }
  return "Změněno $n ascii variant jmen";
}
# ===========================================================================================> BANKA
# ------------------------------------------------------------------------------- ch bank_novy_darce
# založ nového dárce
function ch_bank_novy_darce ($idd,$firma=0) {
  $ret= (object)array('err'=>'');
  // kontroly vhodnosti vytvoření
  list($popis,$typ)= select('ucet_popis,typ','dar',"id_dar=$idd");
  if ($typ!=5) { $ret->err= "lze použít jen na žluté řádky"; goto end; }
  $cond= $firma ? ch_search_popis_fy($popis) : ch_search_popis($popis);
  $idc= select('id_clen','clen',"deleted='' AND $cond LIMIT 1");
  if ($idc) { $ret->err= "kontakt tohoto jména už v databázi je"; goto end; }
  if ($firma) {
    // vytvoření návrhu firmy
    $ret->nazev= $popis;
  }
  else {
    // vytvoření návrhu osoby
    $titul= '';
    $tituly= 'Bc|Mgr|Ing|JUDr|MUDr|MVDr|MsDr|Paedr|PHDr|ThDr|ThLic';
    $m= null;
    $ok= preg_match("~(?:($tituly)\.|)\s*(\w+)[\s,]+(\w+)~iu",trim($popis),$m);
    if ($ok) {
      if ($m[1]) {
        $mtit= explode('|',$tituly);
        $i= array_search(strtolower($m[1]), array_map('strtolower', $mtit));
        $titul= $m[1] ? "$mtit[$i]." : '';
      }
      $jmeno= $m[2];
      $prijmeni= $m[3];
    }
    else {
      list($jmeno,$prijmeni)= preg_split("/[\s,]+/u",trim($popis));
    }
  //  display("$popis:$titul $jmeno $prijmeni");
    $jmeno= mb_convert_case($jmeno, MB_CASE_TITLE, 'UTF-8');
    $prijmeni= mb_convert_case($prijmeni, MB_CASE_TITLE, 'UTF-8');
    $zname= select('jmeno','_jmena',"jmeno='$jmeno'");
    if ($zname) {
      $ret->jmeno= $jmeno;
      $ret->prijmeni= $prijmeni;
    }
    else {
      $ret->jmeno= $prijmeni;
      $ret->prijmeni= $jmeno;
    }
    $ret->titul= $titul;
  }
end:
  return $ret;
}
function ch_bank_uloz_darce($idd,$titul,$jmeno,$prijmeni) {
  // vlož osobní kontakt 
  $osl= osl_insert(1,$titul,$jmeno,$prijmeni);
  $upd= array();
  $idc= ezer_qry("INSERT",'clen',0,array(
    (object)array('fld'=>'zdroj',     'op'=>'i','val'=>'VYPIS'),
    (object)array('fld'=>'osoba',     'op'=>'i','val'=>1),
    (object)array('fld'=>'titul',     'op'=>'i','val'=>$titul),
    (object)array('fld'=>'jmeno',     'op'=>'i','val'=>$jmeno),
    (object)array('fld'=>'prijmeni',  'op'=>'i','val'=>$prijmeni),
    (object)array('fld'=>'prijmeni5p','op'=>'i','val'=>$osl->prijmeni5p),
    (object)array('fld'=>'osloveni',  'op'=>'i','val'=>$osl->osloveni),
    (object)array('fld'=>'rod',       'op'=>'i','val'=>$osl->rod)
  ));
  // proveď změnu daru
  ezer_qry("UPDATE",'dar',$idd,array(
    (object)array('fld'=>'id_clen', 'op'=>'u','val'=>$idc), //,'old'=>0),
    (object)array('fld'=>'typ',     'op'=>'u','val'=>9)     //,'old'=>5)
  ));
  ch_remake_ascii_fields($idc);
}
function ch_bank_uloz_darce_fy($idd,$popis) {
  // vlož osobní kontakt 
  $upd= array();
  $idc= ezer_qry("INSERT",'clen',0,array(
    (object)array('fld'=>'zdroj',     'op'=>'i','val'=>'VYPIS'),
    (object)array('fld'=>'osoba',     'op'=>'i','val'=>0),
    (object)array('fld'=>'firma',     'op'=>'i','val'=>$popis)
  ));
  // proveď změnu daru
  ezer_qry("UPDATE",'dar',$idd,array(
    (object)array('fld'=>'id_clen', 'op'=>'u','val'=>$idc), //,'old'=>0),
    (object)array('fld'=>'typ',     'op'=>'u','val'=>9)     //,'old'=>5)
  ));
  ch_remake_ascii_fields($idc);
}
# --------------------------------------------------------------------------------- ch bank kontrola
# kotrola řad
function ch_bank_kontrola ($cis_ucet,$rok) {
  list($zkratka,$nazev,$ucet)= select('zkratka,hodnota,ikona','_cis',
      "druh='b_ucty' AND data='$cis_ucet' ");
  $html= "<div style='font-weight:bold;padding:3px;border-bottom:1px solid black'>
    Účet $zkratka: $ucet - $nazev</div>";
  // projdeme výpisy
  $rv= pdo_query("
    SELECT soubor_od,soubor_do FROM vypis 
    WHERE YEAR(datum_od)='$rok' AND nas_ucet='$cis_ucet'
    ORDER BY datum_od  ");
  while ($rv && (list($soubor_od,$soubor_do)=pdo_fetch_row($rv))) {
    $html.= "<br>$soubor_od $soubor_do";
  }
  return $html;
}
/*
# -------------------------------------------------------------------------------- ch bank_join_dary
# spáruj dary výpisu 
function ch_bank_join_dary ($idv) {
  $rv= pdo_query("SELECT id_dar FROM dar WHERE id_vypis=$idv AND typ=5 AND ucet_popis!='' ");
  while ($rv && (list($idd)=pdo_fetch_row($rv))) {
    ch_bank_join_dar($idd);
  }
}
# --------------------------------------------------------------------------------- ch bank_join_dar
# spáruj dar
function ch_bank_join_dar ($idd) {
  // podrobnosti z převodu a získání podmínky na popis
  list($castka,$datum,$popis,$typ)= 
      select('castka,castka_kdy,ucet_popis,typ','dar',"id_dar=$idd");
  if ($typ==9) goto end;
  $cond= ch_search_popis($popis);
  // hledání dárce
  list($idd2,$idc)= select('id_dar,id_clen','dar JOIN clen USING (id_clen)',
      "zpusob=2 AND typ=9 AND dar.deleted='' AND $cond AND castka=$castka AND castka_kdy='$datum' ");
  if ($idd2) {
//    display("idc=$idc, idd2= $idd2");
    query("UPDATE dar SET deleted='D x' WHERE id_dar=$idd2");
    query("UPDATE dar SET typ=9,id_clen=$idc WHERE id_dar=$idd");
  }
  else {
    $idc= select('id_clen','clen',$cond);
    if ($idc) {
//      display("idc=$idc, idd2= ---");
      query("UPDATE dar SET typ=7,id_clen=$idc WHERE id_dar=$idd");
    }
//    display("? $popis ");
  }
end:
}
# -------------------------------------------------------------------------------- ch bank_load_ucty
function ch_bank_pub($pub,&$p,&$u,&$b,$padding=true) {
  list($pu,$b)= explode('/',$pub);
  list($p,$u)= explode('-',$pu);
  if ( $padding ) {
    if ( $u ) {
      $u= str_pad($u,10,'0',STR_PAD_LEFT);
      $p= str_pad($p,6,'0',STR_PAD_LEFT);
    }
    else {
      $u= str_pad($p,10,'0',STR_PAD_LEFT);
      $p= '000000';
    }
  }
  else {
    if ( !$u ) {
      $u= $p;
      $p= '';
    }
    $u= ltrim($u,'0');
    $p= ltrim($p,'0');
  }
}
# -------------------------------------------------------------------------------- ch bank_load_ucty
# $bank_nase_banky = array ('0100',...)
# $bank_nase_ucty  = array ('0100'=> array('000000-1234567890'=>'X'),...)
# $bank_nase_nucty = array ('X'=> n) -- n je data účtu v _cis.druh=='k_ucty'
function ch_bank_load_ucty () {
  global $bank_nase_banky, $bank_nase_ucty, $bank_nase_nucty;
  if ( !isset($bank_nase_banky) ) {
    $bank_nase_ucty= array();
    $bank_nase_banky= array();
    $qry= "SELECT * FROM _cis WHERE druh='b_ucty' AND ikona!='' ";
    $res= pdo_qry($qry);
    while ( $res && $c= pdo_fetch_object($res) ) {
      bank_pub($c->ikona,$p,$u,$b);
      if ( !in_array($b,$bank_nase_banky) )
        $bank_nase_banky[]= $b;
      $bank_nase_ucty[$b]["$p-$u"]= $c->zkratka;
      $bank_nase_nucty[$c->zkratka]= $c->data;
    }
  }
}
*/
# ----------------------------------------------------------------------------------==> aby ban_load
# ASK
# načtení souboru CSV z bankovního účtu
function aby_ban_load($file) {  //trace();
  global $ezer_path_root;
  $y= (object)array('err'=>'','msg'=>'ok',idv=>0);
  // definice importovaných sloupců
  $flds_2010= array(
      "Datum"             => array(0,'d','castka_kdy'),
      "Objem"             => array(0,'c','castka'),
      "Měna"              => array(0,'m'),
      "Protiúčet"         => array(0,'u1','ucet'),
      "Název protiúčtu"   => array(0,'n','ucet_popis'),
      "Kód banky"         => array(0,'u2','ucet'),
      "VS"                => array(0,'vs','vsym'),
      "Zpráva pro příjemce" => array(0,'p0','zprava'),
      "Poznámka"            => array(0,'p1','zprava') // bereme pouze pokus se liší od názvu protiúčtu
    );
  // načti vlastní účty
  $nase_ucty= array(); // účet -> ID
  $res= pdo_qry("SELECT data,ikona FROM _cis WHERE druh='b_ucty' AND ikona!='' ");
  while ( $res && list($idu,$u_b)= pdo_fetch_row($res) ) {
    $nase_ucty[$u_b]= $idu;
  }
//  debug($nase_ucty,'$nase_ucty');
  $csv= "$ezer_path_root/banka/$file";
  $data= $prefix= array();
  $msg= aby_csv2array($csv,$data,0, 'UTF-8',';',10,$prefix); // 9 řádků před vlastními daty
//  debug($data[0],"load:$msg"); 
  debug($prefix,"prefix"); 
  // rozbor prefixu 
  // ------------- výpis
  $m= null;
  $ok= preg_match('~"Výpis č.\s*(\d+)/(\d+)\s*z účtu\s*""(\d+)/(\d+)""~',$prefix[0],$m);
  $vypis_n= $m[1];
  $vypis_rok= $m[2];
  $banka= $m[4];
  $nas_ucet= "$m[3]/$banka";
  $idu= isset($nase_ucty[$nas_ucet]) ? $nase_ucty[$nas_ucet] : 0;
//  debug($m,"ok=$ok, ucet=$nas_ucet, idu=$idu");
  if (!$idu) { $y->err= "'$nas_ucet' není mezi účty zapsanými v Nastavení"; goto end; }
  // -------------- od-do
  $m= null;
  $ok= preg_match('~"Období:\s*([\d\.]+)\s*-\s*([\d\.]+)"~',$prefix[3],$m);
  $od= sql_date($m[1],1);
  $do= sql_date($m[2],1);
//  debug($m,"ok=$ok, od=$od, do=$do");
  // -------------- počáteční stav
  $m= null;
  $ok= preg_match('~".*:\s*([\d\,]+)\s*CZK"~',$prefix[4],$m);
  $stav_od= str_replace(',','.',$m[1]);
//  // -------------- koncový stav
  $m= null;
  $ok= preg_match('~".*:\s*([\d\,]+)\s*CZK"~',$prefix[5],$m);
  $stav_do= str_replace(',','.',$m[1]);
  debug($m,"ok=$ok, stav od=$stav_od, do=$stav_do");
  // zpracování výpisu
  $flds= $flds_2010;
  $prevody= array(); // idc,
  foreach ($data as $i=>$rec) {
    // v prvním průchodu proveď kontroly a založ záznam pro výpis
    if ($i==0) {
      // ověření existence základních položek
      foreach ($rec as $fld=>$val) {
        if (isset($flds[$fld])) $flds[$fld][0]++;
      }
      foreach ($flds as $fld=>$desc) {
        if (!$desc[0]) { $y->err= "ve výpisu chybí povinné pole '$fld'"; goto end; }
      }
    }
    // vložení záznamu
    $set= ''; $castka= 0; $ucet= $popis= $pozn= $zprava= '';
    foreach ($rec as $fld=>$val) {
      list(,$fmt,$f)= $flds[$fld];
      switch ($fmt) {
        // společné
        case 'd': $datum= sql_date($val,1); 
                  $set.= ", $f='$datum'"; 
                  $nd= select('COUNT(*)','dar',"nas_ucet=$idu AND deleted='' AND castka_kdy='$datum'");
                  if ($nd) {
                    display("na řádku $i je platba s datem $datum, které již pro tento účet bylo zpracované"); 
                    $y->err= "na řádku $i je platba s datem $datum, které již pro tento účet bylo zpracované"; 
                    goto end;
                  }
                  break;
        case 'c': $castka= preg_replace(array("/\s/u","/,/u"),array('','.'),$val);
                  $set.= ", $f=$castka"; break;
        case 'm': if ($val=='CZK') break;
                  $y->err= "nekorunové platby nejsou implementovány"; goto end;
        case 'n': $popis= $val; 
                  $set.= ", $f='$val'"; break;
        // 0800
        case 'u': $ucet= $val; 
                  $set.= ", $f='$val'"; break;
        // 0600
        case 'vs': $set.= ", $f='$val'"; break;
        case 'u1': $ucet= $val; break;
        case 'u2': $ucet.= "/$val"; 
                   $set.= ", ucet='$ucet'"; break;
        case 'p0': if ($val!=$popis && $val!=trim($pozn)) $zprava= $val; break;
        case 'p1': if ($val!=$popis && $val!=trim($pozn)) $pozn.= " $val"; break; 
      }
    }
    $pozn= trim("$zprava $pozn");
    if ($pozn) $set.= ", zprava='$pozn'"; 
    // určení typu a způsobu
    $typ= $castka<=0 ? 1 : ($ucet=='160987123/0300' && $popis=='CESKA POSTA, S.P.' ? 8 : 5);
    $zpusob= $typ==8 ? 3 : 2;
    // pokus o zjištění dárce
    $idc= 0;
    if ($typ==5) {
      // nejprve podle účtu je-li
      if ($ucet)
        $idc= select('id_clen','dar',"zpusob=2 AND ucet='$ucet' ORDER BY castka_kdy DESC LIMIT 1");
      // potom podle popisu
      if (!$idc && $popis) {
        $cond= ch_search_popis($popis);
        $idc= select('id_clen','clen',"deleted='' AND $cond ORDER BY id_clen LIMIT 1");
      }
      $idc= $idc ?: 0;
      $typ= $idc ? 7 : 5;
    }
    // vložení záznamu - pokud jde o příjem
    $set.= ", id_clen=$idc, typ= $typ, zpusob=$zpusob ";
//    if ($castka>0) {
      $prevody[]= $set;
//    }
  }
  debug($prevody);
  // pokud je vše v pořádku vlož výpis
  query("INSERT INTO vypis SET soubor='$file', nas_ucet=$idu, 
      rok_vypis='$vypis_rok', n_vypis='$vypis_n', 
      datum_od='$od', datum_do='$do', stav_od='$stav_od', stav_do='$stav_do'  ");
  $y->idv= pdo_insert_id();
  // a převody
  foreach ($prevody as $set) {
    query("INSERT INTO dar SET id_vypis=$y->idv, nas_ucet=$idu $set");    
  }
end:
  if ($y->err) {
    // problém - smažeme import
    unlink($csv);
  }
  else {
    // úspěch - uklidíme
    $path= "$ezer_path_root/banka/$banka/$vypis_rok";
    if (!file_exists($path)) mkdir ($path);
    $info= pathinfo($csv);
    $ok= copy($csv,"$path/{$info['basename']}");
    if ($ok) unlink($csv);
  }
  return $y;
}
# =========================================================================================> PROJEKT
# ----------------------------------------------------------------------------------- aby donio_load
# import dat pro donio.cz => {idp,war,err}
# pokud idp=0 bude vytvořen nový projekt
# pokud idp!=0 budou dary přidány ke stávajícímu projektu
function aby_donio_load($csv,$idp,$novy) { trace();
  global $ezer_path_root;
  $res= (object)array('idp'=>0,'err'=>'','war'=>'');
  $typ= 1;
  $data= array();
  $csv_path= "$ezer_path_root/banka/donio/$csv";
  $msg= aby_csv2array($csv_path,$data,999999,'UTF-8');
//  display($msg);                                            
  if ($msg) { 
    $res->err= $msg; goto end;
  }  
  // test na donio.cz
  if (!isset($data[0]['Datum příspěvku'])) {
    $res->err= "'$csv' asi není export z donio.cz"; goto end;
  }
  // ochrana proti násobnému načtení ALE nikoliv proti změně kódování
  $md5= md5_file($csv_path);
  $duplicita= select('COUNT(*)','projekt',"typ=$typ AND FIND_IN_SET('$md5',md5)");
  if ($duplicita) {
    $res->err= "tento soubor již byl vložen"; goto end_bez_vymazu;
  }
  if ($idp) {
    $res->idp= $idp;
    query("UPDATE projekt SET md5=CONCAT(md5,',','$md5'),soubor=CONCAT(soubor,',','$csv') 
      WHERE id_projekt=$idp");
  }
  else {
    query("INSERT INTO projekt (nazev,typ,soubor,md5) VALUES ('$novy',1,'$csv','$md5') ");
    $res->idp= pdo_insert_id();
  }
  // otestování a případné vytvoření ANONYM
  $anonym= select('id_clen','clen', "prijmeni='♥ANONYM'");
  if (!$anonym) {
    $qry= "INSERT INTO clen (zdroj,osoba,prijmeni,email) VALUE ('system',1,'♥ANONYM','')";
    query($qry);
    $anonym= pdo_insert_id();
  }
  // definice polí
  $flds= array( 
      // Donio: Email	Jméno	Částka	Stav	Typ platby	Datum příspěvku	Jméno přispěvatele	Vzkaz
      'Částka'            => "castka,dn",
      'Stav'              => ",stav",          // zaplaceno | nezaplaceno
      'Datum příspěvku'   => "castka_kdy,dt",
      'Jméno přispěvatele'=> "ucet_popis",
      'Vzkaz'             => "pozn"
  );
  // rozdělíme na clen a dar
  $n_clen= $n_dar= $suma= 0;
  foreach ($data as $row) {
//                                                    debug($row);
    // atributy darů
    $d= array();
    $castka= 0;
    foreach ($flds as $fld=>$desc) {
      if ($desc=='-') continue;
      list($itm,$cnv)= explode(',',$desc);
      $val= $row[$fld];
      switch ($cnv) {
        case 'stav':
          if ($val=='nezaplaceno') continue 3; // přejdi na další záznam
          break;
        case 'dn': 
          $castka= strtr($val,array(' '=>'','Kč'=>''));
          $d[$itm]= $castka;
          break;
        case 'dt': 
          $m= null;
          if (preg_match("~^(\d+\.\d+\.\d+)~",$val,$m)) {
            $d[$itm]= sql_date($m[1],1); 
          }
          break;
        default: 
          $d[$itm]= $val; 
          break;
      }
    }
    // přičti částku
    $suma+= $castka;
    // najdi kontakt podle emailu nebo vlož nový kontakt
    $email= trim($row['Email']);
    $prijmeni= trim($row['Jméno']);
    $idc= $email ? select('id_clen','clen', "email='$email' AND deleted='' ") : $anonym;
    if (!$idc) {
      $qry= "INSERT INTO clen (zdroj,osoba,prijmeni,email) VALUE ('donio',1,'$prijmeni','$email')";
      query($qry);
      $idc= pdo_insert_id();
      $n_clen++;
    }
    // vytvoření dar
    $attr= array();
    $d['id_clen']= $idc;
    foreach ($d as $itm=>$val) {
      $attr[]= "$itm='$val'";
    }
    if (isset($d['castka']) && $d['castka']) {
      query("INSERT INTO dar SET typ=9,zpusob=5,id_projekt=$res->idp,".implode(',',$attr));
      $n_dar++;
    }
  }
  query("UPDATE projekt SET suma='$suma' WHERE id_projekt=$res->idp");
  $res->war= "Bylo vloženo $n_clen lidí a $n_dar darů";
end:
  if ($res->err) {
    // problém - smažeme neúspěšný import
    unlink($csv_path);
  }
end_bez_vymazu:
  return $res;
}
# --------------------------------------------------------------------------------- aby darujme_load
# import dat pro darujme.cz => {idp,war,err}
function aby_darujme_load($csv,$typ=2) { trace();
  global $ezer_path_root;
  $res= (object)array('idp'=>0,'err'=>'','war'=>'');
  $data= array();
  $csv_path= "$ezer_path_root/banka/darujme/$csv";
  $msg= aby_csv2array($csv_path,$data,999999,'UTF-8');
//  display($msg);                                            
  if ($msg) { 
    $res->err= $msg; goto end;
  }  
  // test na darujme.cz
  if (!isset($data[0]['Částka převedená na účet NNO (CZK)'])) {
    $res->err= "'$csv' asi není export z darujme.cz"; goto end;
  }
  // ochrana proti násobnému načtení ALE nikoliv proti změně kódování
  $md5= md5_file($csv_path);
  $duplicita= select('COUNT(*)','projekt',"typ=$typ AND FIND_IN_SET('$md5',md5)");
  if ($duplicita) {
    $res->err= "tento soubor již byl vložen"; goto end_bez_vymazu;
  }
  // otestování a případné vytvoření ANONYM
  $anonym= select('id_clen','clen', "prijmeni='♥ANONYM'");
  if (!$anonym) {
    $qry= "INSERT INTO clen (zdroj,osoba,prijmeni,email) VALUE ('system',1,'♥ANONYM','')";
    query($qry);
    $anonym= pdo_insert_id();
  }
  // definice polí
  $flds= array( 
      // Darujme: 		Obec	PSČ		Dárcovská výzva	Předčíslí-ID daru
      'Projekt' => "C,-,projekt",
      'Stav transakce'                      => "C,-,stav",
      'Částka převedená na účet NNO (CZK)'  => "D,castka,dn",
      'Datum převedení prostředků NNO'      => "D,castka_kdy,dt",
      'Chci obdržet potvrzení o daru'       => "-",
      'Jméno'       => "C,jmeno",
      'Příjmení'    => "C,prijmeni",
      'Email'       => "C,email",
      'Telefon'     => "C,telefony",
      'Ulice, č.p.' => "C,ulice",
      'Obec'        => "C,obec",
      'PSČ'         => "C,psc"
  );
  // rozdělíme na clen a dar
  $n_clen= $n_dar= 0;
  foreach ($data as $row) {
//                                                    debug($row);
    // atributy darů
    $c= $d= array();
    $castka= 0;
    $idp= 0;
    foreach ($flds as $fld=>$desc) {
      if ($desc=='-') continue;
      list($tab,$itm,$cnv)= explode(',',$desc);
      $val= $row[$fld];
      switch ($cnv) {
        case 'projekt': // najdi nebo vytvoř projekt
          if (!$val) continue 3;
          list($idp,$md5s)= select('id_projekt,md5','projekt',"typ=$typ AND nazev='$val'");
//                                  display("list($idp,$md5s)");
          if (!$idp) {
            query("INSERT INTO projekt (nazev,typ,soubor,md5) VALUES ('$val',$typ,'$csv','$md5') ");
            $idp= pdo_insert_id();
          }
          elseif (strpos($md5s,$md5)===false) { // případně doplň md5
            query("UPDATE projekt 
              SET md5=CONCAT(md5,',','$md5'),soubor=CONCAT(soubor,',','$csv')  
              WHERE id_projekt=$idp");
          }
          $res->idp= $idp;
          break;
        case 'stav':
          if ($val!='OK, převedeno') continue 3; // přejdi na další záznam
          break;
        case 'dn': 
          $castka= strtr($val,array(' '=>'','Kč'=>''));
          if ($tab=='C') $c[$itm]= $castka; else $d[$itm]= $castka;
          break;
        case 'dt': 
          $m= null;
          if (preg_match("~^(\d+\.\d+\.\d+)~",$val,$m)) {
            $den= sql_date($m[1],1); 
            if ($tab=='C') $c[$itm]= $den; else $d[$itm]= $den;
          }
          break;
        default: 
          if ($tab=='C') $c[$itm]= $val; else $d[$itm]= $val;
          break;
      }
    }
//                                                    debug($c,'clen');
//                                                    debug($d,'dar');
    // najdi kontakt podle emailu nebo vlož nový kontakt
    $email= trim($row['Email']);
    $idc= $email ? select('id_clen','clen', "email='$email' AND deleted='' ") : $anonym;
    if (!$idc) {
      $jmeno= trim($row['Jméno']);
      $qry= "INSERT INTO clen (zdroj,osoba,jmeno,email) VALUE ('darujme',1,'$jmeno','$email')";
      query($qry);
      $idc= pdo_insert_id();
      $n_clen++;
    }
    // doplnění clen
    $attr= array();
    foreach ($c as $itm=>$val) {
      $attr[]= "$itm='$val'";
    }
//                                                    debug($attr,'attr');
    if (count($attr)) {
      query("UPDATE clen SET ".implode(',',$attr)." WHERE id_clen=$idc");
    }
    // vytvoření dar
    $attr= array();
    $d['id_clen']= $idc;
    foreach ($d as $itm=>$val) {
      $attr[]= "$itm='$val'";
    }
    if (isset($d['castka']) && $d['castka']) {
      query("INSERT INTO dar SET typ=9,zpusob=5,id_projekt=$idp,".implode(',',$attr));
      $n_dar++;
    }
    // doplnění projektu
    query("UPDATE projekt SET suma=suma+'$castka' WHERE id_projekt=$idp");
  }
  $res->war= "Bylo vloženo $n_clen lidí a $n_dar darů";
end:
  if ($res->err) {
    // problém - smažeme neúspěšný import
    unlink($csv_path);
  }
end_bez_vymazu:
  return $res;
}
