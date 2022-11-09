<?php
# Aplikace HledámBoha
# (c) 2022 Martin Smidek <martin@smidek.eu>
/*
# ------------------------------------------------------------------------------------- aby truncate
# inicializace db
function aby_truncate() { trace();
  global $abs_root;
  query("TRUNCATE TABLE dar");
  query("TRUNCATE TABLE clen");
  query("TRUNCATE TABLE ukol");
  query("TRUNCATE TABLE vztah");
  query("TRUNCATE TABLE projekt");
  query("TRUNCATE TABLE vypis");
  // vymaž banka
  foreach (array("$abs_root/banka/darujme","$abs_root/banka/donio","$abs_root/banka/2100") as $dir) {
    if (($handle= opendir($dir))) {
      while (false !== ($file= readdir($handle))) {
        if (is_file("$dir/$file")) {
          @unlink("$dir/$file");
        }
      }
      closedir($handle);
    }
  }  
  return "tabulky clen, vztah, dar, ukol, projekt, vypis a soubory banka/* jsou vyprázdněny";
}
# --------------------------------------------------------------------------------------- aby import
# primární import dat
function aby_import($par) { trace();
  global $ezer_path_root;
  $csv= "$ezer_path_root/doc/import/{$par->file}.csv";
  $TEST= isset($par->test);
  $data= array();
  $msg= aby_csv2array($csv,$data,$par->max?:999999,'UTF-8');
//  display($msg);                                              
  debug($data,$csv);
  // zrušíme staré záznamy
  // definice polí
  $flds= array( 
      // Donio: Email	Jméno	Částka	Stav	Typ platby	Datum příspěvku	Jméno přispěvatele	Vzkaz
      // kontakty darci_2021.csv: Jméno, x, Email, Adresa, Poznámka, Posláno ...
      // clen
      'Email'   => "C,email",
      'Jméno'   => "C,*,jp",
      // dar
      'Částka'            => "D,castka,dn",
      'zpusob'            => "D,-",
      'Datum příspěvku'   => "D,castka_kdy,d",
      'Jméno přispěvatele'=> "D,-",
      'Vzkaz'             => "D,pozn",
  );
  // rozdělíme na clen a dar
  $n_clen= $n_dar= 0;
  foreach ($data as $row) {
    $poznamka= ''; // složená z poznamka s prefixem barva a poznamka2
//                                                    debug($row);
    // najdi kontakt: fyzické podle jmeno+prijmeni (osoba=1), právnické podle firma (osoba=0)
    // nebo vlož nvý kontakt
    $osoba= $row['osoba'];
    $jmeno= $row['jmeno'];
    $prijmeni= $row['prijmeni'];
    $firma= trim($row['firma']);
    if (!$prijmeni && !$firma) continue;
    $firma_info= trim($row['firma_info']);
    $idc= 0;
    if ($row['soukr']=='soukr') {
      // u lékařů ap. proveď firma=titul+firma a info= celé jméno
      $firma= "{$row['titul']} $firma";
      $firma_info.= " {$row['firma']}";
    }
    if ($row['zdroj']=='firmy2') {
      $poznamka= $row['barva'];
      $firma_info= trim("{$row['titul']} {$row['jmeno']} {$row['prijmeni']} {$row['titul_za']}");
    }
    if ($row['zdroj']=='dobr' || $row['zdroj']=='kruh') {
      $p2= trim($row['poznamka2']);
      $p3= trim($row['poznamka3']);
      $poznamka.= ($p2 && $p3) ? "POVOLÁNÍ $p2, POMOC: $p3" 
          : ($p2 ? "POVOLÁNÍ $p2" : ($p3 ? "POMOC $p3" : ''));
    }
    if ($row['zdroj']=='darci') {
      $idc= select('id_clen','clen', $osoba||!$firma
          ? "prijmeni='$prijmeni' AND jmeno='$jmeno'"
  //        : "firma='$firma' AND prijmeni='$prijmeni' AND jmeno='$jmeno'"
          : "firma='$firma'  "
          );
    }
    if (!$idc) {
      if ($osoba||!$firma) {
        $JM= trim(utf2ascii($jmeno,' .'));
        $PR= trim(utf2ascii($prijmeni,' .'));
        $qry= "INSERT INTO clen (osoba,firma,jmeno,prijmeni,ascii_jmeno,ascii_prijmeni) 
          VALUE ($osoba,'$firma','$jmeno','$prijmeni','$JM','$PR')";
      }
      else {
        $FI= trim(utf2ascii($firma_info,' .'));
        $qry= "INSERT INTO clen (osoba,firma,firma_info,ascii_firma_info) 
          VALUE ($osoba,'$firma','$firma_info','$FI')";
      }
      query($qry);
      $idc= pdo_insert_id();
      $n_clen++;
    }
    // atributy
    $c= $d= array();
    $c['email']= $c['poznamka']= '';
    $d['zpusob']= 0;
    foreach ($flds as $fld=>$desc) {
      if (substr($fld,0,1)=='-') continue;
      list($tab,$cnv)= explode(',',$desc);
      $val= $row[$fld];
      switch($cnv) {
        case 'adr2': 
          $m= null;
          if (!$val) {
            break;
          }
          elseif (preg_match("~^\*~",$val)) {
            display("UPRAVIT:$val");
          }
          elseif (preg_match("~^(.*),([\s\d]+)(.*)$~",$val,$m)) {
            $c['ulice 2']= $m[1];
            $c['psc 2']= str_replace(' ','',$m[2]);
            $c['obec 2']= $m[3];
          }
          break;
        case 'adr': 
          $m= null;
          if (!$val) {
            break;
          }
          elseif (preg_match("~^\*~",$val)) {
            display("UPRAVIT:$val");
          }
          elseif (preg_match("~^(.*),([\s\d]+)(.*)$~",$val,$m)) {
            $c['ulice']= $m[1];
            $c['psc']= str_replace(' ','',$m[2]);
            $c['obec']= $m[3]=='P'?'Polička':$m[3];
          }
          else {
            $c['ulice']= $val;
          }
          break;
        case 'rc': 
          $m= null;
          $val= str_replace('*','',$val);
          if (preg_match("~^\d\d\d\d$~",$val)) {
            $c['narozeni_rok']= $val;
            display("$prijmeni: narozeni_rok:$val");
          }
          elseif (preg_match("~^\d+\.\d+\.\d+$~",$val)) {
            $c['narozeni']= sql_date($val,1);
            display("$prijmeni: narozeni:$val");
          }
          elseif (preg_match("~^t:\s*(\d+)$~",$val,$m)) {
            $c['telefony']= $m[1];
            display("$prijmeni: telefon:$val");
          }
          break;
        case 'ic': 
          $c[$fld]= str_replace(' ','',$val);
          break;
        case 'dn': 
          $d[$fld]= str_replace(' ','',$val);
          break;
        case 'd': 
          if (preg_match("~^\*?\d+\.\d+\.\d+$~",$val)) {
            if ($tab=='C') $c[$fld]= sql_date($val,1); 
            elseif ($tab=='D') $d[$fld]= sql_date($val,1); 
          }
          break;
        case 'dv': 
          if (preg_match("~věcný~",$val)) {
            $d['zpusob']= 4; 
          }
          elseif (preg_match("~^\d+\.\d+\.\d+$~",$val)) {
            $d[$fld]= sql_date($val,1); 
          }
          break;
        case 'z': 
          $d['zpusob']= $val=='na účet' ? 2 : ($val=='v hotovosti' ? 1 : 0); 
          break;
        case 'ep': 
          if (strchr($val,'@')) $c['email']= $val; elseif ($tab=='D') $c['poznamka']= $val; 
          break;
        case 'p': 
          $c['poznamka']= ($poznamka ? "$poznamka, " : '').$val; 
          break;
        default: 
          if ($tab=='C') $c[$fld]= $val; else $d[$fld]= $val; 
          break;
      }
    }
    // přidání atributů do clen
    $attr= array();
    foreach ($c as $fld=>$val) {
      if ($fld=='rodcis') {
        $attr[]= "$fld=IF($fld='0000-00-00',$val'";
      }
      elseif ($val)
        $attr[]= "$fld='$val'";
    }
    if (count($attr))
      query("UPDATE clen SET ".implode(',',$attr)." WHERE id_clen=$idc");
    // vygenerování oslovení pro fyzické osoby
    if ($osoba==1)
      osl_update($idc);
    // vytvoření dar
    $attr= array();
    $d['id_clen']= $idc;
    foreach ($d as $fld=>$val) {
      $attr[]= "$fld='$val'";
    }
    if (isset($d['castka']) && $d['castka']) {
      query("INSERT INTO dar SET typ=9,".implode(',',$attr));
      $n_dar++;
    }
  }
  return "Bylo vloženo $n_clen lidí a $n_dar darů";
}
 */
