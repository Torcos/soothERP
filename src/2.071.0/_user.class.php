<?php
// *************************************************************************************************************
// CLASSE DE GESTION DE L'UTILISATEUR DU PROGRAMME
// *************************************************************************************************************
// La classe USER g�re l'utilisateur en cours pour une session.
// La classe UTILISATEUR g�re l'utilisateur d'un contact en dehors de toute session.

final class user {
	private $ref_user;						// R�f�rence de l'utilisateur

	private $ref_coord_user;			// Coordonn�es de l'utilisateur
	private $ref_contact;					// R�f�rence du contact propri�taire de l'utilisateur
	private $master;							// 1 si il s'agit du compte maitre de ce contact

	private $pseudo;							// Pseudo affich�
	private $code;
	private $login;								// 1 si l'utilisateur est loggu�
	private $login_time;					// Heure de connexion

	private $actif;								// 1 si le compte utilisateur est actif
	private $ordre;								// Ordre d'affichage de ce compte utilisateur dans la liste du contact

	public  $profil;							// Profil en cours pour la session
	private $allowed_profils;			// Tableau des profils autoris�s

	private $id_interface;				// Interface en cours d'utilisation
	private $last_id_interface;		// Derniere interface utilis�e
	private $last_id_theme;				// Dernier theme utilis�

	private $permissions;					// Tableau des permissions de l'utilisateur (tout profil confondu)

