<?php
// *************************************************************************************************************
// CONFIG $import_catalogue_csv 
// *************************************************************************************************************


$import_catalogue_csv['folder_name'] = "import_catalogue_csv/";

$import_catalogue_csv['import_images_folder'] = "import_catalogue/";

$import_catalogue_csv['menu_admin'][0]		= array('separateur','','true','','');
$import_catalogue_csv['menu_admin'][1]		=  array('import_catalogue_csv','modules/'.$import_catalogue_csv['folder_name'].'import_catalogue_csv.php','true','sub_content','Import d\'articles depuis un fichier CSV ');

$import_catalogue_csv['css_admin'][0] = 'modules/'.$import_catalogue_csv['folder_name'].'themes/admin_fr/css/import_catalogue_csv.css';

$import_catalogue_csv['js_admin'][0] = 'modules/'.$import_catalogue_csv['folder_name'].'themes/admin_fr/javascript/import_catalogue_csv.js';


$import_catalogue_csv['liste_entete'] 	= array(
				array(
						"main_lib" => "Description",
						"champs" => array(
						array("lib"=>'Libell�:' 					,"id"	=>'lib_article', 	"multiple" => "3","correps" => array("libelle", "article")),
						array("lib"=>'Libell� court:'			,"id"	=>'lib_ticket',  "correps" => array()),
						array("lib"=>'Description courte:' ,"id"	=>'desc_courte', "multiple" => "3", "correps" => array("description")),
						array("lib"=>'Description Longue:' ,"id"	=>'desc_longue',  "multiple" => "3", "correps" => array()),
						array("lib"=>'R�f�rence Interne:'	 ,"id"	=>'ref_interne', 										"correps" => array("reference")),
						array("lib"=>'R�f�rence constructeur:'	,"id"	=>'ref_oem', 										"correps" => array("eom")),
						array("lib"=>'Constructeur:'	 ,"id"	=>'ref_constructeur', "id_type" => "o", "correps" => array("constructeur", "fabricant"))
						)
						)
						,
						
				array(
						"main_lib" => "Gestion",
						"champs" => array(
						array("lib"=>'Date de d�but de disponibilit�:',"id"	=>'date_debut_dispo', "correps" => array()),
						array("lib"=>'Date de fin de disponibilit�:',"id"	=>'date_fin_dispo', 		"correps" => array()),
						array("lib"=>'Suivi des num�ros de s�rie:'	,"id"	=>'gestion_sn', "id_type" => "o", "correps" => array()),
						array("lib"=>'Valorisation:'			,"id"	=>'id_valo', "id_type" => "o", 			"correps" => array()),
						array("lib"=>'Code barre: '			,"id"	=>'code_barre', 		"multiple" => "3", 	"correps" => array())
						)
						)
						,
						
				array(
						"main_lib" => "Mat�riel",
						"champs" => array(
						array("lib"=>'Poids:'							,"id"	=>'poids', 					"correps" => array("poids")),
						array("lib"=>'Colisage:'					,"id"	=>'colisage', 	 		"correps" => array("colisage")),
						array("lib"=>'Dur�e de Garantie:' ,"id"	=>'duree_garantie', "correps" => array())
						)
						),
						
				array(
						"main_lib" => "Images",
						"champs" => array(
						array("lib"=>'Nom de l\'image:'		,"id"	=>'image',"multiple" => "3", 	"correps" => array())
						)
						)
						,
						
				array(
						"main_lib" => "Tarifs",
						"champs" => array(
						array("lib"=>'Prix public HT:'		,"id"	=>'prix_public_ht', 	"correps" => array()),
						array("lib"=>'Taux de T.V.A.:'				,"id"	=>'tva', 			"correps" => array()),
						array("lib"=>'Prix d\'achat actuel HT:' ,"id"	=>'paa_ht',		"correps" => array())
						)
						)
						
						
						
						);
						
						
?>