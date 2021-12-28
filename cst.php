<?php	
#
#CST:xpastu00
/***********************************************/
/*			Jakub Pastuszek - xpastu00         */
/*				   VUT FIT					   */
/*	  		    CST: C stats    			   */
/*				 Brezen 2015				   */
/*				   cst.php					   */
/***********************************************/

/*** ***/
### Napoveda
/*** ***/
	$help = "###########################################################"
		.	"\nProjekt: CST: C stats\n"
		.	"Autor: Jakub Pastuszek - xpastu00\n"
		.	"\tVUT FIT - Brezen 2015\n"
		.	"Prehled:\n"
		.	"Skript zobrazuje statistiky zdrojovych souboru jazyka C dle standardu ISO C99\n"
		.	"\nParametry:\n"
		.	"--help\tvypise napovedu. Parametr nelze kombinovat s zadnym dalsim parametrem.\n"

		.	"\n--input=fileordir\tzadany vstupni soubor(analyza tohoto souboru) nebo adresar(postupna analyza vsech "
		.	"souboru s priponou jazyka C (.c, .h) v tomto adresari a vsech jeho podadresarich) se zdrojovym kodem v "
		.	"jazyce C. Soubory v kodovani ISO-8859-2. Pokud nebude tento parametr zadan, tak se analyzuji soubory "
		.	"(opet pouze s priponou .c a .h) z aktualniho adresare a vsech jeho podadresaru.\n"

		.	"\n--nosubdir\tprohledavani bude provadeno pouze v zadanem adresari, ale uz ne v jeho podadresarich. "
		.	"Parametr se nesmi kombinovat s pripadem zadani konkretniho souboru pomoci parametru --input.\n"

		.	"\n--output=filename\tzadany textovy vystupni soubor v kodovani ISO-8859-2\n"

		.	"\n-k\tvypise pocet vsech vyskytu klicovych slov (vyskytujicich se mimo poznamky a retezce)\n"

		.	"\n-o\tvypise pocet vyskytu jednoduchych operatoru (nikoliv oddelovacu apod.) mimo poznamky, "
		.	"znakove literaly a retezce v kazdem zdrojovem souboru a celkem.\n"

		.	"\n-i\tvypise pocet vyskytu identifikatoru (mimo poznamky, znakove literaly a retezce) v kazdem "
		.	"zdrojovem souboru a celkem - nezahrnuje klicova slova\n"

		.	"\n-w=pattern\tvyhleda presny textovy retezec pattern ve vsech zdrojovych kodech a vypise "
		.	"pocet neprekryvajicich se vyskytu na soubor i celkem. Jelikoz se nejedna o identifikator ale "
		.	"retezec, hleda se i v poznamkach, makrech, znakovych literalech a retezcich! Toto vyhledavani "
		.	"je case-sensitive.\n"

		.	"\n-c\tvypise celkovy pocet znaku komentaru vcetne uvozujicich znaku komentaru (//, /* a */) "
		.	"na soubor a celkem.\n"

		.	"\n-p\tv kombinaci s predchozimi (az na --help) zpusobi, ze soubory se budou vypisovat bez "
		.	"uplne (absolutni) cesty k souboru.\n"

		.	"\nPriklady:\n"
		.	"\tcst.php -w=ZZ\n"
		.	"\tcst.php --input=dir/ --output=test.out -k --nosubdir\n"
		.	"\tcst.php --input=anydir/file.c -o\n"
		.	"--------------------------------------------------------";

/* Funkce pro obsluhu chyb */
// Parametry: $stream: vystupni souborovy deskriptor
//            $msg: zprava co se vypise
//            $errcode: navratova hodnota programu
	function err($stream, $msg, $errcode) {
		fwrite($stream, $msg);
		fwrite($stream, PHP_EOL);
		exit($errcode);
	}

/*** ***/
### Parametry
/*** ***/
	$shortopts  = "";
	$shortopts .= "w:";   // pozadovana hodnota
	$shortopts .= "koic"; // bez hodnoty
	$shortopts .= "p";    // bez hodnoty
	$shortopts .= "s";    // bez hodnoty

	$longopts  = array(
		"help",     	 // bez hodnoty
		"input:",   	 // pozadovana hodnota
		"nosubdir",      // bez hodnoty
		"output:",       // pozadovana hodnota
	);
	$options = getopt($shortopts, $longopts);

