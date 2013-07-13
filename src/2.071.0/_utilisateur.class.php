<?php
// *************************************************************************************************************
// CLASSE REGISSANT LES INFORMATIONS SUR UN COMPTE UTILISATEUR DE CONTACT 
// *************************************************************************************************************
// La classe USER g�re l'utilisateur en cours pour une session.
// La classe UTILISATEUR g�re l'utilisateur d'un contact en dehors de toute session.

final class utilisateur {
	private $ref_user;						// R�f�rence de l'utilisateur
	
	private $ref_coord_user;			// Coordonn�es de l'utilisateur
	private $ref_contact;					// R�f�rence du contact propri�taire de l'utilisateur
	private $master;							// 1 si il s'agit du compte maitre de ce contact

	private $pseudo;							// Pseudo affich�
	private $code;								// Code
	
	private $actif;								// 1 si le compte utilisateur est actif
	private $ordre;								// Ordre d'affichage de ce compte utilisateur dans la liste du contact
	
	private $permissions;					// Tableau des permissions de l'utilisateur
	private $allowed_profils;			// Tableau des profils utilis�s


function __construct($ref_user = "") {
	global $bdd;

	// Controle si la ref_user est pr�cis�e
	if (!$ref_user) { return false; }

	// S�lection des informations g�n�rales
	$query = "SELECT u.ref_contact, ref_coord_user, master, pseudo, actif, ordre, id_langage
						FROM users u
						WHERE ref_user = '".$ref_user."' ";
	$resultat = $bdd->query ($query);

	// Controle si la ref_user est trouv�e
	if (!$utilisateur = $resultat->fetchObject()) { return false; }

	// Attribution des informations � l'objet
	$this->ref_user 			= $ref_user;
	$this->ref_coord_user	= $utilisateur->ref_coord_user;
	$this->ref_contact 		= $utilisateur->ref_contact;
	$this->master					= $utilisateur->master;
	$this->pseudo			= $utilisateur->pseudo;
	$this->actif			= $utilisateur->actif;
	$this->ordre			= $utilisateur->ordre;
	$this->id_langage	= $utilisateur->id_langage;

	return true;
}



// *************************************************************************************************************
// FONCTIONS LIEES A LA CREATION D'UN UTILISATEUR 
// *************************************************************************************************************

final public function create ($ref_contact, $ref_coord_user, $pseudo, $actif, $code, $id_langage) {
	global $bdd;

	$UTILISATEUR_ID_REFERENCE_TAG = 3;		// R�f�rence Tag utilis� dans la base de donn�e

	// *************************************************
	// Controle des donn�es transmises
	if (!$ref_coord_user) {
		$GLOBALS['_ALERTES']['no_ref_coord_user'] = 1;
	}
	
	if ($ref_coord_user) {
		$query = "SELECT ref_coord_user, actif, ref_user FROM users WHERE ref_coord_user = '".$ref_coord_user."'";
		$resultat = $bdd->query($query);
		if ($tmp= $resultat->fetchObject()) {
			if ($tmp->actif == -1) {
				//suppression de la ref_coord_user de l'utilisateur archiv�
				$query = "UPDATE users 
									SET ref_coord_user = NULL 
									WHERE ref_user = '".$tmp->ref_user."' ";
				$bdd->exec ($query);
			} else {
				$GLOBALS['_ALERTES']['used_ref_coord_user'] = 1;
			}
		}
	}
	if (!$pseudo) {
		$GLOBALS['_ALERTES']['no_pseudo'] = 1;
	}
	//v�rifie que le pseudo est unique
	if ($pseudo) {
		$query = "SELECT pseudo, u.ref_user, u.ref_contact, c.email, a.nom, u.actif
							FROM users u
								LEFT JOIN annuaire a ON u.ref_contact = a.ref_contact 
								LEFT JOIN coordonnees c ON u.ref_coord_user = c.ref_coord  
							WHERE u.pseudo = '".addslashes($pseudo)."' ";
		$resultat = $bdd->query ($query);
		if ($tmp = $resultat->fetchObject()) { 
			//v�rification de l'utilisation du pseudo � un user non supprim�
			if ($tmp->actif == -1) {
				//modification du pseudo de l'utilisateur archiv�
				$query = "UPDATE users 
									SET pseudo = '".$tmp->pseudo."/".$tmp->ref_user."'
									WHERE ref_user = '".$tmp->ref_user."' ";
				$bdd->exec ($query);

			} else {
				// On renvoi une erreur de saisie avec les infos du contact correspondant
				$GLOBALS['_ALERTES']['used_pseudo'] = array($tmp->ref_contact, str_replace("\n", "", $tmp->nom)." / ".$tmp->email); 
			}
		
		}
	}

	// *************************************************
	// Controle du niveau de s�curit� du mot de passe, avant acceptation
	$securite_ok = $this->check_code_security($code);
	if ($securite_ok) { 
		$this->code = $code;
	}
	// *************************************************
	// Si les valeurs re�ues sont incorrectes
	if (count($GLOBALS['_ALERTES'])) {
		return false;
	}
	$this->ref_contact 		= $ref_contact;
	$this->ref_coord_user = $ref_coord_user;
	$this->pseudo 		= $pseudo;
	$this->actif 			= $actif;
	$this->id_langage	= $id_langage;
	

	// *************************************************
	// Cr�ation de la r�f�rence
	$reference = new reference ($UTILISATEUR_ID_REFERENCE_TAG);
	$this->ref_user = $reference->generer_ref();
	
	// Ordre d'affichage
	$query = "SELECT MAX(ordre) ordre FROM users WHERE ref_contact = '".$this->ref_contact."' ";
	$resultat = $bdd->query($query);
	$tmp = $resultat->fetchObject();
	$this->ordre = $tmp->ordre+1;
	unset ($query, $resultat, $tmp);
	
	// Compte maitre
	$query = "SELECT ref_user FROM users WHERE ref_contact = '".$this->ref_contact."' && actif = 1 && master = 1 ";
	$resultat = $bdd->query($query);
	$tmp = $resultat->fetchObject();
	if (isset($tmp->ref_user)) {
		$this->master = 0;
	}
	else {
		$this->master = 1;
	}


	// *************************************************
	// Profils associ�s au compte du contact
	$this->allowed_profils = array();
	$query = "SELECT ap.id_profil, p.id_permission
						FROM permissions p
							LEFT JOIN annuaire_profils ap ON ap.id_profil = p.id_profil 
						WHERE ap.ref_contact = '".$this->ref_contact."' && ISNULL(id_permission_parent) "; 
	$resultat = $bdd->query($query);
	while ($tmp = $resultat->fetchObject()) {	$this->allowed_profils[] = $tmp; }


	// *************************************************
	// Insertion dans la base
	$bdd->beginTransaction();

	$query = "INSERT INTO users (ref_user, ref_contact, ref_coord_user, master, pseudo, code, actif, 
															 ordre, id_langage)
						VALUES ('".$this->ref_user."', '".$this->ref_contact."', '".$this->ref_coord_user."', '".$this->master."', 
										'".$this->pseudo."', md5('".$this->code."'), '".$this->actif."', '".$this->ordre."', 
										'".$this->id_langage."')";
	$bdd->exec($query);
	