	private $email;								// Email associ� aux coordonn�es
	private $contact;


// Constructeur
function __construct () {
	global $DEFAUT_PROFILS;

	$this->login = false;
	// Profils autoris�s par d�faut
	foreach ($DEFAUT_PROFILS as $id_profil) { $this->allowed_profils[$id_profil] = $id_profil; }
	// Les autres profils seront autoris�s apr�s le login en fonction de l'utilisateur
	
	return true;
}



// *************************************************************************************************************
// LOGIN DE L'UTILISATEUR 
// *************************************************************************************************************
final public function login ($login, $code, $page_from = "", $login_id_interface = NULL) { 
	global $bdd;
	global $COOKIE_LOGIN_LT;

	if (!$login) {
		$GLOBALS['_ALERTES']['login_absent'] = 1;
		return false;
	}

	// ************************************************
	// S�lection des informations sur l'utilisateur
	$query = "SELECT ref_user, u.ref_contact, master, ref_coord_user, pseudo, code, actif, u.last_id_interface, c.email
						FROM users u
							LEFT JOIN coordonnees c ON u.ref_coord_user = c.ref_coord
						WHERE (u.ref_user = '".addslashes($login)."' || u.pseudo = '".addslashes($login)."' || c.email = '".addslashes($login)."' ) && 
									 u.code = MD5('".addslashes($code)."')
						ORDER BY actif DESC
						LIMIT 0,1  ";
	$result = $bdd->query($query);
	$user = $result->fetchObject();
	if (!isset($user->ref_user)) {
		$this->log_bad_login($login, $code);
		$GLOBALS['_ALERTES']['login_faux'] = 1;
		return false;
	}

	$this->ref_user 			= $user->ref_user;
	$this->ref_coord_user = $user->ref_coord_user;
	$this->ref_contact 		= $user->ref_contact;
	$this->master 				= $user->master;
	$this->pseudo 				= $user->pseudo;
	$this->code 					= $user->code;
	$this->actif 					= $user->actif;
	$this->email				 	= $user->email;
	$this->last_id_interface	= $user->last_id_interface;

	// ************************************************
	// Compte actif ?
	if (!$this->actif) {
		$GLOBALS['_ALERTES']['non_actif'] = 1;
		return false;
	}


	// ************************************************
	// Log de la connexion
	$this->log_connexion ();
  $this->login	 		= true;
  $this->login_time = time();

 
	// ************************************************
	// Cr�ation du Cookie 
	$used_users = array();
	if (isset($_COOKIE['predefined_user'])) { 
		$pred_user = explode(";", $_COOKIE['predefined_user']); 
		$tmp_p_users = array ();
		foreach($pred_user as $p_user) {
			$tmp_p_users[] = explode("--", $p_user ); 
		}
		foreach($tmp_p_users as $tmp_p_user) {
			if ($tmp_p_user[0] == $this->email) {continue;}
			if (isset($tmp_p_user[1]) && (strtotime("now")-strtotime($tmp_p_user[1])) < (30*24*3600) ){
				array_unshift($used_users, $tmp_p_user[0]."--".$tmp_p_user[1]);
			}
		}
		//$used_users = explode(";", $_COOKIE['predefined_user']); 
		array_unshift($used_users, $this->email."--".date("d-m-Y", time()));
	} else {
		array_unshift($used_users, $this->email."--".date("d-m-Y", time()));
	}
	setcookie('predefined_user', implode(";", $used_users), time() + $COOKIE_LOGIN_LT, "/");

	// ************************************************
  // Contact de l'utilisateur
  $this->contact = new contact ($this->ref_contact);
	if ($this->contact->getDate_archivage () != NULL ) {
		$this->contact->blocages_utilisateurs ();
		$GLOBALS['_ALERTES']['contact archiv�'] = 1;
		return false;
	} 
	
	
	// ************************************************
  // S�lection des permissions de l'utilisateur (dont les profils autoris�s)
  $this->define_permissions (); 
  
  // ************************************************
  // S�lection de l'interface � utiliser
  $this->select_login_interface ($login_id_interface);  


  // ************************************************
  // Page de redirection suite au login
	if ($this->id_interface <= 1) {
		// Si l'interface � utiliser n'est pas d�finie, redirection vers la page de s�lection du profil
		$redirection = $_ENV['CHEMIN_ABSOLU']."site/__user_choix_profil.php";
	}
	else {
		// Page d'accueil du profil
		$redirection = $_ENV['CHEMIN_ABSOLU'].$_SESSION['interfaces'][$this->id_interface]->getDossier();
	}
	
	$GLOBALS['_INFOS']['redirection'] = $redirection;
	return true;
}


// Enregistre la connexion de l'utilisateur
final private function log_connexion () {
	global $bdd;

	$query = "INSERT INTO users_logs (ref_user, date, ip) 
						VALUES ('".$this->ref_user."', NOW(), '".$_SERVER['REMOTE_ADDR']."') ";
	$bdd->exec ($query);
}



// *************************************************************************************************************
// DEFINITION DES PERMISSIONS DE L'UTILISATEUR 
// *************************************************************************************************************

// permissions associ�s � l'utilisateurs
final private function define_permissions () {
	global $bdd;

	$this->permissions = array();
	
	// Controle
	if (!$this->login) { return false; }

	// Selection des permissions
	$query = "SELECT up.id_permission, up.value, p.id_profil, p.id_permission_parent
						FROM users_permissions up
							LEFT JOIN permissions p ON up.id_permission = p.id_permission
						WHERE up.ref_user = '".$this->ref_user."' ";
	$result = $bdd->query ($query);
	while ($var = $result->fetchObject()) { $this->permissions[] = $var; } 
	
	// D�fini quels profils sont accessibles
	foreach ($this->permissions as $permission) {
		if ($permission->id_permission_parent) { continue; } 										// Si il y a une permission parent, nous ne sommes pas � la racine
		if (!isset($_SESSION['profils'][$permission->id_profil])) { continue; } // Si le profil n'est pas en session, ne pas l'autoriser

		$this->allowed_profils[$permission->id_profil] = $permission->id_profil;
	}

	return true;
}

public function check_permission ($id_permission,$id_perm_value="") {
	global $bdd;
	
	$query = "SELECT id_permission, value
						FROM users_permissions 
						WHERE ref_user = '".$this->ref_user."' &&  id_permission = '".$id_permission."' ";
	$result = $bdd->query ($query);
	if ($var = $result->fetchObject()) {
		if (isset($id_perm_value) && $id_perm_value!=""){
			$tmp = explode(",",$var->value);
			if (in_array($id_perm_value,$tmp) || in_array("ALL",$tmp)){
				return true;
			}else{
				return false;
			}
		} else {
		return $var->value;
		}
	} 
	return false;
}




// *************************************************************************************************************
// DEFINITION DE L'INTERFACE DE L'UTILISATEUR 
// *************************************************************************************************************

// V�rifie le droit d'acc�der � une interface
public function interface_is_allowed ($tested_id_interface) {
	if (!isset($_SESSION['interfaces'][$tested_id_interface])) { 
		return false; 
	}
	if (!in_array($_SESSION['interfaces'][$tested_id_interface]->getId_profil(), $this->allowed_profils)) {
		return false;
	}
	return true;
}

// D�fini l'interface dans laquelle l'utilisateur sera redirig� directement apr�s un login
final private function select_login_interface ($login_id_interface) {
	// Interface pr�d�finie via le formulaire de login
	if ($this->interface_is_allowed($login_id_interface)) {
		$this->set_interface ($login_id_interface);
		return true;
	}

	// Derniere Interface utilis�e avec succ�s
	if ($this->interface_is_allowed($this->last_id_interface)) {
		$this->set_interface ($this->last_id_interface);
		return true;
	}

	return false;
}


// Change l'interface pour la session en cours 
public function set_interface ($id_interface) {
	$this->id_interface = $id_interface;
	$this->log_user_interface($id_interface);
	$this->set_profil($_SESSION['interfaces'][$this->id_interface]->getId_profil());
	$this->define_theme_to_use ($_SESSION['interfaces'][$this->id_interface]->getDefaut_id_theme());
}


// Enregistre l'utilisation de cette interface
final private function log_user_interface ($id_interface) {
	global $bdd;

	// Si l'utilisateur n'est pas loggu�, ou si aucun changement d'interface
	if ( !$this->login || $this->last_id_interface == $id_interface) {
		return false;
	}

	// Enregistrement en base de donn�e
	$query = "UPDATE users 
						SET last_id_interface = '".$this->id_interface."'
						WHERE ref_user = '".$this->ref_user."' ";
	$bdd->exec ($query);

	// En session
	$this->last_id_interface = $id_interface;

	return true;
}


// *************************************************************************************************************
// GESTION DU PROFIL 
// *************************************************************************************************************

// Charge les informations sp�cifiques li�es au profil d'un utilisateur
final public function set_profil ($id_profil) {
	global $DIR;

	$code_profil 	= $_SESSION['profils'][$id_profil]->getCode_profil();
	$class_name 	= "user_".$code_profil;
	$this->profil = new $class_name ($_SESSION['profils'][$id_profil]);
	$this->profil->set_user ($this->ref_user, $this->ref_contact);

	return true;
}


// *************************************************************************************************************
// DEFINITION DU THEME POUR L'UTILISATEUR 
// *************************************************************************************************************

// D�fini le th�me a utiliser
final private function define_theme_to_use ($id_theme) {
	global $bdd;

	// Le th�me �tant red�fini apr�s un changement d'interface, on ne connait pas encore le th�me pr�c�dent pour ce profil.
	$last_id_theme = 0;

	// Recherche du pr�c�dent th�me de l'utilisateur pour le profil en cours
	if ($this->login) {
		$query = "SELECT id_theme FROM users_themes
							WHERE ref_user = '".$this->ref_user."' && id_interface = '".$this->id_interface."' ";
		$result = $bdd->query ($query);
		$theme = $result->fetchObject();

		if (isset($theme->id_theme)) {
			$this->last_id_theme = $theme->id_theme;
			$this->set_theme($theme->id_theme);
			return true;
		}
	}
	
	// Utilisation du th�me par d�faut pour cet utilisateur
	$this->set_theme($_SESSION['interfaces'][$this->id_interface]->getDefaut_id_theme());
	return true;
}


// Charge le th�me
final private function set_theme ($id_theme) {
	// D�fini le th�me en cours
	$_SESSION['theme'] = new theme ($id_theme);

	// Enregistrement 
	$this->log_user_theme(); 
}


// Enregistre l'utilisation de ce th�me pour la prochaine session
final private function log_user_theme () {
	global $bdd;

	// Si l'utilisateur n'est pas loggu�, ou que le th�me n'est pas chang�, inutile d'aller plus loin
	if (!$this->login || $this->last_id_theme == $_SESSION['theme']->getId_theme()) {
		return false;
	}
	
	// Si aucun th�me n'a �t� d�fini pour cet utilisateur et ce profil
	if (!$this->last_id_theme) {
		$query = "INSERT INTO users_themes (ref_user, id_interface, id_theme)
							VALUES ('".$this->ref_user."', '".$this->id_interface."', '".$_SESSION['theme']->getId_theme()."')";
		$bdd->exec ($query);
	}
	else {
		$query = "UPDATE users_themes SET id_theme = '".$_SESSION['theme']->getId_theme()."'
							WHERE ref_user = '".$this->ref_user."' && id_interface = '".$this->id_interface."' ";
		$bdd->exec ($query);
	} 
	
	// enregistrement en session
	$this->last_id_theme = $_SESSION['theme']->getId_theme();
	
	return true;
}






// *************************************************************************************************************
// FONCTIONS DE SECURITE 
// *************************************************************************************************************

private function log_bad_login ($login, $code) {
	global $bdd;
	global $DEFAUT_INTERFACE;
	
	$ip = "";
	$user_agent = "";
	// Modification �ffectu�e par Yves Bourvon 
	// La variable $code (mot de passe) �tait �crite en clair dans la base de donn�es dans l'error logs. 
	// Application d'un hachage md5, ce qui permet pour maintenance de comparer au hachage des mots de passe users, mais interdit la l'acc�s en clair.
	$query = "INSERT INTO users_logs_errors (ip, user_agent, date, login, code) 
						VALUES ('".$ip."', '".$user_agent."', NOW(), '".addslashes($login)."', '".md5(addslashes($code))."') ";
	$bdd->exec ($query);

	if (0) {
		$redirection = $_ENV['CHEMIN_ABSOLU'].$DEFAUT_INTERFACE;
		$GLOBALS['_INFOS']['redirection'] = $redirection;
	}
	return true;
}

//verification de la deri�re heure de connexion pour rafraichissement des infos en cache
public function last_log_connexion () {
	global $bdd;

	$query = "SELECT MAX(date) as date_last_log
						FROM users_logs 
						WHERE ref_user='".$this->ref_user."' ";
		$result = $bdd->query ($query);
		$last_log = $result->fetchObject();
		return $last_log->date_last_log;
}




// *************************************************************************************************************
// FONCTIONS DE PUBLICATION DES INFORMATIONS SUR L'UTILISATEUR 
// *************************************************************************************************************

// Retourne si l'utilisateur est loggu�
public function getLogin () {
	if ($this->login) { return true; }
	return false;
}

// Retourne si l'utilisateur est loggu�
public function getRef_user () {
	return $this->ref_user;
}

function getPseudo () {
	return $this->pseudo;
}

// Retourne la liste des profils autoris�s
public function getProfils_allowed () {
	return $this->allowed_profils;
}

function getId_interface () {
	return $this->id_interface;
}

// Retourne la ref_contact de l'user
public function getRef_contact () {
	return $this->ref_contact;
}

function getContactName () {
	if (!is_object($this->contact)) {
		$this->contact = new contact ($this->ref_contact);
	}

	return $this->contact->getNom();
}

function getEmail () {
	return $this->email;
}

// Retourne l'identifiant du profil en cours
public function getId_profil () {
	return $_SESSION['user']->profil->getId_profil();
}

// Retourne le chemin du profil en cours
public function getProfil_dir () {
	return $_SESSION['user']->profil->getDir_profil ();
}

}
//fonction g�n�rant un fichier indiquant qu'une mise � jour des infos de session doit etre effectu�e
function serveur_maj_file() {
	global $DIR;
	$file_id = fopen ($DIR."_session_maj.php", "w");
	fwrite ($file_id, date("d/m/Y")."\n");
	fclose ($file_id);
}

?>