/*** ***/
### Zpracovani parametru
/*** ***/
	//kontrola poctu parametru
	if ( (count($options)+1) < count($argv)) { err(STDERR, 'Unknown param...', 1); }

	//--help : vypis napovedy
	if ( isset($options["help"]) ) {
		count($argv) == 2 ? 
			err(STDOUT, $help, 0) : exit(1);
	}
	//-w=pattern : vyhleda pattern
	$parW = isset($options["w"]) ? (is_array($options["w"]) ? err(STDERR, 'Wrong use of -w param...', 1) : $options["w"]) : FALSE;
	//-k : vypise pocet klicovych slov
	$parK = isset($options["k"]) ? (is_array($options["k"]) ? err(STDERR, 'Wrong use of -k param...', 1) : TRUE) : FALSE;
	//-o : vypise pocet vyskytu jednoduchych operatoru
	$parO = isset($options["o"]) ? (is_array($options["o"]) ? err(STDERR, 'Wrong use of -o param...', 1) : TRUE) : FALSE;						
	//-i : vypise pocet vyskytu identifikatoru - bez klicovych slov
	$parI = isset($options["i"]) ? (is_array($options["i"]) ? err(STDERR, 'Wrong use of -i param...', 1) : TRUE) : FALSE;
	//-c : pocet znaku v komentari
	$parC = isset($options["c"]) ? (is_array($options["c"]) ? err(STDERR, 'Wrong use of -c param...', 1) : TRUE) : FALSE;
	//-p : vypisovani souboru bez absolutnich cest
	$parP = isset($options["p"]) ? (is_array($options["p"]) ? err(STDERR, 'Wrong use of -p param...', 1) : TRUE) : FALSE;
	//-s : rozsireni TER
	$parS = isset($options["s"]) ? (is_array($options["s"]) ? err(STDERR, 'Wrong use of -s param...', 1) : TRUE) : FALSE;
	
	//-w -k -o -i -c : nesmi byt pouzity soucasne
	if ( ( is_string($parW) +  $parK + $parO + $parI + $parC ) != 1 ) err(STDERR, 'Wrong use of short params...', 1);
	
	//--input=fileordir : soubor nebo adresar						
	if ( isset($options["input"]) ) {
		if ( is_array($options["input"]) ) {
			err(STDERR, 'Wrong use of --input param...', 1);
		} else {
			$inputDir = $options["input"];
		}
	} else {
		$inputDir = ".";
	}
	//--output=filename : vystupni soubor					
	if ( isset($options["output"]) ) {
		if ( is_array($options["output"]) ) {
			err(STDERR, 'Wrong use of --output param...', 1);
		} else {
			$outputFile = $options["output"];
		}
	} else {
		$outputFile = STDOUT;
	}
	//--nosubdir : neprohledavat podslozky 					
	if ( isset($options["nosubdir"]) ) {
		if ( is_array($options["nosubdir"]) || is_file($inputDir) ) {
			err(STDERR, 'Wrong use of --nosubdir param...', 1);
		} else {
			$noSubDir = TRUE;
		}
	} else {
		$noSubDir = FALSE;
	}						
	
	if ( is_file($inputDir) && $noSubDir ) { err(STDERR, 'Wrong use of --nosubdir param...', 1); }
	
/*** ***/
### Pomocne funkce
/*** ***/	
/* Vyhledani vsech vzorku v retezci a vraceni celkove delky */	
// Parametry: $pattern: vzorek pro RV
//            $file_cont: soubor s cestou
// Return: celkova delka vsech vyhledanych vzorku
	function searchPatternLength ($pattern, $file_cont) {
		preg_match_all($pattern, $file_cont, $matches);
		$length = 0;
		foreach ( $matches[0] as $that ) {
			$length += strlen($that);	//pricteni delky daneho "podvzorku"
		}
		return $length;
	}	

/* Vyhledani vzorku v retezci a vraceni poctu nalezeni */	
// Parametry: $pattern: vzorek pro RV
//            $file_cont: soubor s cestou
// Return: celkovy pocet vsech vyhledanych vzorku
	function searchPatternCount ($pattern, $file_cont) {
		preg_match_all($pattern, $file_cont, $matches);
		return count($matches[0]);
	}		
	
