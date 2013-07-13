<?php
// *************************************************************************************************************
// CLASSE REGISSANT LES INFORMATIONS SUR LIENS FAVORIS D'UN COLLABORATEUR 
// *************************************************************************************************************


final class web_link {
	private $id_web_link;

	private $lib_web_link;
	private $url_web_link;
	private $desc_web_link;

function __construct($id_web_link = 0) {
	global $bdd;

	// Controle si le id_web_link est pr�cis�e
	if (!$id_web_link) { return false; }

	// S�lection des informations g�n�rales
	$query = "SELECT lib_web_link, url_web_link, desc_web_link, wl.ref_user, ordre 
						FROM users_web_link wl
						WHERE id_web_link = '".$id_web_link."' ";
	$resultat = $bdd->query ($query);

	// Controle si le id_web_link est trouv�e
	if (!$web_link = $resultat->fetchObject()) { return false; }

	// Attribution des informations � l'objet
	$this->id_web_link 		= $id_web_link;
	$this->lib_web_link		= $web_link->lib_web_link;
	$this->url_web_link		= $web_link->url_web_link;
	$this->desc_web_link	= $web_link->desc_web_link;
	$this->ref_user				= $web_link->ref_user;
	$this->ordre					= $web_link->ordre;

	return true;
}



// *************************************************************************************************************
// FONCTIONS LIEES A LA CREATION D'UN FAVORIS 
// *************************************************************************************************************

public function create_web_link ($lib_web_link, $url_web_link, $desc_web_link) {
	global $bdd;

	// *************************************************
	// Controle des donn�es transmises
	$this->lib_web_link 	= trim($lib_web_link);
	if (!$this->lib_web_link) {
		$GLOBALS['_ALERTES']['bad_lib_web_link'] = 1;
	}
	$this->url_web_link 	= $url_web_link;
	$this->desc_web_link	= $desc_web_link;
	
	// *************************************************
	// Si les valeurs re�ues sont incorrectes
	if (count($GLOBALS['_ALERTES'])) {
		return false;
	}
	
	$ordre = 1;
	// Recherche de l'ordre actuel
	$query = "SELECT MAX(ordre) ordre FROM users_web_link WHERE ref_user = '".$_SESSION['user']->getRef_user()."' ";
	$resultat = $bdd->query ($query);
	if ($tmp = $resultat->fetchObject ()) {$ordre = $tmp->ordre+1;}
	$this->ordre				= $ordre;
	
	
	// *************************************************
	// Insertion dans la base
	$query = "INSERT INTO users_web_link 
							(lib_web_link, url_web_link, desc_web_link, ref_user, ordre)
						VALUES ('".addslashes($this->lib_web_link)."', '".addslashes($this->url_web_link)."', 
										'".addslashes($this->desc_web_link)."', '".$_SESSION['user']->getRef_user()."', ".$this->ordre.") ";
	$bdd->query($query);
	$this->id_web_link = $bdd->lastInsertId();
	$this->ref_user 	 = $_SESSION['user']->getRef_user();

	// *************************************************
	// R�sultat positif de la cr�ation
	$GLOBALS['_INFOS']['Cr�ation_web_link'] = $this->id_web_link;

	return true;
}


// *************************************************************************************************************
// FONCTIONS LIEES A LA MODIFICATION D'UN FAVORIS
// *************************************************************************************************************

public function maj_web_link ($lib_web_link, $url_web_link, $desc_web_link) {
	global $bdd;
	
	// *************************************************
	// Controle des donn�es transmises
	$this->lib_web_link 	= trim($lib_web_link);
	if (!$this->lib_web_link) {
		$GLOBALS['_ALERTES']['bad_lib_web_link'] = 1;
	}
	$this->url_web_link 	= $url_web_link;
	$this->desc_web_link	= $desc_web_link;
	
	
	// *************************************************
	// Si les valeurs re�ues sont incorrectes
	if (count($GLOBALS['_ALERTES'])) {
		return false;
	}

	// *************************************************
	// Mise a jour de la base
	$query = "UPDATE users_web_link 
						SET lib_web_link = '".addslashes($this->lib_web_link)."', url_web_link = '".addslashes($this->url_web_link)."', 
								desc_web_link = '".addslashes($this->desc_web_link)."'
						WHERE id_web_link = '".$this->id_web_link."' ";
	$bdd->exec ($query);

	// *************************************************
	// R�sultat positif de la modification
	return true;
}


// Changement d'ordre d'affichage 
final public function maj_ordre ($new_ordre) {
	global $bdd;

	if (!is_numeric($new_ordre)) {
		$GLOBALS['_ALERTES']['bad_ordre'] = 1;
	}
	
	// *************************************************
	// Si les valeurs re�ues sont incorrectes
	if (count($GLOBALS['_ALERTES'])) {
		return false;
	}


	if ($new_ordre == $this->ordre) { return true; }
	elseif ($new_ordre < $this->ordre) {
		$variation = "+";
		$symbole1 = "<";
		$symbole2 = ">=";
	}
	else {
		$variation = "-";
		$symbole1 = ">";
		$symbole2 = "<=";
	}

	
	// *************************************************
	// MAJ BDD
	$bdd->beginTransaction();
	
	// Mise � jour des autres composants
	$query = "UPDATE users_web_link
						SET ordre = ordre ".$variation." 1
						WHERE ref_user = '".$this->ref_user."' && 
									ordre ".$symbole1." '".$this->ordre."' && ordre ".$symbole2." '".$new_ordre."' ";
	$bdd->exec ($query);
	
	// Mise � jour de ce composant
	$query = "UPDATE users_web_link
						SET ordre = '".$new_ordre."'
						WHERE id_web_link = '".$this->id_web_link."'  ";
	$bdd->exec ($query);
	
	$bdd->commit();	

	// *************************************************
	// R�sultat positif de la modification
	return true;
}


// *************************************************************************************************************
// SUPPRESSION D'UN FAVORIS
// *************************************************************************************************************
public function delete_web_link () {
	global $bdd;

	// *************************************************
	// Controle � effectuer le cas �ch�ant

	// *************************************************
	// Suppression de l'tache
	$query = "DELETE FROM users_web_link 
						WHERE id_web_link = '".$this->id_web_link."' ";
	$bdd->exec ($query);

	// D�calage des ordres
	$query2 = "UPDATE users_web_link
						SET ordre = ordre - 1
						WHERE ref_user = '".$this->ref_user."' && 
									ordre >= '".$this->ordre."'  ";
	$bdd->exec ($query2);
	
	
	unset ($this);
	return true;
}



// *************************************************************************************************************
// FONCTIONS DE CHARGEMENT
// *************************************************************************************************************
// Charge la liste des favoris attribu�es � l'utilisateur en  cours
static function charger_web_link () {
	global $bdd;

	$web_links = array();
	
	$query = "SELECT id_web_link, lib_web_link, url_web_link, desc_web_link, ref_user, ordre 
						FROM users_web_link  
						WHERE ref_user = '".$_SESSION['user']->getRef_user()."'
						ORDER BY ordre ASC ";
	$resultat = $bdd->query ($query);
	while ($tmp = $resultat->fetchObject()) {
		$web_links[] = $tmp;
	}


	return $web_links;
}


// *************************************************************************************************************
// FONCTIONS DE LECTURE DES DONNEES 
// *************************************************************************************************************
function getId_web_link () {
	return $this->id_web_link;
}

function getLib_web_link () {
	return $this->lib_web_link;
}

function getDesc_web_link () {
 return $this->desc_web_link;
}

function getUrl_web_link () {
	return $this->url_web_link;
}

function getRef_user() {
	return $this->re_user;
}


}

?>