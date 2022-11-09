<?php
# Aplikace HB pro HledámBoha.cz
# (c) 2022 Martin Šmídek <martin@smidek.eu>

  global // import 
    $ezer_root; 
  global // export
    $EZER, $ezer_server, $ezer_version;
  global // klíče
    $api_gmail_user, $api_gmail_pass;
  
  // vyzvednutí ostatních hodnot ze SESSION
  $ezer_server=  $_SESSION[$ezer_root]['ezer_server'];
  $ezer_version= $_SESSION[$ezer_root]['ezer'];
  $abs_root=     $_SESSION[$ezer_root]['abs_root'];
  $rel_root=     $_SESSION[$ezer_root]['rel_root'];
  chdir($abs_root);

  // inicializace objektu Ezer
  $EZER= (object)array(
      'version'=>'ezer'.$_SESSION[$ezer_root]['ezer'],
      'options'=>(object)array(
          'mail' => "martin@smidek.eu",
          'phone' => "603&nbsp;150&nbsp;565",
          'author' => "Martin",
      ),
      'activity'=>(object)array('skip'=>'MSM'));
  
  // databáze
  $deep_root= "../files/hb";
  require_once("$deep_root/hb.dbs.php");
  
  // archiv sql
  $path_backup= "$deep_root/sql";
  
  $tracked= ',clen,dar,projekt,ukol,dopis,vztah,_user,_cis,';
  
  // PHP moduly aplikace Ark
  $app_php= array(
//    "ck/ck.dop.php", ?
    "hb/hb.$.php",
    "hb/hb.klu.php",
    "hb/hb.klu.pre.php",
    "hb/hb.dop.php",
    "hb/hb.eko.php",
    "hb/hb_pdf.php",
    "hb/hb_tcpdf.php"
  );
  
  // PDF knihovny
  require_once('tcpdf/tcpdf.php');

  // stará verze json
  require_once("ezer$ezer_version/server/licensed/JSON_Ezer.php");

  // je to aplikace se startem v rootu
  chdir($_SESSION[$ezer_root]['abs_root']);
  require_once("{$EZER->version}/ezer_ajax.php");

  // specifické cesty
  global $ezer_path_root;

  $path_www= './';
?>
