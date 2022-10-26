<?php # (c) 2009 Martin Smidek <martin@smidek.eu>
# ================================================================================================== REPORT
# -------------------------------------------------------------------------------------------------- report2list
# funkce ztransformuje zápis reportu v JSON do list-struktury
#   {tg:box,...}
function report2list($report) { trace();
//                                                 debug($report,'report2list');
  $list= array();
  if ( is_array($report->boxes) ) {
    foreach ($report->boxes as $b) {
      $obj= (object)array(tg=>'box');
      $obj->atr= (object)array(type=>$b->type,id=>$b->id,l=>$b->left,t=>$b->top,w=>$b->width,h=>$b->height);
      if ( $b->txt )
        $obj->txt= html2list($b->txt,true);
      if ( $b->style ) {
        foreach(explode(';',$b->style) as $pair) {
          list($atrib,$values)= explode(':',$pair);
          $value= explode(',',$values);
          switch ( $atrib ) {
          case 'font':
            $obj->font_name=  $value[0];
            $obj->font_style= $value[1];
            $obj->font_size=  $value[2];
            $obj->line_height=$value[3];
            break;
          case 'align':
            $obj->align=  $value[0];
            break;
          case 'tabs':
            $obj->tabs=  $values;
            break;
          case 'border':
            $obj->border= $values;
            break;
          }
        }
      }
      $list[]= $obj;
    }
  }
  else {
//                                                 debug($report,'report2list');
  }
  return $list;
}
# ================================================================================================== PDF
# -------------------------------------------------------------------------------------------------- sheets2pdf
# funkce vytiskne seznam požadavků do pdf souboru
# $reports = array ( $report0, $report1, ... )
# $sheets = array ( object(report:n,pars:$pars), ... )     kde $pars je substituční tabulka
# omezení: format=A4
function sheets2pdf ($file,$sheets,$reports) { trace();
  global $pdf, $pdf_pars;
  global $ezer_path_root;
  chdir($ezer_path_root);
  $lists= array();
  foreach ($reports as $report) {
    $list= report2list($report);
    list_plus($list);  // vylepšení list
    $lists[]= $list;
  }
//   require_once("fpdf/pdf.php");
  $pdf= new PDF('P','mm',array(210,297));  //        'A4');
  $pdf->SetDisplayMode('fullpage');
    $pdf->AddFont('times','','a628e69e0845377d57de62302c98034b_times.php');
    $pdf->AddFont('times','B','a628e69e0845377d57de62302c98034b_timesbd.php');
    $pdf->AddFont('times','I','a628e69e0845377d57de62302c98034b_timesi.php');
    $pdf->AddFont('times','BI','a628e69e0845377d57de62302c98034b_timesbi.php');
  $pdf->SetFont('times','',12);
  $pdf->SetDrawColor(0,0,0);
  $pdf->SetLineWidth(0.1);
  // tranformace do PDF
  foreach ($sheets as $sheet) {
    $pdf->SetAutoPageBreak(true,5);
    $pars= $sheet->pars;
    // převeď pars na list
    foreach ($pars as $i=>$par) {
      $pdf_pars[$i]= is_array($par) ? $par : html2list($par,false);
    }
    $pdf->AddPage('','',true);
    $pdf->SetMargins(0,0,0);
    $pdf->SetFont('times','',12);
    pdf_page($lists[$sheet->report],array('line'=>6));
  }
  // zakončení
  $code= $pdf->Output('','S');
  if ( @file_put_contents($file,$code) )
    $ok= true;
  else {
    display("$file nelze zapsat");
    $ok= false;
  }
  return $ok;
}
# -------------------------------------------------------------------------------------------------- report2pdf
# funkce tranformuje $report na $pdf
# $parss je NULL nebo pole substitučních tabulek
# pokud $report->format='A4:l,t,w,h' mají být na stránce tištěny štítky podle tabulky
# jinak bude vytištěna 1 stránka na 1 řádek tabulky
function report2pdf ($report,$root=1,$file='',$parss=NULL,$vodotisk='') {
                                                display("report2pdf(report,$root,$file,$parss)");
  global $pdf, $pdf_pars;
  $list= report2list($report);
//                                                 debug($list,'=report2list');
  list($page,$margins)= explode(':',$report->format);
//                                                 debug(array($page,$margins));
//   if ( $margins ) {             // pro tisk štítků
//   require_once("fpdf/pdf.php");
  $pdf= new PDF('P','mm',array(210,297));  //        'A4');
  $pdf->SetDisplayMode('fullpage');
    $pdf->AddFont('times','','a628e69e0845377d57de62302c98034b_times.php');
    $pdf->AddFont('times','B','a628e69e0845377d57de62302c98034b_timesbd.php');
    $pdf->AddFont('times','I','a628e69e0845377d57de62302c98034b_timesi.php');
    $pdf->AddFont('times','BI','a628e69e0845377d57de62302c98034b_timesbi.php');
  $pdf->SetFont('times','',12);
//     $pdf->AddFont('arial','','a628e69e0845377d57de62302c98034b_arial.php');
//   $pdf->SetFont('arial','',12);
  $pdf->SetDrawColor(0,0,0);
  $pdf->SetLineWidth(0.1);
//                                                 debug($pdf,'pdf');
  // vylepšení list
  list_plus($list);
  // maticový tisk -- na stránku bude vytištěno $nw na šířku a $nh na výšku
  // tranformace $list do PDF
  if ( $parss && $margins ) {
    // tisk štítků
    $pdf->SetAutoPageBreak(false,5);
    list($ml,$mt,$mw,$mh)= explode(',',$margins);
    $nw= floor($pdf->w/$mw);  // počet na šířku
    $nh= floor($pdf->h/$mh);  // počet na výšku
//                                                         display("štítky:$nw/$nh");
    $lMargin= $pdf->lMargin;
    $tMargin= $pdf->tMargin;
    $rMargin= $pdf->rMargin;
    $ip= 0;
    while ( $ip < count($parss) ) {
      $pdf->AddPage('','',true);
      if ( $vodotisk )
        $pdf->Image($vodotisk,0.0,0.0,210,297);
      for ($ih= 0; $ih<$nh; $ih++) {
        for ($iw= 0; $iw<$nw; $iw++) {
          // převeď pars na list
          if ( $ip==count($parss) ) break 3;
          $pars= $parss[$ip++];
          foreach ($pars as $i=>$par) {
            $pdf_pars[$i]= html2list($par,false);
          }
          $mr= $pdf->CurPageFormat[0]-($ml+$mw*($iw+1));
          $pdf->SetXY($ml+$mw*$iw,$mt+$mh*$ih);
          $pdf->SetMargins($ml+$mw*$iw,$mt+$mh*$ih,$mr);
//                                               debug(array($pdf->lMargin,$pdf->tMargin,$pdf->rMargin),"margins:$ih/$iw");
          pdf_page($list,array());
        }
      }
    }
    $pdf->SetMargins($lMargin,$tMargin,$rMargin);
  }
  else if ( $parss ) {
                                                        display("A4");
    $pdf->SetAutoPageBreak(true,5);
    foreach ($parss as $pars) {
//                                                         debug($pars,'pars');
      // převeď pars na list
      foreach ($pars as $i=>$par) {
        $pdf_pars[$i]= is_array($par) ? $par : html2list($par,false);
      }
//                                                         debug($pdf_pars,'pdf_pars');
      $pdf->AddPage('','',true);
      $pdf->SetMargins(0,0,0);
//                                                         debug($list,'list');
      $pdf->SetFont('times','',12);
      pdf_page($list,array('line'=>6));
    }
  }
  else {
    $pdf_pars= NULL;
    $pdf->AddPage('','',true);
    pdf_page($list,array());
  }
  // zakončení
  $code= $pdf->Output('','S');
  if ( $file ) {
    if ( @file_put_contents($file,$code) )
      $code= 'ok';
    else {
      display("$file nelze zapsat");
      $code= 'ko';
    }
  }
  return $code;
}
# -------------------------------------------------------------------------------------------------- list_plus
# funkce tranformuje $list
#   očísluje li vnořené do ol
#   přejmenuje li vnořené do ul jako lu
function list_plus (&$list) {
//                                                         display("list_plus()");
  // transformuj atributy
  if ( ($atr= $list->atr) && is_string($atr) ) {
    if (strstr($atr,'right')) $list->align= 'R';
  }
  // projdi celou strukturu
  if ( is_array($list) ) {
    for ($i= 0; $i<count($list); $i++)
      list_plus($list[$i]);
  }
  else switch ( $list->tg ) {
    case 'ol':
      $n= 0;
      for ($i= 0; $i<count($list->txt); $i++)
        if ( $list->txt[$i]->tg=='li' )
          $list->txt[$i]->n= ++$n;
      break;
    default:
      if ( $list->txt ) {
        list_plus($list->txt);
      }
      break;
  }
}
# -------------------------------------------------------------------------------------------------- pdf_page
# funkce použije $list jako template stránky a
# $pdf_pars jako substituční tabulku pro tg=='par'
function pdf_page ($list,$options) {
//                                                 display("pdf_page({$list->tg})");
//                                                 debug($options,"pdf_page({$list->tg})");
  global $ezer_path_root;
  global $pdf, $pdf_pars;
  if ( is_string($list) || is_numeric($list)  ) {
    $code= iconv("utf-8","windows-1250",$list);
    if ( $options['align'] )
      $pdf->WriteAlign($options['line'],$code,'',$options['align']);
    else
      $pdf->Write($options['line'],$code,'',$options['align']);
  }
  elseif ( is_array($list) ) {
    foreach ($list as $elem)
      pdf_page($elem,$options);
  }
  else switch ( $tag= $list->tg ) {
    case 'b':
    case 'strong':
      $style= $pdf->FontStyle;
      $pdf->SetFont('','B');
      pdf_page($list->txt,$options);
      $pdf->SetFont('',$style);
      break;
    case 'i':
    case 'em':
      $style= $pdf->FontStyle;
      $pdf->SetFont('','I');
      pdf_page($list->txt,$options);
      $pdf->SetFont('',$style);
      break;
    case 'par':
      pdf_page($pdf_pars[$list->txt],$options);
      break;
    case 'box':
      $a= $list->atr;
      $ml= $pdf->lMargin;
      $mt= $pdf->tMargin;
      $mb= $pdf->CurPageFormat[1]-$pdf->bMargin;
      if ( $a->type=='tabs' || $a->type=='tabs_list' ) {
        if (!$list->tabs ) fce_error("pdf_page: box 'tabs' neobsahuje tabulátory");
        // může obsahovat jen text tabulky
        $tab_tab= $tab_aln= $tab_sty= array();
        foreach (explode(',',$list->tabs) as $i => $tas) {
          list($tab_tab[$i],$taba)= explode('-',$tas);
          $tab_aln[$i]= $taba ? (strpos($taba,'R')!==false ? 'R'
            : ( strpos($taba,'C')!==false ? 'C' : '') ) : '';
          $tab_sty[$i]= $taba ? (strpos($taba,'I')!==false ? 'I'
            : ( strpos($taba,'B')!==false ? 'B' : '') ) : '';
        }
        $fSize= $pdf->FontSizePt;
        $style= $pdf->FontStyle;
        if ( $list->font_size ) $pdf->SetFontSize($list->font_size);
        // rozbor tabulky
        if ( $a->type=='tabs' ) {
          $lines= 0;
          foreach ($list->txt[0] as $tr) if ( is_string($tr) ) $lines++;
          $lh= $a->h/$lines;
          $txt= $list->txt[0];
        }
        else {  // data v poli
          $lh= $a->h;
          $txt= $pdf_pars['ryby'];
        }
//                                                                         debug($list->txt,$a->type);
        $line= 0;
        $ttr= $mt+$a->t;
        foreach ($txt as $tr) if ( is_string($tr) ) {
          $tr= iconv("utf-8","windows-1250",$tr);
          foreach (explode('|',$tr) as $i => $td) {
            $tdl= $ml+$a->l+$tab_tab[$i];
            $pdf->SetXY($tdl,$ttr);
            $tdw= (isset($tab_tab[$i+1]) ? $tab_tab[$i+1] : $a->w) - $tab_tab[$i];
            $pdf->SetFont($list->font_name,$tab_sty[$i]);
            $pdf->Cell($tdw,$lh,$td,0,0,$tab_aln[$i]);
          }
          // případně rámeček
          if ( $list->border ) {
            if ( strpos($list->border,'t')!==false )
              $pdf->Line($ml+$a->l, $ttr, $ml+$a->l+$a->w, $ttr);
            if ( strpos($list->border,'r')!==false )
              $pdf->Line($ml+$a->l+$a->w, $ttr, $ml+$a->l+$a->w, $ttr+$a->h);
            if ( strpos($list->border,'b')!==false )
              $pdf->Line($ml+$a->l, $ttr+$a->h, $ml+$a->l+$a->w, $ttr+$a->h);
            if ( strpos($list->border,'l')!==false )
              $pdf->Line($ml+$a->l, $ttr, $ml+$a->l, $ttr+$a->h);
          }
          // nastav další řádek
          if ( $a->type=='tabs_list' && $ttr+2*$a->h > $mb ) {
            $pdf->AddPage('','',true);
            $ttr= $mt+$a->h;
          }
          else
            $ttr+= $lh;
        }
        $pdf->SetFont('',$style,$fSize);
      }
      else {
        if ( $list->atr->type=='O' || $list->atr->type=='dop_znacky' ) {
          $pdf->Rect($ml+$a->l,$mt+$a->t,$a->w,$a->h,'D');
        }
        elseif ( $list->atr->type=='dop_logo' ) {
          $pdf->Image("$ezer_path_root/img/dopis_logo.jpg",$ml+$a->l,$mt+$a->t,$a->w,$a->h);
        }
        if ( $list->txt ) {
          $mr= $pdf->rMargin;
  //                                                 debug(array($list->font_size,$a,$ml,$mt,$mr,$pdf->CurPageFormat[0]),'box');
          $r= $pdf->CurPageFormat[0]-($ml+$a->l+$a->w);
          $pdf->SetXY($ml+$a->l,$mt+$a->t);
          $pdf->SetMargins($ml+$a->l,$mt+$a->t,$r);
          if ( $list->font_size ) {
            $fSize= $pdf->FontSizePt;
            $pdf->SetFontSize($list->font_size);
            $options['line']= $list->line_height ? $list->line_height : $list->font_size/2;
          }
          if ( $list->align ) $options['align']= $list->align;
          pdf_page($list->txt,$options);
  //                                                 display("pdf_page: $r");
  //                                                 debug($list,'Rect');
          $pdf->SetMargins($ml,$mt,$mr);
          if ( $list->font_size ) $pdf->SetFontSize($fSize);
        }
      }
      break;
    case 'hr':  // tadu bude podpis
      $pdf->Image("$ezer_path_root/img/dopis_podpis.jpg",null,null,86,10);
//                                                                 display("hr - image");
      break;
    case 'br':
      $pdf->Ln($options['line']);
      break;
    case 'ol':
    case 'ul':
      $pdf->Ln($options['line']);
      pdf_page($list->txt,$options);
      break;
    case 'li':
      $ml= $pdf->lMargin;
      $pdf->Ln($options['line']);
      $pdf->SetLeftMargin($ml+5);
      $pdf->Write($options['line'],"{$list->n}. ");
      $pdf->SetLeftMargin($ml+10);
      pdf_page($list->txt,$options);
      $pdf->SetLeftMargin($ml);
      break;
    case 'p':
      $pdf->Ln($options['line']);
      if ( $list->align ) $options['align']= $list->align;
      pdf_page($list->txt,$options);
      $pdf->Ln($options['line']);
      break;
    default:
      if ( $list->txt )
        pdf_page($list->txt,$options);
      break;
  }
}
# ================================================================================================== HTML
# -------------------------------------------------------------------------------------------------- list2html
# funkce tranformuje $list na HTML
# pokud je $file zapíše jej do souboru
# $parss je NULL nebo pole substitučních tabulek
function list2html ($list,$file='',$parss=NULL) {
                                                        display("list2html($list,$file,$parss)");
  global $pdf_pars, $list_trace;
  // tranformace $list do HTML
  $trace= false;
  $html= '';
  if ( $parss ) {
    foreach ($parss as $pars) {
      // převeď pars na list
      foreach ($pars as $i=>$par) {
        $pdf_pars[$i]= html2list($par,false);
      }
      $html= html_page($list);
    }
  }
  else {
    $pdf_pars= NULL;
    $html= html_page($list);
  }
  // zakončení
  if ( $file ) {
    $html= <<<__END
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
 <head>
  <title>Dopis</title>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
 </head>
 <body>
   $html
 </body>
</html>
__END
    ;
    file_put_contents($file,$html);
    $code= 'ok';
  }
  if ( $list_trace )
    $html= nl2br(htmlspecialchars($html,ENT_NOQUOTES));
  return $html;
}
# -------------------------------------------------------------------------------------------------- html_page
function html_page ($list) {
//                                                 display("html_page()");
  global $pdf_pars, $list_trace;
  $html= '';
  $t= $list_trace ? "\n" : '';
  if ( is_string($list) || is_numeric($list) ) {
    $html.= htmlspecialchars($list,ENT_NOQUOTES);
  }
  elseif ( is_array($list) )
    foreach ($list as $elem)
      $html.= html_page($elem);
  else switch ( $tag= $list->tg ) {
  case 'h1':
  case 'h2':
  case 'h3':
  case 'h4':
  case 'h5':
  case 'h6':
    $html.= "$t<$tag>".html_page($list->txt)."</$tag>";
    break;
  case 'ul':
  case 'ol':
    $html.= "$t<$tag>".html_page($list->txt)."</$tag>";
    break;
  case 'li':
    $n= $list->n;
    $html.= "$t<li>".html_page($list->txt)."</li>";
    break;
  case 'strong':
  case 'b':
  case 'i':
    $html.= "$t<$tag>".html_page($list->txt)."</$tag>";
    break;
  case 'p':
    $html.= "$t<p>".html_page($list->txt)."</p>";
    break;
  case 'br':
    $html.= "$t<br/>";
    break;
  case 'img':
    $prefix= strpos($bc_style->lib,':') ? 'file:///' : '';
    $html.= "$t<img src='$prefix{$bc_style->lib}".html_page($list->txt)."' {$list->atr}>";
    break;
  case 'hr':
    $html.= "$t<hr>";
    break;
  case 'tab':
    $html.= "$t<table>".html_page($list->txt)."</table>";
    break;
  case 'tr':
    $html.= "$t<tr>".html_page($list->txt)."</tr>";
    break;
  case 'td':
    $html.= "$t<td>".html_page($list->txt)."</td>";
    break;
  case 'box':
    $html.= "$t [".html_page($list->txt)."]";
    break;
  case 'par':
    $html.= $t.($pdf_pars
      ? html_page($pdf_pars[$list->txt])
      : "<font color='red'>{$list->txt}</font>");
    break;
  default:
    $html.= "$t<u>$tag</u>";
    break;
  }
  return $html;
}
# ================================================================================================== HTML2LIST
# -------------------------------------------------------------------------------------------------- html2list
# funkce převede $html do list
# $parms=true způsobí zpracování {xxx} na object(tg=>'par',txt=>'xxx'}
function html2list($html,$parms=false) {
//                                                      display("html2list()");
  global $list, $top;
  $list= array(array());
  $list_n= 0;
//   $html= "<html><body>test <b>tučně a <i align='right'>kurzíva</i></b> konec </body></html>";
//   $html= "<b>tučně</b> <i align='right'>kurzíva</i> ";
  $struct= array();
  $stack= array(''); $top= 0;
  $attrs= array();
  // projdi elementy textu
  // na php/linux FCKeditor dává \ před uvozovky
  $html= strtr($html,array('\"'=>'"',"\r"=>'','&nbsp;'=>' '));
  $patt= $parms ? "/([<{])([\/\w]*)([^>}]*)([>}])/" : "/(<)([\/\w]*)([^>]*)(>)/";
  $parts= preg_split($patt,$html,-1,PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
//                                                         debug($parts,'parts');
  for ($i= 0; $i<count($parts); $i++) {
    if ( $parts[$i]=='{' )  {
      $obj= (object)array('tg'=>'par','txt'=>$parts[$i+1]);
//                                                         debug($obj,'obj');
      if ( is_array($list[$top]) ) array_push($list[$top],$obj); else $list[$top]= $obj;
//                                                         debug($list,$i);
      $i+= 2;
    }
    elseif ( $parts[$i]=='<' )  {
      $tag= $parts[++$i];
      if ( $tag=='br/' || $tag=='br' ) {
        $i++;
        $obj= (object)array('tg'=>'br');
        if ( is_array($list[$top]) ) array_push($list[$top],$obj); else $list[$top]= $obj;
      }
      elseif ( $tag=='hr/' || $tag=='hr' ) {
        $i++;
        $obj= (object)array('tg'=>'hr');
        if ( is_array($list[$top]) ) array_push($list[$top],$obj); else $list[$top]= $obj;
      }
      elseif ( $tag[0]=='/' ) {
        $i++;
        $tag= substr($tag,1);
        // snížení zásobníku
//                                                         debug($list,"$tag/$top");
        if ( $stack[$top]!=$tag ) list_error("očekávalo se '{$stack[$top]}' místo '$tag'");
        $obj= (object)array('tg'=>$tag,'txt'=>array_pop($list));
        if ( $attr= $attrs[$top] ) $obj->atr= $attr;
//                                                         debug($obj,'obj');
        $top--;
        if ( is_array($list[$top]) ) array_push($list[$top],$obj); else $list[$top]= $obj;
//                                                         debug($list,$i);
      }
      else {
        $stack[++$top]= $tag;
        $attr= $parts[++$i];
        array_push($list,array(''));
        if ( $attr=='>' ) {
          $attrs[$top]= '';
        }
        else {
          $attrs[$top]= $attr;
          $i++;
        }
      }
    }
    else {
//                                                         debug($parts[$i],'$parts[$i]');
      list_out($parts[$i]);
    }
  }
//                                                         debug($stack,'stack');
//                                                         debug($attrs,'attrs');
//                                                         debug($list,'result');
  return $list;
}
# ================================================================================================== RTF
# -------------------------------------------------------------------------------------------------- file_rtf
# funkce obalí text do rtf a případně zapíše do souboru
function file_rtf($text) {
                                                display("file_rtf($text)");
  $ok= "zápis nebyl proveden";
  $text= iconv("utf-8","windows-1250",$text);
  $rtf= <<<__END
{\\rtf1 \\ansi \\ansicpg1250 \\deff0 \\deflang1029 \\ftnnar \\ftnbj
{\\fonttbl
{\\f0 \\froman Times New Roman CE;}
{\\f1 \\fswiss Arial CE;}}
$text
}
__END
    ;
  return $rtf;
}
# -------------------------------------------------------------------------------------------------- list2rtf
function list2rtf ($list,$root=1) {
                                                display("list2rtf()"); return;
  global $list_trace, $list_br, $list_ch, $bc_style, $list_trace_struct, $count_char;
  $rtf= '';
  if ( $root ) {
    $list_br= false;
    $list_ch= 0;
    $count_char= 0;
  }
  if ( is_string($list) || is_numeric($list)  )
    $rtf.= $list;
  elseif ( is_array($list) )
    foreach ($list as $elem)
      $rtf.= list2rtf($elem,0);
  else switch ( $tag= $list->tg ) {
  case 'h':
    $n= 24+4*(6-$list->n);
    $rtf.= "\n{\\par\\ql\\sl500\\f1\\fs$n\\b ".trim(list2rtf($list->txt,0))."}";
    break;
  case 'b':
  case 'i':
    $rtf.= "{\\$tag ".list2rtf($list->txt,0)."}";
    break;
  case 'p':
    $rtf.= "\n{\\par\\ql\\sl500\\li300\\fs24 ".list2rtf($list->txt,0)."}";
    break;
  case 'n':
    $rtf.= "\n{\\footnote ".list2rtf($list->txt,0)."{\\par poznamka}}";
    break;
  default:
    $rtf.= "\n{\\par\\ql\\sl500\\li300\\fs24 ".list2rtf($list->txt,0)."}";
    break;
  }
  return $rtf;
}
# ================================================================================================== LIST
# list :: array ( elem, elem, ...)
# elem :: string | object ( tg:id [,n:i] [,txt:list] ) | list           (object)array('td'=>id, ...)
# tg   :: 'h' | 'p' | 'br' | 'b' | 'i'
#         'endnote'
# -------------------------------------------------------------------------------------------------- list_wiki
# funkce transformuje wiki-soubor do list-struktury
function list_wiki($wname,$dir='wiki/',$file=null,$include=0,$trace=0,$out_notes=1) {
                                                display("list_wiki($wname,$dir,$include,$trace)");
  // start
  global $list, $list_dir, $list_max_depth, $list_depth, $notes, $txt, $htm;
  $list_dir= $dir;
  $list_max_depth= $include;
  $list_depth= 0;
  $list= array();
  $notes= array();
  $text= list_file($wname);
  $text= str_replace("\r",'',$text);
//                                                         display("text:".rawurlencode($text));
  $txt= list_text("\n$text\n",$list);
                                                        display($txt);
  return $list;
}
# -------------------------------------------------------------------------------------------------- list_file
# funkce otevře wiki-soubor včetně vnořených a vrátí výsledný text
function list_file($wname) {
  global $list_dir, $list_max_depth, $list_depth, $htm;
                                                        display("list_file($wname)");
  $text= " ???$wname/$list_depth??? ";
  $fname= "$list_dir$wname.wiki";
  if ( file_exists($fname) ) {
    $text= file_get_contents($fname);
    $text= preg_replace("/\[\<([^\]]*)\]/",'',"\n$text\n");
//                                                         $htm.= $text;
//                                                         display("text1:$text");
    if ( $list_depth < $list_max_depth ) {
      $list_depth++;
      $text= preg_replace_callback("/\[\^([^\]]*)\]/",'list_include',$text);
    }
//                                                         display("text2:$text");
  }
  else fce_error("file $fname nelze otevřít");
  return $text;
}
# -------------------------------------------------------------------------------------------------- list_include
# zpracuje include
function list_include ($m) {
//                                                           display("list_include({$m[0]})");
//                                                           debug($m);
  $iname= strtr(rawurlencode($m[1]),array('%28'=>'(','%29'=>')'));
  $text= list_file($iname);
  return $text;
}

# -------------------------------------------------------------------------------------------------- list_error
function list_error($text,$i=0,$stack=NULL) {
  global $error, $error_line;
//   debug($stack,$i);
//   fce_error($text);
  $error.= "<br>$error_line: $text";
}
# -------------------------------------------------------------------------------------------------- list_text
# zpracuje řádek
function list_text($text,&$list,$p=1) {
  global $list, $top, $notes, $error, $error_line, $list_trace_stack;
//                                                 display("list_text($text)");
  $stack= array('eof','p0'); $list[0]= $list[0]= array(''); $top= 0;
  $error= '';
  $error_line= 0;
  $xxx= '';
  $chapter= 0;
  $state= 'normal';
  for ($i= 0; $i<mb_strlen($text); $i++) {
    $x= mb_substr($text,$i,1);
    if ( $x=="\n" ) $error_line++;
    $xx= mb_substr($text,$i,2);
                                                if ( $list_trace_stack ) display("$i $top ". " S:{$stack[$top-4]} {$stack[$top-3]} {$stack[$top-2]} {$stack[$top-1]} {$stack[$top]}". " I:".strtr(rawurlencode(mb_substr($text,$i,1)      .' '.mb_substr($text,$i+1,1).' '.mb_substr($text,$i+2,1).' '.mb_substr($text,$i+3,1)),array('%0A'=>'\n','%7C'=>'|','%20'=>' ','%5B'=>'[','%5D'=>']')));
    // hlídání chyb
    if ( $list_trace_stack && $stack[$top][0]=='p' && $stack[$top-1][0]=='p' ) { $xxx.= "PP"; break; }
    // ukončení řádkových tagů
    if ( $x=="\n" ) {
      $stop= $stack[$top];
      if ( ($stack[$top][0]=='o' && $xx!="\n#") || ($stack[$top][0]=='u' && $xx!="\n-") ) {
        $top--;
        array_push($list[$top],(object)array('tg'=>$stop[0],'n'=>$stop[1],'txt'=>array_pop($list)));
        $top--;
        array_push($list[$top],(object)array('tg'=>$stop[0].'l','txt'=>array_pop($list)));
        $xxx.= '}}';
      }
      $stop= $stack[$top];
      if ( $stack[$top][0]=='h' ) {
        $top--;
        if ( !is_array($list[$top]) ) $error.= "?{$list[$top]}";
        $n= substr($stop,1);
        $obj= (object)array('tg'=>'h','n'=>$n,'txt'=>array_pop($list));
        if ( $n==1 ) $obj->ch= $chapter;
        array_push($list[$top],$obj);
        $xxx.= '}';
      }
      if ( $stack[$top][0]=='o' || $stack[$top][0]=='u' ) {
        $top--;
        if ( !is_array($list[$top]) ) $error.= "?{$list[$top]}";
        array_push($list[$top],(object)array('tg'=>$stop[0],'n'=>$stop[1],'txt'=>array_pop($list)));
        $xxx.= "}";
      }
      if ( $stack[$top]=='ind' || $stack[$top]=='p' || $stack[$top]=='br' ) {
        $top--;
        array_push($list[$top],(object)array('tg'=>$stop,'txt'=>array_pop($list)));
        $xxx.= '}';
      }
      if ( $stack[$top]=='td' ) {                       // konec řádku tabulky
        $top--;
        array_push($list[$top],(object)array('tg'=>'td','txt'=>array_pop($list)));
        $top--;
        array_push($list[$top],(object)array('tg'=>'tr','txt'=>array_pop($list)));
        $xxx.= '}}';
        if ( $xx!="\n|" && $xx!="\r|" ) {              // konec tabulky
          $top--;
          array_push($list[$top],(object)array('tg'=>'tab','txt'=>array_pop($list)));
          $xxx.= '}';
        }
      }
      $stop= $stack[$top];
      if ( $stop[0]=='p' ) {
        $top--;
        if ( !is_array($list[$top]) ) $error.= "?{$list[$top]}";
        array_push($list[$top],(object)array('tg'=>'p','n'=>$stop[1],'txt'=>array_pop($list)));
        $xxx.= "($i)}";
      }
    }
    // dvouznakové oddělovače
    switch ( $xx ) {
    case '||':
    case '**':
    case '==':
      $xxx.= $x; $i++;
      list_out($x);
      break;
    case '__':
      if ( mb_substr($text,$i+1,1)=='_' ) {
        $i+= 2;
        array_push($list[$top],(object)array('tg'=>'hr'));
      }
      else {
        $xxx.= $x; $i++;
        list_out($x);
      }
      break;
    case '[$':
      $ref= '';
      $atr= false;
      for ($k= $i+2; mb_substr($text,$k,1)!="\n"; $k++ ) {
        $ch= mb_substr($text,$k,1);
        if ( $ch==']' ) break;
        if ( $ch=='$' ) continue;
        if ( $ch=='#' ) { $atr= ''; continue; }
        if ( $atr===false )
          $ref.= $ch;
        else
          $atr.= $ch;
      }
      $i= $k; $xxx.= '{img:'."$ref#$atr}";
      array_push($list[$top],(object)array('tg'=>'img','txt'=>$ref,'atr'=>$atr));
      break;
    case '[|':
      $stack[++$top]= '|]'; $i++; $xxx.= '{n:';
      array_push($list,array(''));
      $stack[++$top]= 'p0'; $xxx.= '{p0:';
      array_push($list,array(''));
      break;
    case '|]':
      if ( $stack[$top]!='p0' ) list_error("očekávalo se '{$stack[$top]}' místo '$xx'",$i,$stack);
      $top--; $xxx.= '}'; $i++;
      array_push($list[$top],(object)array('tg'=>'p','n'=>-1,'txt'=>array_pop($list)));
      if ( $stack[$top]!='|]' ) list_error("očekávalo se '{$stack[$top]}' místo '$xx'",$i,$stack);
      $top--;
      $notes[]= (object)array('tg'=>'nt','txt'=>array_pop($list),'ch'=>$chapter,'n'=>count($notes)+1);
      array_push($list[$top],(object)array('tg'=>'n','txt'=>count($notes)));
      break;
    case "\n+":
    case "\r+":
      $i0= $i;
      while ( $text[$i+1]=='+' ) $i++;
      $n= $i-$i0;
      if ( $n==1 ) $chapter++;
      $xxx.= '{h'.$n.':';
      $stack[++$top]= 'h'.$n;
      array_push($list,array(''));
      break;
    case "\n-":
    case "\r-":
      if ( $stack[$top]!='ul' ) {
        // začátek
        $stack[++$top]= 'ul';
        array_push($list,array(''));
        $xxx.= '{ul:';
      }
      // pokračování
      $n= $i;
      while ( $text[$i+1]=='-' ) $i++;
      $xxx.= '{u'.($i-$n).':';
      $stack[++$top]= 'u'.($i-$n);
      array_push($list,array(''));
      break;
    case "\n#":
    case "\r#":
      if ( $stack[$top]!='ol' ) {
        // začátek
        $stack[++$top]= 'ol';
        array_push($list,array(''));
        $xxx.= '{ol:';
      }
      // pokračování
      $n= $i;
      while ( $text[$i+1]=='#' ) $i++;
      $xxx.= '{o'.($i-$n).':';
      $stack[++$top]= 'o'.($i-$n);
      array_push($list,array(''));
      break;
    case "\n>":
    case "\r>":
      $stack[++$top]= 'ind';
      $xxx.= '{ind:';
      $i++;
      array_push($list,array(''));
      break;
    case "\n|":
    case "\r|":
      if ( $stack[$top]!='tab' ) {
        // začátek
        $stack[++$top]= 'tab';
        array_push($list,array(''));
        $xxx.= '{tab:';
      }
      $stack[++$top]= 'tr';
      array_push($list,array(''));
      $xxx.= '{tr:';
      $stack[++$top]= 'td';
      array_push($list,array(''));
      $xxx.= '{td:';
      $i++;
      break;
    default:
      // jednoduché oddělovače
      switch ( $x ) {
      case "\r":
        break;
      case "\n":
        $n= $i;
        while ( $text[$i+1]=="\n" ) $i++;
        $stack[++$top]= 'p'.($i-$n);
        if ( ($i-$n) > 0 ) { $i--; $text[$i+1]= "\r"; }
        array_push($list,array(''));
          $xxx.= '{p:';
        break;
      case '_':
        if ( $state=='note' ) {
          while ( $top && $stack[$top]!='|]' ) $top--;
          $xxx.= '}';
          array_push($list[$top],(object)array('tg'=>'p','n'=>-1,'txt'=>array_pop($list)));
          if ( $stack[$top]!='|]' ) list_error("očekávalo se '{$stack[$top]}' místo '$xx'",$i,$stack);
          $top--;
          $notes[]= (object)array('tg'=>'nt','txt'=>array_pop($list),'ch'=>$chapter,'n'=>count($notes)+1);
          array_push($list[$top],(object)array('tg'=>'n','txt'=>count($notes)));
          $state= 'normal';
        }
        else {
          $stack[++$top]= '|]'; $xxx.= '{n:';
          array_push($list,array(''));
          $stack[++$top]= 'p0'; $xxx.= '{p0:';
          array_push($list,array(''));
          $state= 'note';
        }
        break;
      case '*':
      case '=':
        if ( $stack[$top]==$x ) {
          $top--;
          array_push($list[$top],(object)array('tg'=>($x=='*'?'b':'i'),'txt'=>array_pop($list)));
          $xxx.= '}';
        }
        else {
          $stack[++$top]= $x;
          array_push($list,array(''));
          $xxx.= '{'.($x=='*'?'b':'i').':';
        }
        break;
      case '[':
        $stack[++$top]= ']';
        array_push($list,array(''));
        $xxx.= '{r:';
        break;
      case ']':
        if ( $stack[$top]!=']' ) list_error("očekávalo se '{$stack[$top]}' místo '$x'",$i,$stack);
        $top--; $xxx.= '}';
        array_push($list[$top],(object)array('tg'=>'r','txt'=>array_pop($list)));
        break;
      case '|':
        if ( $stack[$top]=='td' ) {
          $xxx.= '}{td:';
          $top--;
          array_push($list[$top],(object)array('tg'=>'td','txt'=>array_pop($list)));
          $stack[++$top]= 'td';
          array_push($list,array(''));
        }
        else
          $xxx.= '|';
        break;
      default:
        $xxx.= $x;
        list_out($x);
        break;
      }
    }
  }
  if ( $top && $stack[$top][0]=='p' ) { $top--; $xxx.= '}'; }
  if ( $top ) {
    for ( ;$top>0;$top--) {
      $error.= " '{$stack[$top]}'";
    }
  }
//                                                                 debug($list,'OUT');
  return $xxx;
}
# -------------------------------------------------------------------------------------------------- list_add
function list_out($x) {
  global $list, $top;
  $last= count($list[$top])-1;
//                                                                 debug($list,"list[$top][$last]");
  if ( is_array($list[$top]) ) {
    if ( is_string($list[$top][$last]) )
      $list[$top][$last].= $x;
    else
      $list[$top][]= $x;
  }
  else {
    $list[$top]= array($list[$top],$x);
  }
}


# ================================================================================================== OBSOLETE
# -------------------------------------------------------------------------------------------------- dop_report_data
// # načte data dopisu pro dané členy
// function dop_report_data($ids_clen='',$is_dary=false) {
//                                                 display("dop_report_data($ids_clen,$dary)");
//   $result= array(); $n= 0;
//   if ( !$ids_clen ) $ids_clen= $_SESSION['user_memory_cond'];
//   // projdi layout daného vzoru
//   $qry= "SELECT * FROM clen WHERE FIND_IN_SET(id_clen,'$ids_clen')";
//   $res= pdo_qry($qry);
//   while ( $res && $c= pdo_fetch_object($res) ) {
//     // načtení případné sardinky
//     $s= null;
//     if ( $c->sardinka ) {
//       $qry1= "SELECT * FROM clen WHERE id_clen={$c->sardinka}";
//       $res1= pdo_qry($qry1);
//       $s= pdo_fetch_object($res);
//     }
//     $result[$n]= array();
//     // obecné údaje
//     $result[$n]['cislo']=  $c->id_clen;
//     // údaje do dopisu
//     $result[$n]['ps']= clen_data($c,'ps');
//     // dary, pokud jsou požadovány
//     if ( $is_dary )
//       $result[$n]['dary']= clen_data($c,'dary');
//     // adresa člena pro potvrzení tzn. se zohledněním "dary na"
//     $result[$n]['adresa_darce']=  "???";
//     // adresa poš tovní
//     $result[$n]['kod_poslani']= clen_data($s ? $s : $c,'kod_poslani');
//     $result[$n]['adresa_pos tovni']=  $s
//       ? clen_data($s,'jmeno2').'<br>(pro '.clen_data($c,'jmeno2').')<br>'.clen_data($s,'adresa2')
//       : clen_data($c,'jmeno2').'<br>'.clen_data($c,'adresa2');
//     // údaje pro členskou legitimaci
//     $result[$n]['clenske_cislo']=  "Členské číslo: {$c->id_clen}";
//     $result[$n]['rodne_cislo']= $c->osoba && $c->rodcis ? "Rodné číslo: ".clen_data($c,'rodcis') : '';
//     $result[$n]['jmeno_clena']= clen_data($c,'jmeno1');
//     $result[$n]['adresa_clena']= clen_data($c,'adresa1');
//     $n++;
//   }
// //                                                         debug($result,'dop_data');
//   $_SESSION['user_memory_pars']= $result;
//   return $result;
// }

?>
