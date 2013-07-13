<?php
// *************************************************************************************************************
// CLASSE REGISSANT LES INFORMATIONS GENERIQUE SUR UN THEME D'AFFICHAGE 
// *************************************************************************************************************

class theme {
	private $id_theme;

	private $id_interface;
	private $lib_theme;
	private $code_theme;

	private $id_langage;
	private $actif;

	private $dir_theme;


function __construct ($id_theme) {
	global $DIR;
	global $bdd;

	$query = "SELECT id_theme, id_interface, lib_theme, code_theme, id_langage, actif
						FROM interfaces_themes
						WHERE id_theme = '".$id_theme."' ";
	$result = $bdd->query ($query);
	$theme = $result->fetchObject();
	
	// Th�me non trouv�
	if (!isset($theme->id_theme)) {
		$erreur = "Tentative de chargement d'un th�me inexistant (ID_THEME = ".$id_theme.")";
		alerte_dev ($erreur);
	}
	
	// Th�me non actif
	if (!$theme->actif) {
		$erreur = "Tentative de chargement d'un th�me non actif (ID_THEME = ".$id_theme.")";
		alerte_dev ($erreur);
	}
	
	$this->id_theme 	= $theme->id_theme;
	$this->id_interface 	= $theme->id_interface;
	$this->lib_theme 	= $theme->lib_theme;
	$this->code_theme = $theme->code_theme;
	$this->id_langage = $theme->id_langage;
	$this->actif 			= $theme->actif;
	
	return true;
}



// *************************************************************************************************************
// Fonctions d'acc�s aux donn�es
// *************************************************************************************************************

// Retourne le r�pertoire du theme
final public function getDir_theme() {
	
	// R�pertoire de ce th�me
	$dir_theme = $_SESSION['interfaces'][$this->id_interface]->getDossier()."themes/".$this->code_theme."/";

	return $dir_theme;
}

// retourne d'identifiant du th�me
final public function getId_theme() {
	return $this->id_theme;
}

// retourne le libell� du th�me
final public function getLib_theme() {
	return $this->lib_theme;
}

// retourne le libell� du th�me
final public function getCode_theme() {
	return $this->code_theme;
}


}
?>