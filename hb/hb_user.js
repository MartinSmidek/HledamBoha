// Aplikace HledámBoha
// (c) 2022 Martin Smidek <martin@smidek.eu>
// ------------------------------------------------------------------------------- on_dblclk_copy_to
// Pro elementy při DblClk zkopíruje hodnotu do stejnojmenného elementu formuláře goal
// (funguje pro field, edit)
function on_dblclk_copy_to(form,goal) {
  form= form instanceof Var ? form.value : form;
  goal= goal instanceof Var ? goal.value : goal;
//   $each(form.part,function(field,id) {
  for (let id in form.part) { let field= form.part[id];
    if ( (field instanceof Field || field instanceof FieldList 
      || field instanceof Edit || field instanceof Select)
      && field.DOM_Input ) {
      field.DOM_Input
        .dblclick( function(el) {
          let field2= goal.part[this.id];
          if ( field2 ) {
            field2.set(this.value);
            field2.change();
          }
        }.bind(field));
    }
  }
}
// ----------------------------------------------------------------------------------------- date_gt
// vrátí date2sql(a)>date2sql(b) ? 1 : 0
function date_gt(a,b) {
  return Ezer.fce.date2sql(a)>Ezer.fce.date2sql(b) ? 1 : 0;
}
// ---------------------------------------------------------------------------------------- je_1_2_5
// výběr správného tvaru slova podle množství a tabulky tvarů pro 1,2-4,5 a více
// např. je_1_2_5(dosp,"dospělý,dospělí,dospělých")
function je_1_2_5(kolik,tvary) {
  tvar= tvary.split(',');
  return kolik>4 ? tvar[2] : (
         kolik>1 ? tvar[1] : (
         kolik>0 ? tvar[0] : tvar[2]));
}
// ------------------------------------------------------------------------------------------------- fis_min_typ
//ff: fis.fis_min_typ (a,b)
//      minimum(a,b==6?10:b)
//s: fis
Ezer.fce.fis_min_typ= function (a,b) {
  return Math.min(a,b==6?10:b);
}
// ------------------------------------------------------------------------------------------------- test_rc
//ff: fis.test_rc (rc)
//      otestuje, zda rc je rodné číslo
//      rodná čísla vydaná před rokem 1954 mají pouze třímístný index a nelze je testovat na modulo 11.
//      rodná čísla vydaná po roce 1954 včetně mají čtyřmístný index a lze je testovat na modulo 11.
//      století, kdy bylo rodné číslo vydáno se pozná podle počtu číslic v indexu. Pokud má
//      počáteční číslice např. 19 a trojmístný index, pak jde o rok 1919, pokud by měl
//      čtyřmístný index, pak se jedná o rok 2019.
//s: fis
Ezer.fce.test_rc= function (rc) {
  return rc.length==10 || rc.length==9 ? 1 : 0;
}
// ------------------------------------------------------------------------------------------------- klub_prevod2clen
//ff: fis.klub_prevod2clen (vsym)
//      z variabilního symbolu vsym odvodí dotaz na člena
//      predanych 10 cislic je bud clenske nebo rodne cislo nebo nic
//         0000nnnnnn | rrmmddxxxx | 9999nnnnnn případně s prefixem 99999 hromadných darů
//s: fis
Ezer.fce.klub_prevod2clen= function (vsym) {
  // tipni si z tvaru variabilniho symbolu, co to je
  var cond= '';
  if ( vsym.trim() ) {
    if ( vsym.substr(0,4)=="9999" )             // hromadny dar  - 9999xxxxxx
      cond= "id_clen="+vsym.substr(-6);
    else if ( vsym.substr(0,4)=="0000" ) {      // clenske cislo - 0000nnnnnn   && n>9
      if ( parseInt(vsym,10)>9 )
        cond= "id_clen="+vsym.substr(4);
    }
    else if ( Ezer.fce.test_rc(vsym) )          // rodne cislo   - rrmmddxxx(x)
      cond= "rodcis="+vsym;
  }
  return cond;
}
// ------------------------------------------------------------------------------------------------- tab2chart
//ff: fis.tab2chart (id)
//      vymění tabulku id za graf
//s: fis
Ezer.fce.tab2chart= function (id) {
  var graf= $(id);
  if ( graf && graf.toChart ) {
    graf.toChart({
      width: 300,
      height: 300,
      fontsize: 20,
      legend: false,
      colorScheme: 'blue',
      type: 'bars'
    });
  }
  return true;
}
// ------------------------------------------------------------------------------------------------- kat
//ff: fis.make_kat (...)
//     sestaví z parametrů identifikátor kategorie
//     kat= a1+(a2[2])+...+ai[i]+...
//s: fis
Ezer.fce.make_kat= function () {
  var x= arguments[0], a;
  for (var i= 1; i<arguments.length; i++) {
    a= arguments[i];
    x+= a ? a.substr(i+1,1) : '.';
  }
  x= x.replace(/\.*$/g,'');
  return x;
}
// ------------------------------------------------------------------------------------------------- castka_slovy
//ff: fis.castka_slovy (castka [,platidlo,platidla,platidel,drobnych])
//      vyjádří absolutní hodnotu peněžní částky x slovy
//a: castka - částka
//   platidlo - jméno platidla nominativ singuláru, default 'koruna'
//   platidla - jméno platidla nominativ plurálu, default 'koruny'
//   platidel - jméno platidla genitiv plurálu, default 'korun'
//   drobnych - jméno drobnych genitiv plurálu, default 'haléřů'
//s: fis
Ezer.fce.castka_slovy= function (castka,platidlo,platidla,platidel,drobnych) {
  var text= '', x= Math.abs(castka);
  var cele= Math.floor(castka);
  var mena= [platidlo||'koruna',platidla||'koruny',platidel||'korun'];
  var numero= cele.toString();
  if ( numero.length<7 ) {
    var slovnik= [];
        slovnik[0]= ["","jedna","dvě","tři","čtyři","pět","šest","sedm","osm","devět"];
        slovnik[1]= ["","","dvacet","třicet","čtyřicet","padesát","šedesát","sedmdesát","osmdesát","devadesát"];
        slovnik[2]= ["","sto","dvěstě","třista","čtyřista","pětset","šestset","sedmset","osmset","devětset"];
        slovnik[3]= ["tisíc","tisíc","dvatisíce","třitisíce","čtyřitisíce", "pěttisíc","šesttisíc","sedmtisíc","osmtisíc","devěttisíc"];
        slovnik[4]= ["","deset","dvacet","třicet","čtyřicet", "padesát","šedesát","sedmdesát","osmdesát","devadesát"];
        slovnik[5]= ["","sto","dvěstě","třista","čtyřista","pětset","šestset","sedmset","osmset","devětset"];
    var slovnik2= ["deset","jedenáct","dvanáct","třináct","čtrnáct","patnáct","šestnáct","sedmnáct","osmnáct","devatenáct"];
    for (var x= 0; x <= numero.length-1; x++) {
      if ((x==numero.length-2) && (numero.charAt(x)=="1")) {
        text+= slovnik2[numero.charAt(x+1)];
        break;
      }
      else {
        text+= slovnik[numero.length-1-x][numero.charAt(x)];
      }
    }
  }
  else {
    text= "********";
  }
  if ( numero.length>1 && numero[numero.length-2]=='1' ) {
    text+= mena[2];
  }
  else {
    var slovnik3= [2,0,1,1,1,2,2,2,2,2];
    text+= mena[slovnik3[numero[numero.length-1]]];
  }
  var drobne= Math.floor(100*(castka-Math.floor(castka)));
  if ( drobne ) {
    text+= drobne.toString()+(drobnych||'haléřů');
  }
  return text;
}

