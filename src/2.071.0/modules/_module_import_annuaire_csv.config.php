<?php
// *************************************************************************************************************
// CONFIG $import_annuaire_csv 
// *************************************************************************************************************


$import_annuaire_csv['folder_name'] = "import_annuaire_csv/";

$import_annuaire_csv['menu_admin'][0]		= array('separateur','','true','','');
$import_annuaire_csv['menu_admin'][1]		=  array('import_annuaire_csv','modules/'.$import_annuaire_csv['folder_name'].'import_annuaire_csv.php','true','sub_content','Import de contacts depuis un fichier CSV ');

$import_annuaire_csv['css_admin'][0] = 'modules/'.$import_annuaire_csv['folder_name'].'themes/admin_fr/css/import_annuaire_csv.css';

$import_annuaire_csv['js_admin'][0] = 'modules/'.$import_annuaire_csv['folder_name'].'themes/admin_fr/javascript/import_annuaire_csv.js';


$import_annuaire_csv['liste_entete'] 	= array(
				array(
						"main_lib" => "Informations g�n�rales",
						"champs" => array(
						array("lib"=>'Nom:' 					,"id"	=>'nom', 					"multiple" => "3","correps" => array("nom", "prenom", "pr�nom")),
						array("lib"=>'Cat�gorie:'			,"id"	=>'id_categorie', "id_type" => "o", "correps" => array("type", "entreprise", "id_categorie")),
						array("lib"=>'Civilit�:' 			,"id"	=>'id_civilite', "id_type" => "o", 	"correps" => array("civilite", "civilit�", "id_civilite")),
						array("lib"=>'Siret:' 				,"id"	=>'siret', 													"correps" => array("siret", "siren")),
						array("lib"=>'TVA intra:'	 		,"id"	=>'tva_intra', 											"correps" => array("tva", "tva intra")),
						array("lib"=>'Note g�n�rale:'	,"id"	=>'note_gen', 	"multiple" => "3", 	"correps" => array("informations", "info"))
						)
						)
						,
						
				array(
						"main_lib" => "Coordonn�es",
						"champs" => array(
						array("lib"=>'Libell� Coordonn�es:',"id"	=>'lib_coord', 										"correps" => array("coordonn�e", "contact")),
						array("lib"=>'T�l�phone 1:'			,"id"	=>'tel1', 														"correps" => array("tel_1", "tel", "t�l")),
						array("lib"=>'T�l�phone 2:'			,"id"	=>'tel2', 														"correps" => array("tel_2", "tel 2", "t�l2")),
						array("lib"=>'Email:'						,"id"	=>'email', 														"correps" => array("email", "mail")),
						array("lib"=>'Fax:'						,"id"	=>'fax', 															"correps" => array("fax")),
						array("lib"=>'Note coordonn�es:',"id"	=>'coord_note', 	"multiple" => "3", 	"correps" => array("coord_note"))
						)
						)
						,
						
				array(
						"main_lib" => "Adresse",
						"champs" => array(
						array("lib"=>'Libell� Adresse:'	,"id"	=>'lib_adresse', 												"correps" => array("lib_adresse")),
						array("lib"=>'Adresse:'					,"id"	=>'adresse', 				"multiple" => "3", 	"correps" => array("adresse", "adresse1", "adresse2")),
						array("lib"=>'Code postal:'  		,"id"	=>'adresse_cp', 												"correps" => array("code", "code postal", "zip", "code zip")),
						array("lib"=>'Ville:'		  			,"id"	=>'adresse_ville', 											"correps" => array("ville", "city")),
						array("lib"=>'Pays:'	 				 	,"id"	=>'id_pays', "id_type" => "o", 					"correps" => array("pays", "country", "id_pays")),
						array("lib"=>'Adresse note:'		,"id"	=>'adresse_note', "multiple" => "3", 		"correps" => array("adresse_note"))
						)
						)
						,
						
				array(
						"main_lib" => "Site",
						"champs" => array(
						array("lib"=>'Libell� Site:'		,"id"	=>'lib_site', 										"correps" => array("lib_site")),
						array("lib"=>'URL site:'				,"id"	=>'url', 													"correps" => array("url", "site", "web")),
						array("lib"=>'Login:'						,"id"	=>'login',												"correps" => array("login")),
						array("lib"=>'Mot de passe:'		,"id"	=>'pass', 												"correps" => array("pass", "mdp", "mot de passe")),
						array("lib"=>'Note site:'				,"id"	=>'note_site', "multiple" => "3", "correps" => array("note_site"))
						)
						)
						,
						
				array(
						"main_lib" => "INFORMATIONS CLIENT",
						"id_profil" => $CLIENT_ID_PROFIL,
						"champs" => array(
						array("lib"=>'Cat�gorie de client:'			,"id"	=>'id_client_categ', "id_type" => "o",	"correps" => array("id_client_categ", "cat�gorie client")),
						array("lib"=>'Type:'										,"id"	=>'type', 						"id_type" => "o", "correps" => array("type", "propect")),
						array("lib"=>'Grille tarifaire'					,"id"	=>'id_tarif', 				"id_type" => "o", "correps" => array("tarifs", "id_tarif")),
						array("lib"=>'Facturation p�riodique:'	,"id"	=>'facturation', 			"id_type" => "o", "correps" => array("facturation")),
						array("lib"=>'Encours:'									,"id"	=>'encours', 														"correps" => array("encours")),
						array("lib"=>'D�lai de r�glement des factures:',"id"	=>'delai_reglement', 						"correps" => array("r�glement �")),
						array("lib"=>'Compte Compta:'			,"id"	=>'defaut_numero_compte', 										"correps" => array("compte"))
						)
						)
						,
						
				array(
						"main_lib" => "INFORMATIONS FOURNISSEUR",
						"id_profil" => $FOURNISSEUR_ID_PROFIL,
						"champs" => array(
						array("lib"=>'Cat�gorie de fournisseur:',"id"	=>'id_fournisseur_categ', "id_type" => "o", "correps" => array("id_fournisseur_categ")),
						array("lib"=>'Identifiant revendeur:'		,"id"	=>'identifiant', 													"correps" => array("identifiant")),
						array("lib"=>'Condition commerciales:'	,"id"	=>'conditions_commerciales', 							"correps" => array("conditions_commerciales")),
						array("lib"=>'Lieu de livraison:'				,"id"	=>'id_stock_livraison', "id_type" => "o", "correps" => array()),
						array("lib"=>'D�lai de livraison:'			,"id"	=>'delai_livraison', 										"correps" => array("delai_livraison")),
						array("lib"=>'Compte Compta:'			,"id"	=>'defaut_numero_compte', 										"correps" => array("compte"))
						)
						)
						,
						
				array(
						"main_lib" => "INFORMATIONS CONSTRUCTEUR",
						"id_profil" => $CONSTRUCTEUR_ID_PROFIL,
						"champs" => array(
						array("lib"=>'R�f�rence revendeur:'			,"id"	=>'identifiant_revendeur', 							"correps" => array()),
						array("lib"=>'Conditions de garantie:'	,"id"	=>'conditions_garantie', 								"correps" => array())
						)
						)
						
						);
	
?>