/* Odstraneni nepotrebnych veci z retezce */	
// Parametry: $pattern: vzorek pro RV
//            $file_cont: soubor s cestou
//			  $space: zpusob nahrazovani nalezenych vzorku 
// Return: upraveny retezec	
	function clean ($pattern, $file_cont, $space) {
		while (1) {
			preg_match($pattern, $file_cont, $matches, PREG_OFFSET_CAPTURE);
			if ( !count($matches) ) { return $file_cont; }	//nenalezen zaden vzorek
			if ($space == 1) {$space="\n";}					
			elseif ($space == 2) { if ($matches[0][1][1] != "*") {$space="\n";} else {$space="";} }	//v pripade blokoveho komentare nevkladam nic
			else {$space="";}
			$file_cont = substr($file_cont, 0, $matches[0][1]) . $space . substr($file_cont, $matches[0][1]+strlen($matches[0][0]));
		}
	}
	
/* Pomocna funkce pro odstraneni nepotrebnych veci z retezce */	
// Parametry: $file_cont: soubor s cestou
//            $makro: mazat makra?
//            $string: mazat retezce?
//            $liter: mazat literaly?
//            $comment: mazat komentare?
// Return: upraveny retezec
	function cleanAll ($file_cont, $makro, $string, $liter, $comment) {

		if ( $string ) {
		/* Vzorek pro nalezeni retezcu */
			$pattern = '/\".*(\\\\\\n.*?)*\"/';	  // \".*(\\\n.*?)*\"
		/* Odstraneni */
			$file_cont = clean ($pattern, $file_cont, 0);
		}
		if ( $liter ) {	
		/* Vzorek pro nalezeni znakovych literalu */
			$pattern = "/'(\\\\\\'|.*?)+'/";										
		/* Odstraneni */
			$file_cont = clean ($pattern, $file_cont, 0);
		}
		if ( $comment ) {
		/* Vzorek pro nalezeni komentaru */
			$pattern =  '/\\/\\/.*?(\\\\\\n.*?)*(\\n|$)|\\/\\*(\\n|.)*?\\*\\//';
		/* Odstraneni */
			$file_cont = clean ($pattern, $file_cont, 2);
		}		
		if ( $makro ) {
		/* Vzorek pro nalezeni maker */
			$pattern = '/(^|\\s|#)#.*?(\\\\\\n.*?)*(\\n|$)/'; // #.*?(\\\n.*?)*(\n|$)
		/* Odstraneni */
			$file_cont = clean ($pattern, $file_cont, 1);
		}
		return $file_cont;
	}

/* Vlozi potrebny pocet mezer do vystupu */
// Parametry: $out_file: vystupni souborovy deskriptor
//            $length: delka nazvu souboru i s cestou
//            $maxLength: nejdelsi nazev souboru i s cestou
	function insertSpaces ($out_file, $length, $maxLength) {
		for ( $i=$length; $i<$maxLength+1; $i++ ) {
			fwrite($out_file, ' ');
		}
	}
	
/*** ***/
### Hlavni funkce
/*** ***/
/* Pocet klicovych slov */
// Parametry: $filename: soubor s cestou
// Return: pocet klicovych slov
	function funK ( $fileName ) {
		$file_cont = file_get_contents( $fileName );
		
		$file_cont = cleanAll ($file_cont, 1, 1, 0, 1);

	/* Vyhledani klicovych slov */
		$count = 0;
		$data_type = array('int', 'long', 'short', 'unsigned', 'signed', 'double', 'char', 'void', 'union', 'float', 'typedef', 'struct');
		$data_separate = array('break', 'continue');
		$data_times = array('while', 'for', 'switch');
		$pattern = '/auto|case|const|default|[\\s^;]do[\\s{]|else|enum|extern|[\\s^;]return[(;\\s$]|'
				.	'goto|[\\s^;]if[(\\s]|register|restrict|sizeof[(\\s]|'
				.	'static|volatile|'
				.	'inline|_Bool|_Complex|_Imaginary';
				
		foreach ( $data_type as $in ) {
			$pattern .= '|(^|[\\s(;,{])' . $in . '(?=[)\\*\\s])'; 		
		}
		foreach ( $data_separate as $in ) {
			$pattern .= '|(^|[\\s;])' . $in . '[;\\s$]'; 		
		}
		foreach ( $data_times as $in ) {
			$pattern .= '|(^|[\\s;])' . $in . '[(\\s]'; 		
		}
		$pattern .=	'/';
		
		$count = searchPatternCount ($pattern, $file_cont);	
		
		return $count;
	}

