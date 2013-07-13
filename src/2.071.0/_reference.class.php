<?php

// *************************************************************************************************************
// CLASSE PERMETTANT DE GENERER LES REFERENCES UNIQUES
// *************************************************************************************************************


$CHECK_EXISTING_REF = 1;	// V�rification double de l'existence d'une r�f�rence avant sa cr�ation



final class reference {

	private $id_reference;				// Identifiant de la cat�gorie de r�f�rences
	private $lib_reference;				// Libell�

	private $lib_table;						// Table principale o� sera ins�r� la r�f�rence et o� elle doit �tre unique
	private $champs;							// Champs dans la table

	private $prefixe;							// Chaine fixe permettant d'identifier la cat�gorie
	private $ref_rules;						// R�gle de g�n�ration de la r�f�rence 
	private $last_id;							// Dernier id pour la g�n�ration d'une r�f�rence unique


// Chargement des infos sur la r�f�rence
function __construct($id_reference) {
	global $bdd;

	if (!is_numeric($id_reference)) {
		$erreur = "R�f�rence non num�rique appel�e. [".$id_reference."]";
		alerte_dev ($erreur);
	}

	// Selection des informations sur la r�f�rence
	$query = "SELECT id_reference, lib_reference, lib_table, champs, prefixe, ref_rules, last_id
						FROM references_tags
						WHERE id_reference = '".$id_reference."' ";
	$result = $bdd->query ($query);
  if (!$reference = $result->fetchObject()) {
    $erreur = "R�f�rence invalide appel�e. [".$id_reference."]";
		alerte_dev ($erreur);
  }
  
  $this->id_reference 	= $reference->id_reference;
  $this->lib_reference 	= $reference->lib_reference;
  $this->lib_table		 	= $reference->lib_table;
  $this->champs 				= $reference->champs;
  $this->prefixe 				= $reference->prefixe;
  $this->ref_rules 			= $reference->ref_rules;
  $this->last_id 				= $reference->last_id;
} 


// Cr�ation d'une r�f�rence unique
// Ne fonctionne pas si X = 0 !!
private function calculer_ref ($id = 0) {
	global $bdd;

	if (!$id) { $id = $this->last_id + 1; }

	// X repr�sente le nombre de caract�res en base 36 : 0-9 puis A-Z
	$x = substr ($this->ref_rules, 0, 1);
	// Y repr�sente le nombre de caract�res en base 10
	$y = substr ($this->ref_rules, 2);

  // Valeures maximales
  $max_base_36 = pow(36, $x);
  $max_base_10 = pow(10, $y);
  $max_id = $max_base_10 * $max_base_36;

  if ($id >= $max_id) { // 0 est une des $max_id valeur
  	$erreur = "	La valeur limite pour la r�f�rence [".$this->id_reference."] est atteinte !<br>
								Valeur : ".$id." <br>
								R�gle : ".$this->ref_rules;
		alerte_dev ($erreur);
  }

  $first_part = base_convert(floor($id / $max_base_10), 10, 36);
  if (!$first_part) $first_part='';

  $second_part = $id - (floor($id / $max_base_10)*$max_base_10);
  if(!$second_part) $second_part='';

  for ($i=strlen($first_part); $i<$x; $i++) {
    $first_part = "0".$first_part;
  }
  for ($i=strlen($second_part); $i<$y; $i++) {
    $second_part = "0".$second_part;
  }
  
  $ref = $this->prefixe."-".$_SERVER['REF_DOC']."-".$first_part.$second_part;
  
  return $ref;
}



// G�n�re une ref�rence unique
function generer_ref ($id = 0) {
	global $bdd;
	global $CHECK_EXISTING_REF;

	if (!$id) { $id = $this->last_id+1; }

	
	// Calcul de la r�f�rence
	$ref_ok = 0;
	while (!$ref_ok) {
		$ref = $this->calculer_ref($id);
		if ( $CHECK_EXISTING_REF && !$this->ref_is_free ($ref) ) {
			$id ++;
			continue;
		}
		
		$ref_ok = 1;
		// Mise � jour du dernier ID utilis�
  	$query = "UPDATE references_tags SET last_id = '".$id."'
  						WHERE id_reference = '".$this->id_reference."' ";
  	$bdd->exec($query);
	}

	return $ref;
}



// V�rification de l'existence d'une r�f�rence
function ref_is_free ($ref) {
	global $bdd;
	
	$query = "SELECT ".$this->champs." FROM ".$this->lib_table."
						WHERE ".$this->champs." = '".$ref."' ";
	$result = $bdd->query ($query);
  if (!$reference = $result->fetchObject()) {
		return true;
	}
	
	return false;
}

} 