# ------------------------------------------------------------------------------------ aby csv2array
# načtení CSV-souboru do asociativního pole, při chybě navrací chybovou zprávu
# obsahuje speciální kód pro soubory kódované UTF-16LE
# ověřuje kódování souboru 
function aby_csv2array($fpath,&$data,$max=0,$encoding='UTF-8',$delimiter='',$nprefix=0,&$prefix=null) { trace();
  $encode= function($s,$encoding) {
    if ($encoding!='UTF-8' && $encoding!='UTF-16LE') {
      if ($encoding=='CP1250')
        $s= win2utf($s,1);
      else 
        $s= mb_convert_encoding($s, "UTF-8", $encoding);
    }
    return $s;      
  };
  $msg= '';
  $utf8= false; 
//  display($fpath); fopen("C:/Ezer/beans/hb/doc/import/kontakty darci_2021.csv",'r');
  $f= $encoding=='UTF-16LE' ? fopen_utf8($fpath, "r") : @fopen($fpath, "r");
  if ( !$f ) { $msg.= "soubor $fpath nelze otevřít"; goto end; }
  // načteme první řádek s korekcí na BOM
  $s= fgets($f, 5000);
  if (mb_check_encoding($s,'UTF-8') || mb_check_encoding($s,'UTF-16LE')) {
    $bom= pack('H*','EFBBBF');
    $s= preg_replace("/^$bom/", '', $s);
  }
  else {
    $encoding= 'CP1250';
  }
  $s= $encode($s,$encoding);
  if ($prefix!==null) $prefix[]= $s;
  // přeskočíme případný prefix
  for ($i=1; $i<$nprefix; $i++) {
    $s= fgets($f, 5000);
    $s= $encode($s,$encoding);
    $prefix[]= $s;
  }
  // diskuse oddělovače
  $del= $delimiter ?: strstr($s,';') ? ';' : (strstr($s,',') ? ',' : '');
  if ( !$del ) { $msg.= "v souboru $fpath jsou nestandardní oddělovače"; goto end; }
  $head= str_getcsv($s,$del);
  $n= 0;
  while (($s= fgets($f, 5000)) !== false) {
    $s= $encode($s,$encoding);
//    display("$n:$s");
    $d= str_getcsv($s,$del);
    foreach ($d as $i=>$val) {
      $data[$n][$head[$i]]= $val;
    }
    $n++;
    if ($max && $n>=$max) break;
  }
end:
  return $msg;
}
# http://www.practicalweb.co.uk/blog/2008/05/18/reading-a-unicode-excel-file-in-php/
function fopen_utf8($filename){ trace();
  $encoding= '';
  $handle= @fopen($filename, 'r');
  $bom= fread($handle, 2);
  rewind($handle);
  if($bom === chr(0xff).chr(0xfe)  || $bom === chr(0xfe).chr(0xff)){
    // UTF16 Byte Order Mark present
    $encoding= 'UTF-16';
  } 
  else {
    $file_sample= fread($handle, 1000) + 'e'; //read first 1000 bytes
    // + e is a workaround for mb_string bug
    rewind($handle);
    $encoding= mb_detect_encoding($file_sample , 'UTF-8, UTF-7, ASCII, EUC-JP,SJIS, eucJP-win, SJIS-win, JIS, ISO-2022-JP');
  }
  if ($encoding){
    stream_filter_append($handle, 'convert.iconv.'.$encoding.'/UTF-8');
  }
  return $handle;
} 
/*
# ----------------------------------------------------------------------------------------- git make
# provede git par.cmd>.git.log a zobrazí jej
# fetch pro lokální tj. vývojový server nepovolujeme
function git_make($par) {
  global $abs_root, $ezer_version, $ezer_path;
  $bean= preg_match('/bean/',$_SERVER['SERVER_NAME'])?1:0;
//  display("ezer$ezer_version, abs_root=$abs_root, bean=$bean");
  if ($ezer_version!='3.2') { fce_error("POZOR není aktivní jádro 3.2 ale $ezer_version"); }
  $cmd= $par->cmd;
  $folder= $par->folder;
  $lines= array();
  $msg= "";
  // nastav složku pro Git
  if ( $folder=='ezer') 
    chdir($ezer_path);
  elseif ( $folder=='skins') 
    chdir("./skins");
  elseif ( $folder=='.') 
    chdir(".");
  else
    fce_error('chybná aktuální složka');
  debug($par,"git_make(...), ezer_version=$ezer_version, bean=$bean, ezer_path=$ezer_path, cwd=".getcwd());
  // proveď příkaz Git
  $state= 0;
  $branch= $folder=='ezer' ? ($ezer_version=='3.1' ? 'master' : 'ezer3.2') : 'master';
  switch ($cmd) {
    case 'log':
    case 'status':
      $exec= "git $cmd";
      display($exec);
      exec($exec,$lines,$state);
      $msg.= "$state:$exec\n";
      break;
    case 'pull':
      $exec= "git pull origin $branch";
      display($exec);
      exec($exec,$lines,$state);
      $msg.= "$state:$exec\n";
      break;
    case 'fetch':
      if ( $bean) 
        $msg= "na vývojových serverech (*.bean) příkaz fetch není povolen ";
      else {
        $exec= "git pull origin $branch";
        display($exec);
        exec($exec,$lines,$state);
        $msg.= "$state:$exec\n";
        $exec= "git reset --hard origin/$branch";
        display($exec);
        exec($exec,$lines,$state);
        $msg.= "$state:$exec\n";
      }
      break;
  }
  // případně se vrať na abs-root
  if ( $folder=='ezer'||$folder=='skins') 
    chdir($abs_root);
  // zformátuj výstup
  $msg= nl2br(htmlentities($msg));
  $msg= "<i>Synology: musí být spuštěný Git Server (po aktualizaci se vypíná)</i><hr>$msg";
  $msg.= $lines ? '<hr>'.implode('<br>',$lines) : '';
  return $msg;
}
# -------------------------------------------------------------------------------------------------- sys_errata
// function sys_errata() {
  $html= '';
  // 1
  $n= $k= 0;
  $sro_as= 's\\.[ ]*r\\.[ ]*o\\.|a\\.[ ]*s\\.|spol\\.[ ]*s[ ]*r\\.o.';
  $qry= "SELECT id_clen, osoba, firma, jmeno, prijmeni FROM clen
         WHERE prijmeni REGEXP '$sro_as'";
  $res= pdo_qry($qry);
  while ( $res && ($u= pdo_fetch_object($res)) ) {
    $prijmeni= pdo_real_escape_string($u->prijmeni);
    $firma= pdo_real_escape_string($u->firma);
    $qryu= "UPDATE clen SET osoba=0,firma='$prijmeni',prijmeni='$firma'
            WHERE id_clen={$u->id_clen}";
    $resu= pdo_qry($qryu);
    $k+= pdo_affected_rows();
    $n++;
  }
  $html.= "<br>$k změn typu firma=prijmeni, prijmeni=firma  ($n řádků)";
  // 2
  $n= $k= 0;
  $qry= "SELECT id_clen, osoba, firma, jmeno, prijmeni FROM clen
         WHERE jmeno REGEXP '$sro_as'";
  $res= pdo_qry($qry);
  while ( $res && ($u= pdo_fetch_object($res)) ) {
    $x= pdo_real_escape_string(str_replace('  ',' ',$u->prijmeni.' '.$u->jmeno));
    $firma= pdo_real_escape_string($u->firma);
    $qryu= "UPDATE clen SET osoba=0,firma='$x',prijmeni='$firma',jmeno=''
            WHERE id_clen={$u->id_clen}";
    $resu= pdo_qry($qryu);
    $k+= pdo_affected_rows();
    $n++;
  }
  $html.= "<br>$k změn typu firma=prijmeni+jmeno, prijmeni=firma  ($n řádků)";
  return $html;
}
# -------------------------------------------------------------------------------------------------- ezer_get_temp_dir
# (nepoužitá) funkce definující pro balík PDF pracovní složku
function ezer_get_temp_dir() {
  global $ezer_path_root;
  return "$ezer_path_root/tmp";
}
 * 
 */