/* Pocet vyskytu jednoduchych operatoru */
// Parametry: $filename: soubor s cestou
//			  $extent: aktivita prepinace -s
// Return: pocet vyskytu jednoduchych operatoru
	function funO ( $fileName, $extent ) {
		$file_cont = file_get_contents( $fileName );
	
		$file_cont = cleanAll ($file_cont, 1, 1, 1, 1);
		
	/* Spocitani ternarnich operatoru */
		if ($extent) {
			$ext = 0;
			$pattern = '/.*?\?.*?:.*?;/';

			$ext = searchPatternCount ($pattern, $file_cont);	
		}	
			
	/* Vymazani deklarace ukazatele */
		$types = array('float','int','long','const','char','double','short','signed','unsigned','void','_Bool','_Complex','struct','union');
		$pattern = '/(' . implode('|', $types) . ')[\\s]*[*(]+|';
		$pattern .= 'struct[\\s]+[a-zA-Z_]+[a-zA-Z0-9_]*[\\s]*\*+/';
		
		$file_cont = clean ($pattern, $file_cont, 0);
		
	/* Vyhledani operatoru */
		$count = 0;
		$pattern =  '/<<=|>>=|'
				.	'\\+=|-=|\\*=|\\/=|&=|\\|=|\\^=|\\+\\+|--|<<|>>|->|'
				.	'<=|>=|==|!=|\\|\\||&&|'
				.	'([a-zA-Z_]+[a-zA-Z0-9_]*|[\\s,])\\.[a-zA-Z_]+' // . jenom prvek struktury
				.	'|\\*'									// viz vymazani deklarace ukazatele
				.	'|=|(?<![eE])\\+|(?<![eE])-|\\/|%|&|~|\\||\\^|!|<|>/';

		$count = searchPatternCount ($pattern, $file_cont);	
		
		if ($extent) { return $count+$ext; }	//pokud je aktivni prepinac -s
		else { return $count; }
	}

/* Pocet vyskytu identifikatoru - bez klicovych slov */	
// Parametry: $filename: soubor s cestou
// Return: pocet vyskytu identifikatoru
	function funI ( $fileName ) {
		$file_cont = file_get_contents( $fileName );
		
		$file_cont = cleanAll ($file_cont, 1, 1, 1, 1);
	
	/* Vymazani hexa cisel */	
		$pattern = '/(?<=[0-9])[a-zA-Z_]+[a-zA-Z0-9_]*/';
		
		$file_cont = clean ($pattern, $file_cont, 0);
	
	/* Spocteni poctu klicovych slov */
		$minus = funK( $fileName );
		
	/* Vyhledani identifikatoru */
		$count = 0;
		$pattern = '/(^|(?<![0-9]))[a-zA-Z_]+[a-zA-Z0-9_]*/'; // '/(^|(?<![0-9])|(?<!0[Xx]))[a-zA-Z_]+[a-zA-Z0-9_]*/';

		$count = searchPatternCount ($pattern, $file_cont);
	
		return ($count-$minus);
	}
	
/* Vyhleda pattern */	
// Parametry: $filename: soubor s cestou
//            $parW: vyhledavany vzorek
// Return: pocet nalezenych patternu
	function funW ( $fileName, $parW) {
		$counter = 0;
		$length = strlen($parW);
		
		$file_line = explode("\n", file_get_contents( $fileName ));	//nasekani retezce po radcich
		foreach ( $file_line as $line ) {
			$offset = 0;
			while ( ($offset = strpos($line, $parW, $offset)) !== FALSE ) { //dokud nalezne (muze nalezt na indexu 0)
				$counter++;
				$offset += $length;									//zajisteni neprekryvajicich se vyskytu
				if ( $offset > strlen($line) ) { break; }
			}
		}
		
		return $counter;
	}
	