	// Cr�ation des droits associ�s
	$query_insert = "";
	foreach ($this->allowed_profils as $profil) {
		if ($query_insert) { $query_insert .= ","; }
		$query_insert .= "('".$this->ref_user."', '".$profil->id_permission."', 'ALL')";
	}
	if ($query_insert) {
		$query = "INSERT INTO users_permissions (ref_user, id_permission, value) 
							VALUES ".$query_insert;
		$bdd->exec ($query);
	}
	
	$bdd->commit();


	// *************************************************
	// R�sultat positif de la cr�ation
	$GLOBALS['_INFOS']['Cr�ation_utilisateur'] = $this->ref_user;

	return true;
}



// *************************************************************************************************************
// FONCTIONS LIEES A LA MODIFICATION D'UN UTILISATEUR
// *************************************************************************************************************

final public function modification ($ref_coord_user, $pseudo, $actif, $id_langage) {
	global $bdd;
	
	// *************************************************
	// Controle des donn�es transmises
	if (!$ref_coord_user) {
		$GLOBALS['_ALERTES']['no_ref_coord_user'] = 1;
	}
	
	if ($this->ref_coord_user != $ref_coord_user) {
		$query = "SELECT ref_coord_user, actif, ref_user FROM users WHERE ref_coord_user = '".$ref_coord_user."'";
		$resultat = $bdd->query($query);
		if ($tmp= $resultat->fetchObject()) {
			if ($tmp->actif == -1) {
				//suppression de la ref_coord_user de l'utilisateur archiv�
				$query = "UPDATE users 
									SET ref_coord_user = NULL 
									WHERE ref_user = '".$tmp->ref_user."' ";
				$bdd->exec ($query);
			} else {
				$GLOBALS['_ALERTES']['used_ref_coord_user'] = 1;
			}
		}
	}
	
	if (!$pseudo) {
		$GLOBALS['_ALERTES']['no_pseudo'] = 1;
	}
	//v�rifie que le pseudo est unique
	if ($this->pseudo != $pseudo) {
		$query = "SELECT pseudo, u.ref_user, u.ref_contact, c.email, a.nom, u.actif
							FROM users u
								LEFT JOIN annuaire a ON u.ref_contact = a.ref_contact 
								LEFT JOIN coordonnees c ON u.ref_coord_user = c.ref_coord  
							WHERE u.pseudo = '".addslashes($pseudo)."' ";
		$resultat = $bdd->query ($query);
		if ($tmp = $resultat->fetchObject()) { 
			//v�rification de l'utilisation du pseudo � un user non supprim�
			if ($tmp->actif == -1) {
				//modification du pseudo de l'utilisateur archiv�
				$query = "UPDATE users 
									SET pseudo = '".$tmp->pseudo."/".$tmp->ref_user."'
									WHERE ref_user = '".$tmp->ref_user."' ";
				$bdd->exec ($query);

			} else {
				//on renvois une erreur de saisie avec les infos du contact corespondant
				$GLOBALS['_ALERTES']['used_pseudo'] = array($tmp->ref_contact, str_replace("\n", "", $tmp->nom)." / ".$tmp->email); 
			}
		
		}
	}
	if ($actif != 1 && $actif != 0) {
		$actif = 0;
	}

	// *************************************************
	// Si les valeurs re�ues sont incorrectes
	if (count($GLOBALS['_ALERTES'])) {
		return false;
	}
	$this->ref_coord_user = $ref_coord_user;
	$this->pseudo 		= $pseudo;
	$this->actif 			= $actif;
	$this->id_langage	= $id_langage;

	// *************************************************
	// Mise � jour de la base
	$query = "UPDATE users 
						SET ref_coord_user = '".$this->ref_coord_user."', pseudo = '".addslashes($this->pseudo)."', 
								actif = '".$this->actif."', id_langage = '".$this->id_langage."'
						WHERE ref_user = '".$this->ref_user."' ";
	$bdd->exec ($query);
		
	// *************************************************
	// R�sultat positif de la modification
	$GLOBALS['_ALERTES']['Modification_utilisateur'] = 1;

	return true;
}