# ---------------------------------------------------------------------------------------------- psc
// doplnění mezery do PSČ
function psc ($psc,$user2sql=0) {
  if ( $user2sql )                            // převeď uživatelskou podobu na sql tvar
    $text= str_replace(' ','',$psc);
  else {                                      // převeď sql tvar na uživatelskou podobu (default)
    $psc= str_replace(' ','',$psc);
    $text= substr($psc,0,3).' '.substr($psc,3);
  }
  return $text;
}
# ----------------------------------------------------------------------------------- osl update_all
# ASK
# vygeneruje rod,osloveni,prijmeni5p do tabulky CLEN kde to lze a není zakázáno
function osl_update_all() {
  $res= pdo_qry("
      SELECT id_clen,osoba,titul,jmeno,prijmeni FROM clen 
      WHERE deleted='' AND osoba=1 AND jmeno!='' AND prijmeni!=''
        AND vyjimka!=1 AND osloveni=0 AND prijmeni5p='' ");
  while ($res && (list($idc,$osoba,$titul,$jmeno,$prijmeni)= pdo_fetch_row($res)) ) {
    $x= osl_insert($osoba,$titul,$jmeno,$prijmeni);
    if ($x->osloveni) {
      query("UPDATE clen SET rod=$x->rod,osloveni='$x->osloveni',prijmeni5p='$x->prijmeni5p'
           WHERE id_clen=$idc");
    }
  }
}
# --------------------------------------------------------------------------------------- osl insert
# ASK
# vygeneruje rod,osloveni,prijmeni5p do tabulky CLEN
# pro $explain vysvětlí postup
function osl_insert($osoba,$titul,$jmeno,$prijmeni,$explain='') { trace();
  $result= (object)array();
  $qry= "SELECT jmeno,sex FROM _jmena WHERE jmeno='$jmeno' ORDER BY cetnost DESC LIMIT 1";
  $res= pdo_qry($qry);
  $sex= 0;
  if ( $res && pdo_num_rows($res) ) {
    $s= pdo_fetch_object($res);
    $sex= $s->sex;
  }
  $rod= $typ= $ano= null;
  osl_kontakt($rod,$typ,$ano,$osoba,$titul,$jmeno,$prijmeni,$sex);
  $result->prijmeni5p= $prijmeni ? osl_prijmeni5p($titul,$prijmeni,$rod,$ano) : '';
  $result->osloveni= osl_osloveni($rod,$typ);
  $result->rod= $rod=='m' ? 1 : ( $rod=='f' ? 2 : 0);
//                                         debug($result,"osl_insert($osoba,$titul,$jmeno,$prijmeni)");
  return 
    $explain? "rod=$rod podle $jmeno<br>5.pád=$result->prijmeni5p<br>oslovení=$result->osloveni"
            : $result;
}
/*
# -------------------------------------------------------------------------------------------------- osl_kontakt_new
# ASK
# vygeneruje rod,osloveni,prijmeni5p do tabulky OSLOVENI
function osl_kontakt_new ($op,$ids='',$limit=25000) { trace();
  $msg= '';
  switch ( $op ) {
  case 'start':                                 // smazání verze
    $qry= "TRUNCATE osloveni";
    $res= pdo_qry($qry);
    $msg= "všechna nová oslovení byla vymazána";
    break;
  case 'cont':                                  // opakovaný výpočet po smazání
    $qry= "SELECT max(id_clen) as konec FROM osloveni ";
    $res= pdo_qry($qry);
    $konec= ($res && ($o= pdo_fetch_object($res)) && $o->konec) ? $o->konec : 0;
//                                                 display("konec=$konec");
    $qry= "SELECT id_clen,osoba,c.jmeno,prijmeni,titul,rod,n.sex,anomalie,osloveni,prijmeni5p,vyjimka
           FROM clen AS c LEFT JOIN _jmena AS n ON c.jmeno=n.jmeno
           WHERE id_clen>$konec AND vyjimka!=801  and left(c.deleted,1)!='D'
           and umrti=0 AND neposilat=0
           GROUP BY id_clen
           ORDER BY id_clen LIMIT $limit";
    $res= pdo_qry($qry);
    $n= 0;
    while ( $res && ($x= pdo_fetch_object($res)) ) {
      $n++;
      osl_kontakt($rod,$typ,$ano,$x->osoba,$x->titul,$x->jmeno,$x->prijmeni,$x->sex);
      $prijmeni5p= $x->prijmeni ? osl_prijmeni5p($x->titul,$x->prijmeni,$rod,$ano) : '';
//                                                 display("{$x->prijmeni} -> $prijmeni5p");
      $prijmeni5p= pdo_real_escape_string($prijmeni5p);
      $osloveni= osl_osloveni($rod,$typ);
      $r= $rod=='m' ? 1 : ( $rod=='f' ? 2 : 0);
      $qry1= "INSERT INTO osloveni (id_clen,_rod,_osloveni,_prijmeni5p,_anomalie) VALUE ";
      $qry1.= "($x->id_clen,$r,'$osloveni','$prijmeni5p','$ano')";
      $res1= pdo_qry($qry1);
    }
    $msg= "bylo vygenerováno $n oslovení";
    break;
  case 'replace':                               // náhrada vybraných hodnot ve CLEN
    $n= 0;
    $qry= "SELECT * FROM osloveni WHERE FIND_IN_SET(id_clen,'$ids')";
    $res= pdo_qry($qry);
    while ( $res && ($o= pdo_fetch_object($res)) ) {
      $n++;
      $prijmeni5p= pdo_real_escape_string($o->_prijmeni5p);
      $qr1= "UPDATE clen SET rod={$o->_rod},osloveni='{$o->_osloveni}',prijmeni5p='$prijmeni5p'
             WHERE id_clen={$o->id_clen} ";
      $re1= pdo_qry($qr1);
    }
    $msg= "bylo opraveno $n oslovení";
    break;
  case 'problem':                               // označení jako problematické oslovení
    $n= 0;
    $qry= "UPDATE clen SET vyjimka=802 WHERE FIND_IN_SET(id_clen,'$ids')";
    $res= pdo_qry($qry);
    $msg= pdo_affected_rows()." oslovení bylo označeno jako problematické";
    break;
  case 'update':                                // přepočet vybraných v OSLOVENI (po změně algoritmu)
    $n= 0;
    $qry= "SELECT id_clen,osoba,titul,c.jmeno,prijmeni,n.sex
           FROM clen AS c LEFT JOIN _jmena AS n ON c.jmeno=n.jmeno WHERE FIND_IN_SET(id_clen,'$ids')";
    $res= pdo_qry($qry);
    while ( $res && ($x= pdo_fetch_object($res)) ) {
      $n++;
      osl_kontakt($rod,$typ,$ano,$x->osoba,$x->titul,$x->jmeno,$x->prijmeni,$x->sex);
      $prijmeni5p= osl_prijmeni5p($x->titul,$x->prijmeni,$rod,$ano);
      $prijmeni5p= pdo_real_escape_string($prijmeni5p);
      $osloveni= osl_osloveni($rod,$typ);
      $r= $rod=='m' ? 1 : ( $rod=='f' ? 2 : 0);
      $qry1= "REPLACE osloveni (id_clen,_rod,_osloveni,_prijmeni5p,_anomalie) VALUE ";
      $qry1.= "($x->id_clen,$r,'$osloveni','$prijmeni5p','$ano')";
      $res1= pdo_qry($qry1);
    }
    $msg= "byl opraven návrh $n oslovení";
    break;
  case 'ova':                                   // oprava vybraných koncovek -ova na -ová
    $qry= "UPDATE clen SET prijmeni=concat(left(trim(prijmeni),CHAR_LENGTH(trim(prijmeni))-1),'á')
           WHERE right(trim(prijmeni),3)='ova' AND FIND_IN_SET(id_clen,'$ids')";
    $res= pdo_qry($qry);
    $msg= "bylo opraveno ".pdo_affected_rows()." ova na ová, ";
    $msg.= osl_kontakt_new ('update',$ids);
    break;
  case 'rodina':                                // přepis Rodina ze jmena do titulu
    $qry= "UPDATE clen SET titul='Rodina',jmeno='' WHERE jmeno='Rodina'";
    $res= pdo_qry($qry);
    $msg= "bylo opraveno ".pdo_affected_rows()." kontaktů";
    break;
  }
  return $msg;
}
 * */
# ------------------------------------------------------------------------------------- osl oslovení
// generování oslovení
function osl_osloveni ($rod,$typ) {
  $oslo= 0;
  switch ( $typ ) {
  case 'p':  $oslo= 3; break;
  case 's':  $oslo= 4; break;
  case 'ss': $oslo= 5; break;
  case 'l':  $oslo= $rod=='f' ? 2 : ( $rod=='m' ? 1 : 0 ); break;
  case 'll': $oslo= 6; break;
  }
  return $oslo;
}
# ----------------------------------------------------------------------------------- osl prijmeni5p
// generování 5. pádu z $prijmeni,$rod
function osl_prijmeni5p ($titul,$prijmeni,$rod,&$ano) {  
  $y= '';
  // odříznutí přílepků za jménem (po mezeře nebo čárce)
  $p= trim($prijmeni);
  $ic= strpos($p,',');
  $is= strpos($p,' ');
  if ( $ic || $is ) {
    $i= min($ic?$ic:9999,$is?$is:9999);
    $p= substr($p,0,$i);
  }
  // vlastní algoritmus
  $len= mb_strlen($p,'UTF-8');
  $p1= mb_substr($p,0,-1,'UTF-8'); $p_1= mb_substr($p,-1,1,'UTF-8');
  $p2= mb_substr($p,0,-2,'UTF-8'); $p_2= mb_substr($p,-2,2,'UTF-8');
  $p3= mb_substr($p,0,-3,'UTF-8'); $p_3= mb_substr($p,-3,3,'UTF-8');
  // specifické případy
  if ( trim($titul)=='Rodina' && $p_3=='ova' ) $y= $p3.'ovi';
  else {
    // obecné případy
    switch ( $rod ) {
    case 'm':
      if ( mb_strpos(' eěíýyoůú',$p_1,0,'UTF-8') ) $y= $p;
      // změny
      else if ( mb_strpos(' a',$p_1,0,'UTF-8') ) $y= $p1.'o';
      else if ( mb_strpos(' ek',$p_2,0,'UTF-8') ) $y= $p2.'ku';
      else if ( mb_strpos(' el',$p_2,0,'UTF-8') ) $y= $p2.'le';
      else if ( mb_strpos(' ec',$p_2,0,'UTF-8') ) $y= $p2.'če';
      // přidání
      else if ( mb_strpos(' bdflmnprtv',$p_1,0,'UTF-8') ) $y= $p.'e';
      else if ( mb_strpos(' ghjk',$p_1,0,'UTF-8') ) $y= $p.'u';
      else if ( mb_strpos(' cčsšřzž',$p_1,0,'UTF-8') ) $y= $p.'i';
      else if ( $p_1=='ň' ) $y= $p1.'ni';
      break;
    case 'f':
      if ( $p_3=='ova' || $p_1=='á' || $p_1=='ů' || $p_1=='í' ) $y= $p;
      break;
    case 'mf':
      if ( $p_3=='ovi' ) $y= $p;
      break;
    }
  }
  if ( $y) $ano= '';
//                                                 display("osl_prijmeni5p($p,$rod)=$y ($len:$p1,$p_1,$p2,$p_2,$p3,$p_3:$p)");
  return $y;
}
# -------------------------------------------------------------------------------------- osl kontakt
# rozeznání kategorie člena - kvůli oslovení (vstupem db hodnoty $osoba,$titul,$jmeno,$prijmeni,$sex)
# rod: ?|m|f|mm|ff|mf
# typ: l|ll|s|ss|p
# ano: [o] [f] [r] [a]
#   // o - chybí právnická/fyzická => ručně
    // f - právnická osoba má křestní jméno => fyzická osoba
    // r - rod křestního jména a tvaru příjmení se liší => ručně
    // a - ženské křestní jméno a koncovka -ova => -ová

function osl_kontakt (&$rod,&$typ,&$ano,$osoba,$titul,$jmeno,$prijmeni,$sex) {
  $osoba= $osoba==1 ? 'f' : ( $osoba==0 ? 'p' : '?');
  $sex= $sex==1 ? 'm' : ( $sex==2 ? 'f' : '?');
  $rod= $typ= '?';
  if ( !strcasecmp(mb_substr($titul,0,2,'UTF-8'),'P.') || stristr($titul,'Mons.')
    || $prijmeni=='FU' && strstr($jmeno,"P.") ) {
    $rod= 'm'; $typ= 'p';
  }
  else if ( !strcasecmp(mb_substr($titul,0,2,'UTF-8'),'s.')
       || !strcasecmp(mb_substr($jmeno,0,2,'UTF-8'),'s.')  ) {
    $rod= 'f'; $typ= 's';
  }
  else if ( stristr($titul,"rodina") || stristr($titul,"manželé")
    || mb_substr($prijmeni,-3,3,'UTF-8')=='ovi' || strstr($jmeno,' a ') ) {
    $rod= 'mf'; $typ= 'll';
  }
  else if ( stristr($prijmeni,"Sestry") ) {
    $rod= 'ff'; $typ= 'ss';
  }
  else if ( $osoba=='f' && $prijmeni ) {
    $typ= 'l';
    $p_1= mb_substr($prijmeni,-1,1,'UTF-8');
    if ( $sex!='?' ) {
      $rod= $sex; $typ= 'l';
    }
    if ( mb_strstr(' áůí',$p_1,false,'UTF-8') ) {
      $rod= 'f';
    }
    else {
      $rod= 'm';
    }
  }
  // anomálie adres
  $ano= '';
  // o - chybí právnická/fyzická => ručně
  if ( $osoba=='?' ) $ano.= 'o';
  // f - právnická osoba má křestní jméno => fyzická osoba
  if ( $osoba=='p' && $sex!='?' ) $ano.= 'f';
  // r - rod křestního jména a tvaru příjmení se liší => ručně
  if ( $prijmeni && $osoba=='f' && strstr(' mf',$rod) && $rod!=$sex
    && ($sex!='f' || mb_substr($prijmeni,-3,3,'UTF-8')!='ova') ) {
    $ano.= 'r'; if ( $sex!='?' ) $rod= $sex;
  }
  // a - ženské křestní jméno a koncovka -ova => -ová
  if ( $sex=='f' && mb_substr($prijmeni,-3,3,'UTF-8')=='ova' ) {
    $ano.= 'a';
  }
//                                                 display("osl_kontakt ($rod,$typ,$ano,$osoba,$titul,$jmeno,$prijmeni,$sex)");
}
/*
# -------------------------------------------------------------------------------------------------- osl_gen_oprava
// opravy anomálií a informace o jejich počtu
function osl_gen_oprava ($typ) {
  global $row, $suma;
  switch ( $typ ) {
  case '?':             // zjistí počet anomálií
    $qry= "SELECT anomalie,count(*) as c FROM clen WHERE length(anomalie)>0  GROUP BY anomalie";
    $res= pdo_qry($qry);
    while ( $res && ($row= pdo_fetch_assoc($res)) ) {
      $txt.= "{$row['anomalie']}: {$row['c']}x<br>";
    }
    break;
  case 'f':             // kontakty označené jak anomálie 'f' jsou upraveny na fyzické osoby
    $qry= "UPDATE clen SET osoba=100 WHERE LOCATE('f',anomalie)>0 ";
    $res= pdo_qry($qry);
    $num= pdo_affected_rows();
    $txt= "$num kontaktů bylo změněno jako kontakty na fyzické osoby";
    break;
  case 'a':             // kontakty označené jak anomálie 'a' změní koncovku -ova na -ová
    $qry= "UPDATE clen SET prijmeni=concat(LEFT(prijmeni,CHAR_LENGTH(prijmeni)-1),'á')
           WHERE LOCATE('a',anomalie)>0 ";
    $res= pdo_qry($qry);
    $num= pdo_affected_rows();
    $txt= "$num kontaktů bylo změněno: substituce -ova na -ová u žen";
    break;
  }
  return $txt;
}
*/