/* Pocet znaku v komentarich */
// Parametry: $filename: soubor s cestou
// Return: pocet znaku v komentarich
	function funC ( $fileName ) {
		$length = 0;

		$file_cont = file_get_contents( $fileName );
	
	/* Vzorek pro nalezeni komentaru */
		$pattern =  '/\\/\\/.*?(\\\\\\n.*?)*(\\n|$)|\\/\\*(\\n|.)*?\\*\\//';
		
		$length = searchPatternLength ($pattern, $file_cont);
		
		return $length;
	}
	
/*** ***/
### Main
/*** ***/
	
	//Vystupni soubor
	if ( $outputFile != STDOUT ) {
		if ( ($out_file = @fopen($outputFile, "w")) === FALSE ) {
			err(STDERR, "Can't open ".$outputFile, 3);
		}
	} else {
		$out_file = STDOUT;
	}
	
	if ( is_file($inputDir) ) {
		try {
			if ( substr($inputDir, 0, 1) != '/') { 
				$inputDir = realpath(__DIR__.'/'.$inputDir);
			}
			if ( $parW ) {
				$value = funW($inputDir, $parW);
			} elseif ( $parC ) {
				$value = funC($inputDir);
			} elseif ( $parK ) {
				$value = funK($inputDir);
			} elseif ( $parO ) {
				$value = funO($inputDir, $parS);
			} elseif ( $parI ) {
				$value = funI($inputDir);
			}
			/* Redukce cesty jen na nazev souboru */
			if ( $parP == TRUE ) {
				$result = explode('/', $inputDir);
				$inputDir = end($result);
				$out[$inputDir] = $value;
			} else {
				$out[$inputDir] = $value;
			}
			$maxLength = (strlen("CELKEM:") > strlen($inputDir)) ? strlen("CELKEM:") : strlen($inputDir);
			$sum = $value;
		} catch (Exception $e) {
			err(STDERR, "Can't open $inputDir", 2);
		} 
	} else {
	//Iterator pres soubory (slozky)
		try {
			if ( $noSubDir == TRUE ) {
				$iterator = new DirectoryIterator($inputDir);
			} else {
				$iterator = new RecursiveIteratorIterator(
								new RecursiveDirectoryIterator($inputDir), 
							RecursiveIteratorIterator::SELF_FIRST);
			}
		} catch (Exception $e) {
			err(STDERR, "Can't open $inputDir", 2);
		}

	//Projdu kazdy iterator a zkontroluju zda to neni ceckovy soubor (.c, .h)
		$maxLength = 0;
		$maxNum = 0;
		$sum = 0;
		$i = 0;
		foreach ( $iterator as $file ) {	
			if ( $file->isFile() ) {
				$fileName = $file->getRealpath();
				if ( !is_readable($fileName) ) { err(STDERR, 'File $fileName couldn\'t be read.', 21); }
				$result = explode('\\', $fileName);
				$result = end($result);
				if ( substr($result,-2) == ".c" || substr($result,-2) == ".h" ) {	
					if ( $parW ) {
						$value = funW($fileName, $parW);
					} elseif ( $parC ) {
						$value = funC($fileName);
					} elseif ( $parK ) {
						$value = funK($fileName);
					} elseif ( $parO ) {
						$value = funO($fileName, $parS);
					} elseif ( $parI ) {
						$value = funI($fileName);
					}
					/* Redukce cesty jen na nazev souboru */
					if ( $parP == TRUE ) {
						$result = explode('/', $fileName);
						$fileName = end($result);
						$out[$fileName] = $value;
					} else {
						$out[$fileName] = $value;
					}
					if ( strlen($fileName) > $maxLength ) { $maxLength = strlen($fileName); }
					$sum += $value;
				}
			}
		}
		if ( strlen("CELKEM:") > $maxLength ) { $maxLength = strlen("CELKEM:"); }
	}

	if ( isset($out) ) {
		/* Serazeni souboru */
		ksort($out);	
		
		//print_r($out);
		foreach ( $out as $key => $item ) {
			fwrite($out_file, $key);
			insertSpaces ( $out_file, strlen($key), $maxLength+strlen($sum)-strlen($item) );
			fwrite($out_file, $item);
			fwrite($out_file, PHP_EOL);
		}
	}
	
	fwrite($out_file, "CELKEM:");
	insertSpaces ( $out_file, strlen("CELKEM:"), $maxLength );
	fwrite($out_file, $sum);
	fwrite($out_file, PHP_EOL);

	exit(0);
?>