// Modifie le mot de passe
final public function changer_code ($new_code) {
	global $bdd;

	$securite_ok = $this->check_code_security($new_code);
	if ($securite_ok) { 
		$this->code = $new_code;
	}
	
	// *************************************************
	// Si les valeurs re�ues sont incorrectes
	if (count($GLOBALS['_ALERTES'])) {
		return false;
	}

	// *************************************************
	// Mise � jour de la base
	$query = "UPDATE users 
						SET code = md5('".$this->code."')
						WHERE ref_user = '".$this->ref_user."' ";
	$bdd->exec ($query);
		
	// *************************************************
	// R�sultat positif de la modification
	$GLOBALS['_INFOS']['Modification_code'] = 1;

	return true;
}


// Modifie le mot de passe
final public function set_master () {
	global $bdd;

	// *************************************************
	// Mise � jour de la base
	$bdd->beginTransaction();

	$query = "UPDATE users 
						SET master = 0
						WHERE ref_contact = '".$this->ref_contact."' ";
	$bdd->exec ($query);
	
	$query = "UPDATE users 
						SET master = 1
						WHERE ref_user = '".$this->ref_user."' ";
	$bdd->exec ($query);
	
	$bdd->commit();
		
	// *************************************************
	// R�sultat positif de la modification
	$GLOBALS['_INFOS']['set_master'] = 1;

	return true;
}


final public function modifier_ordre ($new_ordre) {
	global $bdd;
	if ($new_ordre == $this->ordre) { return false; }

	if (!is_numeric($new_ordre)) {
		$GLOBALS['_ALERTES']['bad_ordre'] = 1;
	}
	
	// *************************************************
	// Si les valeurs re�ues sont incorrectes
	if (count($GLOBALS['_ALERTES'])) {
		return false;
	}
	
	if ($new_ordre < $this->ordre) {
		$variation = "+";
		$symbole1 = "<";
		$symbole2 = ">=";
	}
	else {
		$variation = "-";
		$symbole1 = ">";
		$symbole2 = "<=";
	}

	$bdd->beginTransaction();
	
	// Mise � jour des autres users
	$query = "UPDATE users
						SET ordre = ordre ".$variation." 1
						WHERE ref_contact = '".$this->ref_contact."' && 
									ordre ".$symbole1." '".$this->ordre."' && ordre ".$symbole2." '".$new_ordre."' ";
	$bdd->exec ($query);
	
	// Mise � jour de cette adresse
	$query = "UPDATE users
						SET ordre = '".$new_ordre."'
						WHERE ref_user = '".$this->ref_user."'  ";
	$bdd->exec ($query);
	
	$bdd->commit();	

	$this->ordre = $new_ordre;

	// *************************************************
	// R�sultat positif de la modification
	return true;
}



