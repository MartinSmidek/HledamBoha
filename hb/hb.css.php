<?php # (c) 2010 Martin Smidek <martin@smidek.eu>
# Aplikace HledámBoha
# (c) 2022 Martin Smidek <martin@smidek.eu>
header("Content-type: text/css");
if ( !isset($_SESSION) ) session_start();
$ezer_root= $_GET['root'];
$skin= $_SESSION[$ezer_root]['skin'] ? $_SESSION[$ezer_root]['skin'] : 'default';
# pokud je v root-adresáři aplikace složka skins se souborem colors.php
# musí v něm být obsažen příkaz global spřístupňující změněné barvy a cestu k obrázkům
global $skin, $path, $c, $b, $ab, $c_appl,
  $c_menu, $b_menu, $c_main, $b_main,
  $c_group, $b_group, $s_group, $c_item, $b_item, $fb_item, $fc_item, $s_item, $s2_item,
  $b_brow, $b2_brow, $b3_brow, $b4_brow, $b5_brow, $b6_brow, $c_brow, $s1_brow, $s2_brow,
  $c_kuk, $c2_kuk, $b_kuk, $s_kuk, $b_doc_modul, $b_doc_menu, $b_doc_form,
  $b_parm, $b_work;
# c_=color, b_=background-color, a?_=aktivní, f?_=focus, s_=speciál

require_once("../skins/colors.php");

echo <<<__EOD
/* úpravy pro CKEditor 4.6 */

span.cke_toolgroup { padding-right:0; }
span.cke_top { padding: 4px 8px 0px; }


/* hlavní menu - odlišení pro skill=a|m */
ul.MainMenu li.Active a { text-shadow: 1px 1px 2px black; }
ul.MainMenu li.a a { color:yellow !important; text-shadow: 1px 1px 2px black; }
ul.MainMenu li.m a { color:orange !important; text-shadow: 1px 1px 2px black; }

/* podmenu - odlišení pro skill=a|m */
div.MainTabs li a   { text-shadow: 1px 1px 2px black; }
div.MainTabs li.a a { color:yellow !important; }
div.MainTabs li.m a { color:orange !important; }
  
/* leftmenu - odlišení pro skill=a|m */
div.MenuGroup3.a a { color:yellow !important; text-shadow: 1px 1px 2px black; }
div.MenuGroup3.m a { color:orange !important; text-shadow: 1px 1px 2px black; }
div.MenuGroup3 li.a { color:yellow !important; text-shadow: 1px 1px 2px black; }
div.MenuGroup3 li.m { color:orange !important; text-shadow: 1px 1px 2px black; }
  
/* úpravy standardu */
#paticka {
  bottom:16px; }
.PanelRight {
  height:100% !important; }
