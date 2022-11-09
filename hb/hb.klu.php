<?php 
# Aplikace HledámBoha
# (c) 2022 Martin Smidek <martin@smidek.eu>
/** *************************************************************************************==> CLENOVE */
# ---------------------------------------------------------------------------------------- klub spoj
# spojí kopii s originálem a potom kopii vymaže
# přepíše odkazy z kopie na originál
function klub_spoj($id_copy,$id_orig) {
  global $USER;
  user_test();
  $ret= (object)array('err'=>'');
  $now= date("Y-m-d H:i:s");
  // dar: id_clen
  $dar=   select("GROUP_CONCAT(id_dar)",  "dar",  "id_clen=$id_copy");
  query("UPDATE dar SET id_clen=$id_orig WHERE id_clen=$id_copy");
  // vztah: id_left + id_right
  $vztah_L= select("GROUP_CONCAT(id_vztah)","vztah","id_left=$id_copy");
  query("UPDATE vztah SET id_left=$id_orig WHERE id_left=$id_copy");
  $vztah_R= select("GROUP_CONCAT(id_vztah)","vztah","id_right=$id_copy");
  query("UPDATE vztah SET id_right=$id_orig WHERE id_right=$id_copy");
  // ukol: id_clen
  $ukol= select("GROUP_CONCAT(id_ukol)","ukol","id_clen=$id_copy");
  query("UPDATE ukol SET id_clen=$id_orig WHERE id_clen=$id_copy");
  // mail: id_clen
  $mail= select("GROUP_CONCAT(id_mail)","mail","id_clen=$id_copy");
  query("UPDATE mail SET id_clen=$id_orig WHERE id_clen=$id_copy");
  // zápis o ztotožnění osob do _track jako op=d (duplicita)
  $info= "dar:$dar;vztah/L:$vztah_L;vztah/R:$vztah_R;ukol:$ukol;mail:$mail";
  // smazání kopie a zápis do _track
  query("UPDATE clen SET deleted='D clen=$id_orig' WHERE id_clen=$id_copy");
  $user= $USER->abbr;
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','clen',$id_orig,'clen','d','$info',$id_copy)");
  // zápis o smazání kopie do _track jako op=x (eXtract)
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','clen',$id_copy,'','x','smazaná kopie',$id_orig)");
end:
  return $ret;
}
# --------------------------------------------------------------------------------------- klub vyber
# pro cmd='options' sestaví podmínky výběru kontaktů 
# pro cmd='cond' vrací SQL vybrané podmínky pro daný klíč
function klub_vyber($cmd,$key=0) {
  $conds= array(); // [key:{nazev,cond},...]
  $conds[1]= (object)array(nazev=>'všichni',cond=>" 1");
//  $rk= pdo_query("SELECT data,hodnota FROM _cis WHERE druh='kategorie' ORDER BY zkratka ");
//  while ($rk && (list($data,$nazev)= pdo_fetch_row($rk))) {
//    $conds[$data+10]= (object)array(nazev=>"kategorie - $nazev",cond=>" FIND_IN_SET('$data',kategorie)");
//  }
  $conds[90]= (object)array(nazev=>'podle kategorie',cond=>" /*k*/ FIND_IN_SET('\$kategorie',kategorie)");
  $conds[91]= (object)array(nazev=>'podle vztahů',
      cond=>" /*v*/ (vl.vztah='\$vztah' AND vl.deleted='' OR vr.vztah='\$vztah' AND vr.deleted='')");
  $conds[100]= (object)array(nazev=>'podezřelé adresy osob',cond=>" (jmeno REGEXP '\\\\\\\\s|\\\\\\\\.' OR prijmeni REGEXP '\\\\\\\\s|\\\\\\\\.')");
  $conds[101]= (object)array(nazev=>'změny tohoto měsíce',cond=>" month(c.zmena_kdy)=month(now()) and year(c.zmena_kdy)=year(now()) ");
  $conds[102]= (object)array(nazev=>'změny kým ...',cond=>" c.zmena_kdo=\$user");
  $conds[103]= (object)array(nazev=>'změny dne ...',cond=>" left(c.zmena_kdy,10)='\$datum'");
  $conds[104]= (object)array(nazev=>'změny dne ... kým ...',cond=>" c.zmena_kdo=\$user and left(c.zmena_kdy,10)='\$datum'");
  switch($cmd) {
    case 'options':
      $selects= $del= '';
      foreach ($conds as $key=>$desc) {
        $css= $key>=100 ? ":nasedly" : '';
        $selects.= "$del{$desc->nazev}:$key$css"; $del= ',';
      }
      return $selects;
    case 'cond':
      $desc= $conds[$key];
      return $desc->cond;
  }
}
# ----------------------------------------------------------------------------------- klub firma_ico
# najde údaje o firmě podle zadaného IČO
function klub_firma_ico($ico) {
  $ret= (object)array('err'=>'');
  $ares= "http://wwwinfo.mfcr.cz/cgi-bin/ares/darv_std.cgi?ico";
//                                                        $ico++;
  $url= "$ares=$ico#3";
                                                        display($url);
   $xml= file_get_contents($url);
  libxml_use_internal_errors(true);
  $xml= strtr($xml,array('are:'=>'','dtt:'=>'','udt:'=>'','xsi:'=>''));
//                                                         display(htmlentities($xml));
  $js= simplexml_load_string($xml);
  if ( $js===false ) {
    foreach (libxml_get_errors() as $error) {
      $ret->err.= "<br>{$error->message}";
      goto end;
    }
  }
  // rozbor odpovědi
  $x= $js->Odpoved->Pocet_zaznamu;
  if ( $x==1 ) {
    $ret->ok= 1;
    $z= (object)$js->Odpoved->Zaznam[0];
    $ret->osoba= 0;
    $ret->firma= (string)$z->Obchodni_firma;
    $ret->ico=      (string)$z->ICO;
    $i= $z->Identifikace[0]->Adresa_ARES;
    $ret->ulice=    (string)$i->Nazev_ulice;
    $ret->obec=     (string)$i->Nazev_obce;
    $ret->psc=      (string)$i->PSC;
  }
  else {
    $ret->ok= 0;
    $ret->err.= "<br>IČO nebylo systémem ARES rozpoznáno";
  }
//                                                         debug($js,"ARES");
end:
                                                        debug($ret,"klub_firma_ico($ico)");
  return $ret;
}
/*
function dump($x) { trace();
  ob_start(); var_dump($x); display(ob_get_contents()); ob_end_clean();
}
*/
# ---------------------------------------------------------------------------------- klub ukaz_clena
# zobrazí odkaz na člena
function klub_ukaz_clena($id_clen,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://klu.cle.show_clen/$id_clen'>$id_clen</a></b>";
}
/*
# -------------------------------------------------------------------------------- klub select_cleny
# zobrazí odkaz, který zařídí aby členové byli selected
function klub_select_cleny($ids_clen,$caption,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://klu.cle.select_cleny/$ids_clen'>$caption</a></b>";
}
*/
# ------------------------------------------------------------------------------------ klub ukaz_dar
//# zobrazí odkaz na dar
//function klub_ukaz_dar($id_dar,$barva='') {
//  $style= $barva ? "style='color:$barva'" : '';
//  return "<b><a $style href='ezer://klu.dry.show_dar/$id_dar'>$id_dar</a></b>";
//}
# --------------------------------------------------------------------------------- klub clen_delete
# smaže člena, pokud k němu není nic připnuto
function klub_clen_delete($idc) {
  global $USER;
  $msg= '';
  $n= select('COUNT(*)','vztah',"id_left=$idc OR id_right=$idc");
  $m= select('COUNT(*)','dar',"id_clen=$idc AND deleted='' ");
  if ($n||$m) {
    if ($n) {
      $msg= "před smazáním je třeba tento kontakt rozpojit z $n vztahů";
    }
    elseif ($m) {
      $msg= "před smazáním osoby je třeba smazat nebo přepsat jejích $m darů";
    }
  }
  else {
    $D= "D {$USER->abbr} ".date('j.n.Y');
    ezer_qry("UPDATE",'clen',$idc,array((object)array('fld'=>'deleted', 'op'=>'u','val'=>$D)));
  }
  return $msg;
}
/** *****************************************************************************=*********==> VZTAH */
# ------------------------------------------------------------------------------- klub vztah_selects
# vytvoří vztah mezi osobami - podle hodnoty $demi_vztah pozná orientaci
function klub_vztah_selects() {
  $sel= $del= '';
  $rv= pdo_qry("SELECT ikona,barva FROM _cis WHERE druh='vztahy' ORDER BY poradi");
  while ($rv && (list($left,$right)= pdo_fetch_row($rv))) {
    $sel.= "$del$left,$right";
    $del= ',';
  }
  return $sel;
}
# ----------------------------------------------------------------------------------- klub vztah_add
# vytvoří vztah mezi osobami - podle hodnoty $demi_vztah pozná orientaci
function klub_vztah_add($pinned,$member,$demi_vztah) {
  $ret= (object)array(msg=>'',idv=>0);
  list($vztah,$left)= select('data,ikona','_cis',"'$demi_vztah' IN (ikona,barva)");
  if ($demi_vztah==$left) { // pinned-member
    $idl= $pinned;
    $idr= $member;
  }
  else { // member-pinned
    $idl= $member;
    $idr= $pinned;
  }
  $ret->idv= ezer_qry("INSERT",'vztah',0,array(
    (object)array('fld'=>'id_left',   'op'=>'i','val'=>$idl),
    (object)array('fld'=>'id_right',  'op'=>'i','val'=>$idr),
    (object)array('fld'=>'vztah',     'op'=>'i','val'=>$vztah)
  ));
  return $ret;
}
# ----------------------------------------------------------------------------------- klub vztah_del
# zruší vztah mezi osobami
function klub_vztah_del($pinned,$member,$demi_vztah) {
  list($vztah,$left)= select('data,ikona','_cis',"'$demi_vztah' IN (ikona,barva)");
  if ($demi_vztah==$left) { // pinned-member
    $idl= $pinned;
    $idr= $member;
  }
  else { // member-pinned
    $idl= $member;
    $idr= $pinned;
  }
  $idv= select('id_vztah','vztah',"id_left=$idl AND id_right=$idr AND vztah=$vztah");
  ezer_qry("UPDATE",'vztah',$idv,array(
    (object)array('fld'=>'deleted',  'op'=>'u','val'=>'D')
  ));
}
/*
# -------------------------------------------------------------------------------- klub oprav_prevod
# opraví dárce v převodu
//function klub_oprav_prevod($ident,$id_clen) {
  query("UPDATE prevod SET clen=$id_clen WHERE ident='$ident' ");
  $opraveno= pdo_affected_rows() ? 1 : 0;
  return $opraveno;
}
*/
# ---------------------------------------------------------------------------------- klub clen_udaje
# ASK: vrátí jméno, příjmení a obec člena zadaného číslem
function klub_clen_udaje ($id_clen) {
  $udaje= 'chybné číslo člena!!!';
  $qry= "SELECT jmeno,prijmeni,obec FROM clen WHERE id_clen='$id_clen'";
  $res= pdo_qry($qry);
  if ( $res && ($row= pdo_fetch_assoc($res)) ) {
    $udaje= "{$row['jmeno']} {$row['prijmeni']}, {$row['obec']}";
  }
  return $udaje;
}
# --------------------------------------------------------------------------------------- klub check
# ASK: zjistí zda je člen dobře vyplněn ($id_clen není pro nového člena definován)
# 1. zda vyplněné rodné číslo nebo IČ není použito u nevymazaného kontaktu
# 2. zda vyplněný obvyklý dárce má správný formát tzn. buďto jméno nebo jméno a plná adresa
function klub_check ($id_clen,$rodcis='',$darce='') { trace();
  if ( !$id_clen ) $id_clen= 0;
  $ok= 1;
  $msg= '';
  $del= '';
  // kontrola jednoznačnosti rodného čísla nebo IČ
  if ( $rodcis ) {
    $ids= '';
    $qry= "SELECT id_clen FROM clen
           WHERE id_clen!=$id_clen AND rodcis='$rodcis' AND left(deleted,1)!='D' ";
    $res= pdo_qry($qry);
    while ( $res && $c= pdo_fetch_object($res) ) {
      $ids.= " {$c->id_clen}";
    }
    if ( $ids ) {
      $ok= 0;
      $msg.= "{$del}POZOR: rodné číslo nebo IČ $rodcis je použito pro: $ids";
      $del= "<br/>";
    }
  }
  // kontrola formátu obvyklého dárce tzn. buďto jméno nebo jméno a plná adresa
  if ( $darce ) {
    $m= null;
    $x= preg_match("/^([^;]+)(?:|;\s*([^;]*);\s*(\d{3}\s*\d{2}[^\d][^;]+))$/",$darce,$m);
//                                                         debug($m,$darce);
    if ( !$x ) {
      $ok= 0;
      $msg.= "{$del}POZOR: položka 'příjemce potvrzení' smí obsahovat pouze jméno nebo "
      . "jméno po středníku doplněné o úplnou adresu: ulice;psč obec (zkuste kliknout [...])";
    }
  }
  return (object)array('ok'=>$ok,'msg'=>$msg);
}
/** ****************************************************************************************==> DARY */
# ----------------------------------------------------------------------------------- klub dary_suma
# ASK: vrátí součet darů dárce, $strediska je seznam _cis.data středisek
function klub_dary_suma ($id_clen) {  trace();
  $suma= 0;
  $qry= "SELECT sum(castka) as suma FROM dar AS dd
         WHERE LEFT(deleted,1)!='D' AND id_clen=$id_clen 
           AND typ IN (8,9) ";
  $res= pdo_qry($qry);
  if ( $res && $u= pdo_fetch_object($res) ) {
    $suma= $u->suma;
  }
  return number_format($suma,2,'.','');
}
/** ***********************************************************************************==> INFORMACE */
# ------------------------------------------------------------------------------------------ klu_inf
# rozskok na informační funkce
function klu_inf($par) {
  $html= '';
  switch($par->fce) {
    case 'vypisy_uplnost': 
      $rok= date('Y') - $par->p;
      $html= klu_inf_vypisy($rok); 
      break;
    case 'stat': 
      $html= klu_inf_stat(); 
      break;
    case 'vyvoj': 
      $html= klu_inf_vyvoj($par->p); 
      break;
    case 'dary_dupl':
      $rok= date('Y') - $par->p;
      $msg= dop_kon_dupl($rok,$par->corr);
      $html= $msg=='' ? "vše ok" : $msg;
      break;
  }
  return $html;
}
# ------------------------------------------------------------==> . úplnost výpisů
# kontrola výpisů roku
# název souboru má tvar "číslo účtu-banka_od_do.csv 
#   kde banka je 0600 nebo 0800 a od a do jsou datumy ve tvaru rrrr-mm-dd
function klu_inf_vypisy($rok) {
  $html= '<i>Kontrolujeme se úplnost výpisů v daném roce, zda jsou měsíční, korektnost jejich názvů,
    při vládání se kontrolovala shoda názvu s obsahem</i>';
  
  $mesice= array(1=>'leden','únor','březen','duben','květen','červen',
    'červenec','srpen','září','říjen','listopad','prosinec');
  // projdeme účty
  $res= pdo_qry("SELECT data,zkratka,hodnota FROM _cis WHERE druh='b_ucty' ORDER BY zkratka ASC");
  while ( $res && (list($ucet,$zkratka,$nazev)= pdo_fetch_row($res)) ) {
    $html.= "<h3>$zkratka: $nazev</h3>";
    $mesic= array(); 
    $rv= pdo_qry("SELECT MONTH(soubor_od),DAY(soubor_od),MONTH(soubor_do),DAY(soubor_do)
      FROM vypis WHERE nas_ucet=$ucet AND YEAR(soubor_od)=$rok ORDER BY soubor_od ASC");
    while ( $rv && (list($mod,$dod,$mdo,$ddo)= pdo_fetch_row($rv)) ) {
      $od= "$rok-$mod-$dod"; $do= "$rok-$mdo-$ddo"; 
      $vypis= "$zkratka $od $do";
      // musí být měsíční
      $last= date('t',strtotime($od))+date('L',$od);
      if ($mod!=$mdo) $mesic[$mod]= "$vypis není měsíční";
      elseif ($dod!=1 || $ddo!=$last) $mesic[$mod]= "$vypis chybná mezní data";
      else $mesic[$mod]= "ok";
    }
    debug($mesic);
    // vyhodnocení
    if (count($mesic)==12) {
      $html.= "výpisy jsou všechny";
    }
    elseif (count($mesic)) {
      $mend= $rok==date('Y') ? date('m') : 13;
      for ($m= 1; $m<$mend; $m++) {
        if (!isset($mesic[$m])) $html.= "chybí $mesice[$m]<br>";
        elseif ($mesic[$m]!='ok') $html.= "chyba $mesice[$m]: $mesic[$m]<br>";
      }
    }
    else {
      $html.= "není ani jeden výpis";
    }
  }
  return $html;
}
# ------------------------------------------------------------==> . duplicitní dary
# kontrola darů roku
function dop_kon_dupl($rok,$corr) {
  $map_zpusob= map_cis('k_zpusob','hodnota');
  $r= " align='right'";
  $html= '';
  $err= 0;
  $msg= '';
  $n_del= $n_kop= $n_ruc= $suma= 0;
  $qry= mysql_qry("
    SELECT castka_kdy,castka,zpusob,
      id_vypis,vu.zkratka,n_vypis,
      id_projekt,GROUP_CONCAT(DISTINCT LEFT(p.nazev,32)),GROUP_CONCAT(DISTINCT id_projekt),
      id_clen,count(*) AS _pocet_,
      prijmeni,jmeno,SUM(IF(
        diky_kdy OR potvrz_kdy OR d.popis!='' OR stredisko!=0  OR d.darce,1,0)),
        GROUP_CONCAT(id_dar)
    FROM dar AS d
    LEFT JOIN clen AS c USING (id_clen)
    LEFT JOIN vypis AS v USING (id_vypis)
    LEFT JOIN projekt AS p USING (id_projekt)
    LEFT JOIN _cis AS vu ON druh='b_ucty' AND data=v.nas_ucet 
    WHERE YEAR(castka_kdy)=$rok AND LEFT(d.deleted,1)!='D'
    GROUP BY castka_kdy,castka,d.id_clen,d.zpusob HAVING _pocet_>1 
    ORDER BY id_dar -- castka_kdy
      ");
  while ( $qry 
    && list($datum,$castka,$zpusob,$idv,$xv,$nv,$idp,$np,$idps,$idc,$pocet,$prijmeni,$jmeno,$rucne,$idds)
      = pdo_fetch_row($qry) ) {
    $datum= sql_date1($datum);
    $zpusob= $map_zpusob[$zpusob];
    $suma+= $castka*($pocet-1);
    $clen= klub_ukaz_clena($idc);
    if ( $rucne ) { // na vyžádání automatická oprava
      $pozn= $rucne; 
      if ($corr==2 && $zpusob=='bankou') {
        list($delete,$dkdy,$pkdy,$poz,$pop,$str)= 
            select('id_dar,diky_kdy,potvrz_kdy,pozn,popis,stredisko',
                'dar',"id_dar IN ($idds) AND id_vypis=0");
        $update= select('id_dar','dar',"id_dar IN ($idds) AND id_vypis!=0");
        if ($delete && $update) {
          $pozn.= " doplnit údaje: $update, smazat: $delete z $idds";
          // smažeme starý dar
          $new= 'D duplicita';
          ezer_qry("UPDATE",'dar',$delete,array(
            (object)array('fld'=>'deleted',  'op'=>'u','val'=>$new)
          ));
          // zkopírujeme údaje do nového
          ezer_qry("UPDATE",'dar',$update,array(
            (object)array('fld'=>'diky_kdy',  'op'=>'u','val'=>$dkdy),
            (object)array('fld'=>'potvrz_kdy','op'=>'u','val'=>$pkdy),
            (object)array('fld'=>'pozn',      'op'=>'u','val'=>$poz),
            (object)array('fld'=>'popis',     'op'=>'u','val'=>$pop),
            (object)array('fld'=>'stredisko', 'op'=>'u','val'=>$str)
          ));
          $n_kop++;
        }
        else {
          $pozn.= " upravit ručně";
          $n_ruc++;
        }
      }
    }
    else { // na vyžádání automatický výmaz
      $pozn= '';
      if ($corr==1 && $zpusob=='bankou') {
        $delete= select('id_dar','dar',"id_dar IN ($idds) AND id_vypis=0");
        if ($delete) {
          $pozn.= "smazat: $delete z ($idds)";
          // smažeme starý dar
          $new= 'D duplicita';
          ezer_qry("UPDATE",'dar',$delete,array(
            (object)array('fld'=>'deleted',  'op'=>'u','val'=>$new)
          ));
          $n_del++;
        }
        else {
          $pozn.= " upravit ručně";
          $n_ruc++;
        }
      }
    }
    $bankou= $zpusob=='bankou' ? "$xv:$nv" : '';
    $online= $zpusob=='online' ? "($idps):$np" : '';
    $msg.= "<tr><td$r>$datum</td><td>$zpusob</td><td$r>$bankou</td><td>$online</td>
      <td$r><b>$castka,-</b></td><td>{$pocet}x</td>
      <td$r>$clen</td><td>$prijmeni $jmeno</td><td>$pozn</td></tr>";
    $err++;
  }
end:  
  $html.= $msg=='' ? 'Nebyl zjištěn žádný problém' : "<h3>Podezřelé (stejný dárce, den a způsob daru) zápisy darů v roce $rok</h3>";
  $html.= $corr ? "$n_del darů bylo smazáno, $n_kop údajů převedeno, ručně zbývá posoudit $n_ruc takových duplicit<hr>" : '';
  $html.= "<table>$msg</table>";
  if ( $err ) $html= "CELKEM JE PODEZŘELÝCH DARŮ: $err (v přehledu darů je tedy asi $suma Kč navíc)<hr>$html";
  return $html;
}
# ------------------------------------------------------------------------------------- klu_inf_stat
# základní statistika
function klu_inf_stat() { trace();
  $html= '';
  // kontakty
  $c= pdo_object("SELECT count(*) as _pocet FROM clen
                    WHERE left(clen.deleted,1)!='D' AND umrti=0 ");
  $html.= "<br>počet známých kontaktů = <b>{$c->_pocet}</b>";
  // dárci
  $clenu= $daru= 0;
  $qry= "SELECT count(*) as _pocet FROM dar JOIN clen USING(id_clen)
         WHERE left(dar.deleted,1)!='D' AND typ IN (8,9)
         GROUP BY id_clen";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $clenu++;
    $daru+= $x->_pocet;
  }
  $html.= ", z toho je <b>$clenu</b> dárců s celkem <b>$daru</b> dary";
  return $html;
}
# ------------------------------------------------------------------------------------ klu_inf_vyvoj
# vývoj Klubu
function klu_inf_vyvoj($od) { trace();
  $letos= date('Y');
  // dary
  $fin_p= $vec_p= array();              // roční histogramy - počty
  $fin_s= $vec_s= array();              // roční histogramy - sumy
  $qry= "SELECT year(castka_kdy) as _year, castka, zpusob FROM dar JOIN clen USING(id_clen)
         WHERE left(dar.deleted,1)!='D' AND left(clen.deleted,1)!='D' 
           AND (typ=9 OR typ=8 AND dar.id_clen!=0)";
  $res= pdo_qry($qry);
  while ( $res && ($d= pdo_fetch_object($res)) ) {
    $y= $d->_year;
    if ( $d->zpusob==4 ) {                // věcný dar
      $vec_p[$y]++;
      $vec_s[$y]+= $d->castka;
    }
    else {                              // finanční dar
      $fin_p[$y]++;
      $fin_s[$y]+= $d->castka;
    }
  }
  // zobrazení
  $html.= "<table class='stat'>";
  $html.= "<tr><th>rok</th>
          <th align='right'>počet finančních darů</th>
          <th align='right'>a součty částek</th>
          <th align='right'>počet věcných darů</th>
          <th align='right'>a jejich hodnoty</th></tr>";
  for ($r= $od; $r<=$letos; $r++) {
    $html.= klu_vyvoj_row($r,$fin_p[$r],$fin_s[$r],$vec_p[$r],$vec_s[$r]);
  }
  $html.= "</table>";
//                                                 debug($clen_od,'clen_od');
  return $html;
}
function klu_vyvoj_row($r,$x1,$x2,$x3,$x4) {
  $html.= "<tr>";
  $html.= "<th>$r</th>";
  $x= number_format(round($x1), 0, '.', ' ');  $html.= "<td align='right'>$x</td>";
  $x= number_format(round($x2), 0, '.', ' ');  $html.= "<td align='right'>$x</td>";
  $x= number_format(round($x3), 0, '.', ' ');  $html.= "<td align='right'>$x</td>";
  $x= number_format(round($x4), 0, '.', ' ');  $html.= "<td align='right'>$x</td>";
  $html.= "</tr>";
  return $html;
}
?>