// *************************************************************************************************************
// FONCTIONS LIEES A LA SUPPRESSION D'UN UTILISATEUR
// *************************************************************************************************************
// Un compte utilisateur n'est pas supprim�, il est juste archiv� 
final public function suppression () {
	global $bdd;

	// *************************************************
	// Controle cet utilisateur est le compte maitre, et qu'il y a d'autres comptes utilisateurs
	if ($this->master) {
		$query = "SELECT COUNT(ref_user) nb_users FROM users WHERE ref_contact = '".$this->ref_contact."' ";
		$resultat = $bdd->query ($query);
		$result = $resultat->fetchObject();
		if ($result->nb_users > 1) {
			$GLOBALS['_ALERTES']['compte_maitre'] = 1;
		}
	}

	// *************************************************
	// Arret en cas d'erreur
	if (count($GLOBALS['_ALERTES'])) {
		return false;
	}

	// *************************************************
	// Archivage du compte
	$query = "UPDATE users SET actif = -1 , pseudo = '".$this->pseudo."/".$this->ref_user."', ref_coord_user = NULL, ordre = 0
						WHERE ref_user = '".$this->ref_user."' ";
	$bdd->exec ($query);
	
	// Changement de l'ordre des users suivants
	$query = "UPDATE users 
						SET ordre = ordre -1
						WHERE ref_contact = '".$this->ref_contact."' && ordre > '".$this->ordre."'";
	$bdd->exec ($query);

	unset ($this);
}



// *************************************************************************************************************
// FONCTIONS DIVERSES
// *************************************************************************************************************
// V�rifie si le niveau de s�curit� du mot de passe est suffisant
function check_code_security ($code) {

	if (empty($code)) {
		$GLOBALS['_ALERTES']['code_vide'] = 1;
		return false;
	}
	
	return true;
}

// *************************************************************************************************************
// FONCTIONS DIVERSES
// *************************************************************************************************************

//************************************************************************************************************
//FONCTION D'INSERTION DES PREMISSIONS UTILISATEURS D'UN CONTACT AFIN D'AUTORISER L'UTILISATION DE L'INTERFACES DU PROFIL AJOUT� SI IL EXISTE DANS LES PROFIL_ALLOWED
//************************************************************************************************************
static function set_users_permission ($ref_contact = "", $id_profil = "") {
	global $bdd;

	if (!$ref_contact && !$id_profil) { return false; }
	// *************************************************
	// Profils associ�s au compte du contact
	$allowed_profils = array();
	$query = "SELECT ap.id_profil, p.id_permission
						FROM permissions p
							LEFT JOIN annuaire_profils ap ON ap.id_profil = p.id_profil 
						WHERE ap.ref_contact = '".$ref_contact."' && ISNULL(id_permission_parent) && p.id_profil = '".$id_profil."' "; 
	$resultat = $bdd->query($query);
	while ($tmp = $resultat->fetchObject()) {	$allowed_profils[] = $tmp; }

	// *************************************************
	// utilisateurs associ�s au compte du contact
	$users = array();
	$query = "SELECT u.ref_user
						FROM users u
						WHERE u.ref_contact = '".$ref_contact."' && u.actif = 1 "; 
	$resultat = $bdd->query($query);
	while ($tmp = $resultat->fetchObject()) {	$users[] = $tmp; }

	
	// mise � jour des droits associ�s
	foreach ($users as $user) {
		$query_insert = "";
		foreach ($allowed_profils as $profil) {
			if ($query_insert) { $query_insert .= ","; }
			$query_insert .= "('".$user->ref_user."', '".$profil->id_permission."', 'ALL')";
		}
		if ($query_insert) {
			$query = "INSERT INTO users_permissions (ref_user, id_permission, value) 
								VALUES ".$query_insert;
			$bdd->exec ($query);
		}
	}
	
	$bdd->commit();
	
	// *************************************************
	// R�sultat positif de la modification
	return true;
}
//************************************************************************************************************
//FONCTION DE SUPPRESSION DES PERMISSIONS UTILISATEURS D'UN CONTACT 
//************************************************************************************************************
//AFIN DE NE PLUS AUTORISER L'UTILISATION DE L'INTERFACES DU PROFIL SUPPRIM� 
static function unset_users_permission ($ref_contact = "", $id_profil = "") {
	global $bdd;
	
	if (!$ref_contact && !$id_profil) { return false; }
	// *************************************************
	// R�cup�ration de l'id_permission pour le profil supprim� 
	$allowed_profils = array();
	$query = "SELECT p.id_permission
						FROM permissions p
						WHERE p.id_profil = '".$id_profil."' "; 
	$resultat = $bdd->query($query);
	while ($tmp = $resultat->fetchObject()) {	$allowed_profils[] = $tmp; }

	// *************************************************
	// utilisateurs associ�s au compte du contact
	$users = array();
	$query = "SELECT u.ref_user
						FROM users u
						WHERE u.ref_contact = '".$ref_contact."' "; 
	$resultat = $bdd->query($query);
	while ($tmp = $resultat->fetchObject()) {	$users[] = $tmp; }

	
	// mise � jour des droits associ�s
	foreach ($users as $user) {
		$query_where = "";
		foreach ($allowed_profils as $profil) {
			if ($query_where) { $query_where .= " || "; }
			$query_where .= "(ref_user = '".$user->ref_user."' &&  id_permission = '".$profil->id_permission."')";
		}
		if ($query_where) {
			$query = "DELETE FROM users_permissions 
								WHERE ".$query_where;
			$bdd->exec ($query);
		}
	}
	
	$bdd->commit();
	
	// *************************************************
	// R�sultat positif de la suppression
	return true;
}