.BrowseSmart td.BrowseQry input {
  background-color:#effdf1; }
.SelectDrop li {
  white-space:normal !important; }
.Label3, .Check {
  color:#456; }
.Label3 h1 {
  font-size:12pt; margin:0; padding:0 }
.href:hover {
  background-color:#fb6 !important; }
.href {
  text-decoration:underline; color:navy; cursor:pointer; }
#Content, #Index {
  padding:15px; }
.CheckT input {
  position:absolute; top:10px; left:-3px; }
.ae_inp         { position:absolute; }
/* specifika formulářů */
.lege           { font-size:x-small }
/*.inAccordion    { width:670px; }*/
/* prvky v akordeon-menu */
.zIndex1        { z-index:1; }
.info           { min-height:545px; height:100%; z-index:0; }
.info-stat      { width:624px; height:100%; z-index:0; background-color:#dce7f4; padding:5px; }
/* Label jako přepínací tlačítko */
.ae_butt_on {
  cursor:default; background-color:$b_work; z-index:0; border-radius:5px; }
.ae_butt_off {
  cursor:default; background-color:#EFF1FD; z-index:0; border-radius:5px; }
.ae_butt_on:hover {
  background:url("../skins/ch/label_switch_on_hover.png") repeat-x scroll 0 -1px transparent !important; }
.ae_butt_off:hover {
  background:url("../skins/ch/label_switch_off_hover.png") repeat-x scroll 0 -1px transparent !important; }
/* rámečky formulářů */
.ae_info        {
  background-color:#f5f5f5; border:1px solid #f5f5f5; z-index:-1; border-radius:5px; }
.ae_work        {
  background-color:$b_work; z-index:0; border-radius:5px; }
.ae_parm        {
  background-color:$b_parm; border:1px solid #f5f5f5; z-index:0; border-radius:5px;  }
.parm2          { 
  background-color:#2c8931;; border:1px solid #f5f5f5; border-radius:5px; z-index:1;}
/* barvení řádků browse */
.fis_red        { background-color:#933; }
.kas_1          { background-color:#ff6 !important; }
.dar_1          { background-color:#fbb !important; }
.dar_2          { background-color:#ffa !important; }
.vyp_n          { background-color:#fdd !important; }
.vyp_r          { background-color:#dfd !important; }
.vyp_p          { background-color:#ddf !important; }
.pre_5          { background-color:#ffffaa !important; }
.pre_7          { background-color:#7ff6ff !important; }
.pre_8          { background-color:#ffbb66 !important; }
.pre_9          { background-color:#77ff77 !important; }
.pre_10         { background-color:#cccccc !important; }
.sedy           { background-color:#777777 !important; }
.nasedly        { background-color:#dddddd !important; }
.zluty          { background-color:#ffff77 !important; }
.cerveny        { background-color:#ff7777 !important; }
.zeleny         { background-color:#77ff77 !important; }
.modry          { background-color:#7389ae !important; }
.termin         { background-color:#f00 !important; }
.diskuse        { background-color:#fa0 !important; }
/* */
.aktivni        { background-color:#ffeed6 !important; border:1px solid #ffeed6 !important; }
.pasivni        { background-color:#f5f5f5 !important; border:1px solid #f5f5f5 !important; }
.chyba          { color:#700; font-weight:bold; background-color:yellow; z-index:3; }
/* grafy */
.graphs         { background-color:#fff !important; left:10px; }
.graph          { margin-bottom:20px; }
.graph_title    { background-color:#D1DDEF; padding-left:10px; }
/* tabulky */
dd              { margin-left:20px; }
table.vypis     { border-collapse:collapse; width:100%; }
table.dary      { width:200px; }
.vypis th       { border:1px solid #777; background-color:#eee; }
.vypis td       { border:1px solid #777; }
.chart          { float:right; }
.fis_kat        { font-family:Courier; font-weight:bold; }
.button_small   { width:20px; height:20px; font-size:x-small; }
/* = = = = = = = = = = = = = = = = = = = = = = statistika */
table.stat      { border-collapse:collapse; font-size:8pt; width:745px; }
.stat td        { border:1px solid #777; background-color:#fff; padding:0 3px;}
.stat th        { border:1px solid #777; background-color:$b_item; }
.stat dt        { margin:10px 0 0 0; }
#proj table     { border-collapse:collapse; }
#proj td        { border:1px solid #aaa;font:x-small Arial;color:#777;padding:0 3px;}
#proj td.title  { color:#33a;}
#proj td.label  { color:#a33;}
.odhad          { background-color:#ffdab9 !important; }
/* = = = = = = = = = = = = = = = = = = = = = = rozesilani */
table.roze      { border-collapse:collapse; }
.roze td        { border:1px solid #777; text-align:right; background-color:#eee; padding:3px; color:black;}
.roze th        { border:1px solid #777; text-align:right; background-color:#aaa; }

/* = = = = = = = = = = = = = = = = = = = = = = struktura dat */
table.struc     { border-collapse:collapse; /*font-size:8pt;*/ max-width:calc(100% - 35px); 
                  width:unset !important}
.struc td       { border:1px solid #777; background-color:#fff; padding:0 3px;}
.struc th       { border:1px solid #777; background-color:$b_work; }
.struc dt       { margin:10px 0 0 0; }
/* = = = = = = = = = = = = = = = = = = = = = = HELP */
div.ContextHelp { width:740px !important}

__EOD;
?>
