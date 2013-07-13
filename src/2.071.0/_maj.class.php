<?php
// *************************************************************************************************************
// CLASSE DE GESTION DES MISES A JOUR SYSTEME 
// *************************************************************************************************************


class maj_serveur {
	var $version_before_maj;
	var $version_after_maj;

	var $ftp_id_connect;					// Identifiant de la connexion FTP
	var $tmp_files_dir;						// Dossier local temporaire de t�l�chargement des fichiers de mise � jour
	var $ftp_files_dir;						// Dossier FTP de t�l�chargement des fichiers de mise � jour

	var $xml_liste_fichiers;			// fichier xml distant listant les fichiers � downloader
	var $install_files;						// liste des fichiers � t�l�charger
	var $install_dirs;						// liste des dossiers � t�l�charger
	var $install_infos;						// liste des infos de t�l�chargement
	var $derniereBaliseRencontree;
	var $download_infos_file;			//fichier de progression de la maj
	
	var $parseurXML;
	
	var $do_not_synchro_dirs;			// Tableau des dossiers qui ne peuvent etre synchronis�s

	var $break_point_file;				// Nom du fichier contenant les Break Points
	var $last_break_point;				// Dernier Break Point encas de restauration d'une MAJ


function __construct ($version_after_maj) {
	global $_SERVER; 
	global $CONFIG_DIR;
	global $DIR;
	global $MAJ_SERVEUR;

	// Informations sur la mise � jour
	$this->version_before_maj = $_SERVER['VERSION'];
	$this->version_after_maj 	= $version_after_maj; // Conversion en nombre

	$texte = "<b>MISE A JOUR DE LMB v".$this->version_before_maj." vers v".$this->version_after_maj."</b>";
	$GLOBALS['_INFOS']['maj_actions'][] = $texte;

	// Initialisation des variables
	$this->tmp_files_dir = $DIR."echange_lmb/maj_lmb_".$this->version_after_maj."/";
	$this->ftp_files_dir = $MAJ_SERVEUR['ftp_racine']."maj-v".$this->version_after_maj."/";
	$this->xml_liste_fichiers = "lmb_liste_fichiers.xml";
	$this->install_files = array();
	$this->install_dirs = array();
	$this->install_infos = array();
	$this->derniereBaliseRencontree = "";
	$this->download_infos_file = "lmb_download_state.tmp";
	$this->do_not_synchro_dirs = array(); //($CONFIG_DIR);

	// Recherche d'un �ventuel Break Point (Afin de ne pas r�p�ter une �tape de la mise � jour)
	$this->last_break_point = 0;
	$this->break_point_file = $DIR."echange_lmb/v".$this->version_after_maj."_break_points.tmp";

	if (is_file($this->break_point_file)) {
		$break_points = file ($this->break_point_file);
		$this->last_break_point = $break_points [count($break_points)-1] * 1;
		$GLOBALS['_INFOS']['maj_actions'][] = "<i>R�cup�ration de la mise � jour au point n�".$this->last_break_point."</i>";
	}

	return true;
}



// *************************************************************************************************************
// Fonction de gestion des �tapes de mise � jour
// *************************************************************************************************************
// Permet de g�rer une erreur lors de la mise � jour, afin de reprendre � cette �tape lors d'une tentative ult�rieure
function set_break_point($i) {
	$GLOBALS['_INFOS']['maj_actions'][] = "<b>Point de restauration n� ".$i." cr��</b>";
	$file_id = fopen ($this->break_point_file, "a");
	fwrite ($file_id, $i."\n");
	fclose ($file_id);
	$this->last_break_point = $i;
}


function unset_break_point() {
	unlink ($this->break_point_file);
}

// *************************************************************************************************************
// T�l�chargement des fichiers n�cessaires � la mise � jour
// *************************************************************************************************************
function get_maj_files ($all) {
	global $DIR;
	global $MAJ_SERVEUR;
	$MS = &$MAJ_SERVEUR;

	// *************************************************
	// Mise en place d'une connexion FTP basique
	$GLOBALS['_INFOS']['maj_actions'][] = "Connexion au serveur FTP";
	$this->ftp_id_connect = ftp_connect($MS['ftp_server']); 
	$login_result = ftp_login($this->ftp_id_connect, $MS['ftp_user'], $MS['ftp_pass']);

	// V�rification de la connexion
	if ((!$this->ftp_id_connect) || (!$login_result)) {
		$error = "La connexion FTP a �chou� : ".$MS['ftp_server']." / ".$MS['ftp_user'].""; 
		alerte_dev ($error);
		exit; 
	}
	ftp_pasv($this->ftp_id_connect, true);
	// T�l�chargement du script de mise � jour (dossier complet)
	$GLOBALS['_INFOS']['maj_actions'][] = "<b>T�l�chargement des fichiers de mise � jour</b>";
	$this->ftp_download_dir ();

	// Fermeture du flux FTP
	ftp_close($this->ftp_id_connect); 
}


// *************************************************************************************************************
// Fonctions FTP
// *************************************************************************************************************
// Upload un r�pertoire complet
function ftp_download_dir () {

	if (!is_dir($this->tmp_files_dir)) { mkdir($this->tmp_files_dir);}
	//fichier de progression
	$this->make_download_state (1, "Mise &agrave; jour vers version ".$this->version_after_maj." en cours", "T&eacute;l&eacute;chargement des fichiers", "" );
	
	//chargement du fichier xml listant les fichiers et dossier � t�l�charger
	set_time_limit(300);
	ftp_get ($this->ftp_id_connect, $this->tmp_files_dir.$this->xml_liste_fichiers, $this->ftp_files_dir.$this->xml_liste_fichiers, FTP_BINARY);

	//chargement du fichier de maj
	ftp_get ($this->ftp_id_connect, $this->tmp_files_dir."maj.php", $this->ftp_files_dir."maj.php", FTP_BINARY);
	
	//lecture du fichier
	$this->read_xml_file();
	
	$downloaded = 0;
	$total_size = $this->install_infos[0]['TOTAL_SIZE'];
	
	// Cr�ation de l'arborescence des r�pertoires
	if (!is_dir($this->tmp_files_dir."files/")) { mkdir($this->tmp_files_dir."files/");}
	$dir_list = $this->install_dirs;
	foreach ($dir_list as $dir) {
		if (!is_dir($this->tmp_files_dir."files/".$dir['SRC'])) {@mkdir ($this->tmp_files_dir."files/".$dir['SRC']);}
		$GLOBALS['_INFOS']['maj_actions'][] = "<b>Dossier</b> : ".$this->tmp_files_dir."files/".$dir['SRC']."<br>";
	}
	
	// T�l�chargement des fichiers 1 � 1
	$files_list = $this->install_files;
	foreach ($files_list as $file) {
		set_time_limit(300);

		// T�l�chargement du fichier
		ftp_get ($this->ftp_id_connect, $this->tmp_files_dir."files/".$file['SRC'], $this->ftp_files_dir."files/".$file['SRC'], FTP_BINARY);
		$GLOBALS['_INFOS']['maj_actions'][] = "<b>FICHIER</b> : ".$this->tmp_files_dir."files/".$file['SRC']."<br>";
		
		// Inscription des informations sur l'�tat du t�l�chargement
		$downloaded 	+= filesize ($this->tmp_files_dir."files/".$file['SRC']);
		$percent = number_format(((90 * $downloaded)/$total_size), 0);
		
		$this->make_download_state ($percent, "Mise &agrave; jour vers version ".$this->version_after_maj." en cours", "T&eacute;l&eacute;chargement des fichiers", "T&eacute;l&eacute;chargement : ".number_format($downloaded/1048576,2)." MB sur ".number_format($total_size/1048576,2)." MB");
	}
	//Fin du t�l�chargement des fichiers
	
	//V�rification au moins une fois du bon t�l�chargement des fichiers
	foreach ($files_list as $file) {
		set_time_limit(300);
		//le fichier
		if (!file_exists ($this->tmp_files_dir."files/".$file['SRC'])) {
			// T�l�chargement du fichier
			ftp_get ($this->ftp_id_connect, $this->tmp_files_dir."files/".$file['SRC'], $this->ftp_files_dir."files/".$file['SRC'], FTP_BINARY);
		}
	}
	
	//relance de la v�rification
	$liste_missing_files = array();
	foreach ($files_list as $file) {
		set_time_limit(300);
		//le fichier
		if (!file_exists ($this->tmp_files_dir."files/".$file['SRC'])) {
			$liste_missing_files[] = $file['SRC'];
		}
	}
	if (count($liste_missing_files)) {
		$this->make_download_state (1, "Mise &agrave; jour interrompue ! ", "Des fichiers sont manquants ", "Actualisez la page de mise &agrave; jour pour r&eacute;essayer " );
		exit;
	}
	
}




// V�rification des fichiers pr�sents pour une mise � jour manuel
function check_files () {

	if (!is_dir($this->tmp_files_dir)) { mkdir($this->tmp_files_dir);}
	//fichier de progression
	$this->make_download_state (1, "Mise &agrave; jour vers version ".$this->version_after_maj." en cours", "V&eacute;rification des fichiers", "" );
	
	//chargement du fichier xml listant les fichiers et dossier � t�l�charger
	set_time_limit(300);
	//lecture du fichier
	$this->read_xml_file();
	// Cr�ation de l'arborescence des r�pertoires
	if (!is_dir($this->tmp_files_dir."files/")) { mkdir($this->tmp_files_dir."files/");}
	
	// v�rification des fichiers 1 � 1
	$files_list = $this->install_files;
	//relance de la v�rification
	$liste_missing_files = array();
	foreach ($files_list as $file) {
		set_time_limit(300);
		//le fichier
		if (!file_exists ($this->tmp_files_dir."files/".$file['SRC'])) {
			$liste_missing_files[] = $file['SRC'];
		}
	}
	if (count($liste_missing_files)) {
		$this->make_download_state (1, "Mise &agrave; jour interrompue ! ", "Des fichiers sont manquants ", "Veuillez v&eacute;rifier que l'ensemble des fichiers &agrave; installer sont pr&eacute;sent dans le dossier echange_lmb/maj_lmb_".$this->version_after_maj."/files/ " );
		exit;
	}
	
}


// Lit le fichier d'information sur le code source.
function read_xml_file () {
	
	// Cr�ation du parseur XML
	$this->parseurXML = xml_parser_create("ISO-8859-1");

	//This is the RIGHT WAY to set everything inside the object.
	xml_set_object ( $this->parseurXML, $this );
	
	// Nom des fonctions � appeler lorsque des balises ouvrantes ou fermantes sont rencontr�es
	xml_set_element_handler($this->parseurXML, "opentag" , "closetag");

	// Nom de la fonction � appeler lorsque du texte est rencontr�
	xml_set_character_data_handler($this->parseurXML, "texttag");

	// Ouverture du fichier
	$fp = fopen($this->tmp_files_dir.$this->xml_liste_fichiers, "r");
	if (!$fp) alerte_dev ("Impossible d'ouvrir le fichier XML");

	// Lecture ligne par ligne
	while ( $ligneXML = fgets($fp, 1024)) {
		// Analyse de la ligne
		// REM: feof($fp) retourne TRUE s'il s'agit de la derni�re ligne du fichier.
		xml_parse($this->parseurXML, $ligneXML, feof($fp)) or alerte_dev("Fichier incorrect sur LM.fr");
	}

	xml_parser_free($this->parseurXML);
	fclose($fp);

	return true;
}

// Fontion de lecture des balises ouvrantes
function opentag($parseur, $nomBalise, $tableauAttributs) {
	//$this->$derniereBaliseRencontree = $nomBalise;

	switch ($nomBalise) {
			case "DIR": 
					$this->install_dirs[] = $tableauAttributs;
					break;
			case "FILE": 
					$this->install_files[] = $tableauAttributs;
					break;
			case "INSTALL": 
					$this->install_infos[] = $tableauAttributs;
					break;
	} 
}

// Fonction de traitement des balises fermantes
function closetag($parseur, $nomBalise) {
	//$this->derniereBaliseRencontree = "";
}

//Fonction de traitement du texte
// qui est appel�e par le "parseur" (non utilis�e car pas de texte entre les balises)
function texttag($parseur, $texte)
{
}

// *********************************************************************************************************
// Fonctions de cr�ation du fichier d'�tat de t�l�chargement
// *********************************************************************************************************
public function make_download_state($percent, $majetat, $majinfos, $majinfos_more) {
	/******************************
	* Structure du fichier :
	avancement de la maj (en %)
	texte appliqu� pour indiqu� l'�tat de la maj
	texte indiquant le type de maj en cours
	texte compl�mentaire
	*/
	$entete_download_state  = $percent."\n";			// pourcentage de la maj
	$entete_download_state .= $majetat."\n";			// majetat (texte)
	$entete_download_state .= $majinfos."\n";	// type de maj en cours
	$entete_download_state .= $majinfos_more."\n";				// infos compl�mentaires
	
	if (is_dir($this->tmp_files_dir)) {
	$infos_file = fopen ($this->tmp_files_dir.$this->download_infos_file, "w");
	fwrite ($infos_file, $entete_download_state);
	fclose($infos_file);
	}
	
	return true;
}





// Vide le r�pertoire temporaire FTP
function flush_tmp_files() {
	$GLOBALS['_INFOS']['maj_actions'][] = "Suppression des fichiers de mise � jour";
	$this->rmdir ($this->tmp_files_dir);
}


function rmdir ($dir) {
	$files = scandir($dir);
	if (count($files) == 2) {
		rmdir($dir);
		return true;
	}
	
	for ($i=2; $i<count($files); $i++) {
		if (is_dir ($dir."/".$files[$i])) { 
			$this->rmdir($dir.$files[$i]."/");
		}
		else {
			unlink ($dir.$files[$i]); 
		}
	}
	rmdir($dir);
	return true;
}

function rmfile ($file) {
  unlink ($file); 
}

function create_config_file () {
	global $CONFIG_DIR;
	//@TODO
}

function delete_depreciated_file () {
	//@TODO
}

// Synchronise les fichiers g�n�raux de LMB avec la mise � jour t�l�charg�e
function synchronise_files () {
	global $DIR;

	$GLOBALS['_INFOS']['maj_actions'][] = "Synchronisation des fichiers recus";

	// Les fichiers sont dans le r�pertoire files/ du r�pertoire temporaire
	$source_dir = $this->tmp_files_dir."files/";
	// Ces fichiers vont etre d�plac�s � la racine
	$dest_dir = $DIR;

	if (!is_dir($source_dir)) {
		$GLOBALS['_INFOS']['maj_actions'][count($GLOBALS['_INFOS']['maj_actions'])-1] = " <i>( Aucun fichier � synchroniser )</i>";
		return false; 
	}

	$this->synchronise_dir($source_dir, $dest_dir);
	return true;
}


// Effectue une copie exacte d'un dossier vers un autre
function synchronise_dir ($source_dir, $dest_dir) {
	$files = scandir($source_dir);

	// Boucle sur les fichiers 
	for ($i=2; $i<count($files); $i++) {
		if (!is_file($source_dir.$files[$i])) { continue; }

		$old_name = $source_dir.$files[$i];
		$new_name = $dest_dir.$files[$i];
		copy ($old_name, $new_name);
	}

	// Boucle sur les dossiers 
	for ($i=2; $i<count($files); $i++) {
		if (!is_dir($source_dir.$files[$i])) { continue; }

		$new_source_dir = $source_dir.$files[$i]."/";
		$new_dest_dir 	= $dest_dir.$files[$i]."/";

		// Protection sp�ciale pour les dossiers qui ne sont jamais synchronis�s
		if (in_array($new_dest_dir, $this->do_not_synchro_dirs)) { continue; }

		// Si il n'existe pas on le cr��
		if (!is_dir($new_dest_dir)) { mkdir ($new_dest_dir); }

		//Synchronisation des sous dossiers
		$this->synchronise_dir($new_source_dir, $new_dest_dir);
	}
}




// *************************************************************************************************************
// Actions sur la base de donn�es
// *************************************************************************************************************
function exec_sql ($query) {
	global $bdd;
	$bdd->exec ($query);

	$GLOBALS['_INFOS']['maj_actions'][] = "Requete effectu�e : <br>".nl2br($query);
}




// *************************************************************************************************************
// Mise � jour d'un fichier de configuration
// *************************************************************************************************************
//fonction maj_configuration_file d�plac�e et modifi�e dans divers.lib.php




// *************************************************************************************************************
// ACTIONS PREDETERMINEES SUR LE SERVEUR (D�marrage, Arret)
// *************************************************************************************************************
// Ferme le serveur pour effectuer la mise � jour tranquillement.
public function stop_serveur () {
	global $CONFIG_DIR;
	maj_configuration_file ("config_serveur.inc.php", "maj_line", "\$_SERVER['ACTIF'] =", "\$_SERVER['ACTIF'] = 0;", $CONFIG_DIR);
	$GLOBALS['_INFOS']['maj_actions'][] = "Arret du serveur";
}

// R�ouvre le serveur pour effectuer la mise � jour tranquillement.
public function start_serveur () {
	global $CONFIG_DIR;
	maj_configuration_file ("config_serveur.inc.php", "maj_line", "\$_SERVER['ACTIF'] =", "\$_SERVER['ACTIF'] = 1;", $CONFIG_DIR);
	$GLOBALS['_INFOS']['maj_actions'][] = "D�marrage du serveur";
}

// Inscrit la nouvelle version du serveur dans le fichier de configuration adequat
public function maj_version () {
	global $CONFIG_DIR;

	$line = "\$_SERVER['VERSION'] = '".$this->version_after_maj."';";
	maj_configuration_file ("config_serveur.inc.php", "maj_line", "\$_SERVER['VERSION'] =", $line, $CONFIG_DIR);
	$GLOBALS['_INFOS']['maj_actions'][] = "D�marrage du serveur";
}


public function show_maj_procedure () {
	foreach ($GLOBALS['_INFOS']['maj_actions'] as $action) {
		echo "<li>".$action."</li>";
	}
}



}

?>