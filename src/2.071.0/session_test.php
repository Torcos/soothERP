<?php
// *************************************************************************************************************
// TEST DE VALIDITE DE LA SESSION
// *************************************************************************************************************

$_PAGE['MUST_BE_LOGIN'] = 0;
$DONT_EXTAND_USER_SESSION = 1;

require ("_dir.inc.php");
require ($DIR."_session.inc.php");



if (!isset($_SESSION['date_debut_user_session'])) { $_SESSION['date_debut_user_session'] = time(); }


if ( ($_SESSION['date_debut_user_session'] + $USER_SESSION_LT + $USER_SESSION_LT/$TEST_SESSION_TIMER) >= time() ) {
	echo "ok";
}
else {
	if ($_SESSION['user']->getRef_user ()) {
		$_SESSION['USER_INFOS']['id_profil']		= $_SESSION['user']->getId_profil ();
		$_SESSION['USER_INFOS']['contact_name']	= $_SESSION['user']->getContactName ();
		$_SESSION['USER_INFOS']['pseudo']				= $_SESSION['user']->getPseudo ();
		$_SESSION['USER_INFOS']['ref_user']			= $_SESSION['user']->getRef_user ();
	}
	unset($_SESSION['user']);

	echo "pasok";

	$_SESSION['date_debut_user_session'] = time();
}


// Le temps d'une session utilisateur est donn�e par $USER_SESSION_LT															1 Heure
// La validit� de cette session est test�e toutes les ($USER_SESSION_LT / $TEST_SESSION_TIMER)		4 fois par "1 Heure"
// Une action effectu�e repousse la fin de session: Une session initialis�e � 00H00 finiera donc peut etre � 1H05.
// Le test effectu� sur cette page v�rifie que la fin de session n'est pas atteinte
// Et �galement que la session ne sera pas expir�e AVANT le prochain test. 
// Dans ce dernier cas, elle demandera tout de meme une r�identification

?>