// *************************************************************************************************************
// FONCTIONS DIVERSES
// *************************************************************************************************************

// renvoi de la ref user en fonction de l'ordre
public function get_user_fonctions(){
	global $bdd;
	
	$return = array();
	
	$query = "SELECT DISTINCT f.id_fonction,f.lib_fonction
						FROM users u
							RIGHT JOIN annu_collab_fonctions acf ON u.ref_contact = acf.ref_contact
							RIGHT JOIN fonctions f ON acf.id_fonction = f.id_fonction
						WHERE u.ref_user = '".$this->ref_user."'
						ORDER BY id_fonction ASC"	;
	$resultat = $bdd->query ($query);
	while ($fonction = $resultat->fetchObject()) {
			$return[] = $fonction;
	}
	return $return;
}

// renvoi de la ref user en fonction de l'ordre
static function getRef_user_from_ordre ($ref_contact, $ordre) {
	global $bdd;
	
	$user = "";
	$query = "SELECT ref_user
							FROM users
						WHERE ref_contact = '".$ref_contact."' 
						AND ordre = ".$ordre." 
						LIMIT 1"	;
	$resultat = $bdd->query ($query);
	if ($u = $resultat->fetchObject()) { $user = $u->ref_user; }
	return $user;
}

//retourne une liste des ref_user en fonction d'une plage d'ordre (mise � jour de l'affichage des utilisateurs)
public function liste_ref_user_in_ordre () {
	global $bdd;
	
	$users = array();
	$query = "SELECT ref_user
						FROM users 
						WHERE ref_contact = '".$this->ref_contact."' 
						&& (ordre> ".$this->ordre." || ordre= ".$this->ordre."-1)";
	$resultat = $bdd->query ($query);
	while ($user = $resultat->fetchObject()) { $users[] = $user; }

	return $users;
}

//retourne une liste des ref_user actifs
public function liste_ref_user_actif () {
	global $bdd;
	
	$users = array();
	$query = "SELECT u.ref_user, u.pseudo, co.email
							FROM users u
							LEFT JOIN coordonnees co ON co.ref_coord = u.ref_coord_user 
						WHERE u.ref_contact = '".$this->ref_contact."' && u.actif >= 0 ";
	$resultat = $bdd->query ($query);
	while ($user = $resultat->fetchObject()) { $users[] = $user; }
	
	return $users;
}

// *************************************************************************************************************
// FONCTIONS DE LECTURE DES DONNEES 
// *************************************************************************************************************
function getRef_user () {
	return $this->ref_user;
}

function getMaster () {
	return $this->master;
}

function getRef_coord_user () {
	return $this->ref_coord_user;
}

function getActif () {
	return $this->actif;
}

function getNote () {
	return $this->note;
}

function getOrdre () {
	return $this->ordre;
}

function getPseudo () {
	return $this->pseudo;
}

function getId_langage () {
	return $this->id_langage;
}


function getRef_contact () {
	return $this->ref_contact;
}


}
// fin de la class

?>
