<?php
$_TEST_MOD=0;

if ($_TEST_MOD==1){
	ini_set('display_errors', 'On');
	error_reporting(E_ALL);
}
header('Content-Type: text/html; charset=utf-8');

/***************** PARAMETERS *****************/
$answers_file = "private/reponses.csv";
$_USERS_FILE = "private/collecteurs.csv";
date_default_timezone_set('Europe/Paris');
$VOTE_END_DATE = strtotime('2018-01-01 12:00:00');
$VOTE_END_DATE_STRING = date('d/m/Y à  H\hi',$VOTE_END_DATE);
$_URL = "https://www.dumaine.me/www/pas_dashboard/index.php";

$_USERS = array();

/***************** MAIN ENTRY POINT / CONTROLER *****************/
function main(){
	global $VOTE_END_DATE;
	init_user_list();
	//var_dump($_USERS);

	if (isset($_GET['action'])  && get_session_hash() && exists_user()){
		$admin_status = get_admin_status();
		if ($_GET['action']=='list_answers')
			if ($admin_status==True)
				list_answers();
			else
				admin_required_view();
		if ($_GET['action']=='vote'){
			if ($VOTE_END_DATE < strtotime('now')) {
				vote_closed_view();
			} else { 
				if (isset($_POST['submit'])){
					add_vote();
				}else{
					if (isset($_POST['rebuild_answer_file'])){
						if ($admin_status==True)
							 rebuild_answers_file();
						else
							admin_required_view();
					}
					else {
						$last_record = get_last_answer(get_user_login(),get_user_application());
						display_form($last_record);
					}
				}
			}
		}
	} else {
		echo "Pas d'action, pas de hash ou utilsateur inconnu.";
	}
}

/***************** MODEL *****************/
function init_user_list(){
    global $_USERS;
    global $_USERS_FILE;
    if (($handle = fopen($_USERS_FILE, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
            $hash = $data[0];
            $login = $data[1];
            $admin_bool = $data[2];
			if ($admin_bool=="True")
				$admin_bool=True;
			else
				$admin_bool=False;
            $name = $data[3];
            $application = $data[4];
			$temp=array($login,$admin_bool,$name,$application);
            $_USERS[$hash]=$temp;
        }   
        fclose($handle);
    }
}
function exists_user(){
	global $_USERS;
	return array_key_exists(get_session_hash(), $_USERS);
}
function get_user_login(){
	global $_USERS;
	return $_USERS[get_session_hash()][0];
}
function get_admin_status(){
	global $_USERS;
	return $_USERS[get_session_hash()][1];
}
function get_user_name(){
	global $_USERS;
	return $_USERS[get_session_hash()][2];
}
function get_user_application(){
	global $_USERS;
	return $_USERS[get_session_hash()][3];
}

function get_session_hash(){
	if (isset($_GET['hash']))
		return $_GET['hash'];
	else
		if (isset($_POST['hash']))
			return $_POST['hash'];
		else
			return false;
}


function rebuild_answers_file(){
	global $answers_file;
	rename($answers_file, $answers_file.'_'.get_date());

	$t=array("date", "login", "application", "primary_key");
	foreach ($_POST as $key=>$value)
		if ($t != "submit" && $t != "rebuild_answer_file")
			array_push($t,$key);

    if (($handle = fopen($answers_file, "w")) !== FALSE) {
		fputcsv($handle, $t,";");
        fclose($handle);
		new_answers_file_build_view();
	}

}

function get_last_answer($targeted_login,$targeted_application){
	global $answers_file;
	$last_answers = [];
	$last_answers_date = null;

    if (($handle = fopen($answers_file, "r")) !== FALSE) {
		$headers = fgetcsv($handle, 1000, ";");
		array_shift($headers); //unstack composed_key
		array_shift($headers); //unstack timestamp
		array_shift($headers); //unstack login
		array_shift($headers); //unstack application

        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
			$index =0;
			if (($data[2] == $targeted_login) && ($data[3]==$targeted_application)){
				foreach ($headers as $h){
					$last_answers[$h] = $data[$index+4];
					$index++;
				}
				$last_answers_date = $data[1];
			}
        }   
        fclose($handle);
    }
	return array($last_answers_date,$last_answers);
}


function get_date(){
	return date("dMY_His");
}


function add_vote(){
	global $answers_file;
	$line_to_add = array(get_date().'-'.get_user_login().'-'.get_user_application(),get_date(), get_user_login(),get_user_application());

    if (($handle = fopen($answers_file, "r")) !== FALSE) {
		$headers = fgetcsv($handle, 1000, ";");
		array_shift($headers); //unstack composed_key
		array_shift($headers); //unstack timestamp
		array_shift($headers); //unstack login
		array_shift($headers); //unstack application
		foreach ($headers as $h){
			if (array_key_exists($h, $_POST)){
				array_push($line_to_add, $_POST[$h]);
			}
        }   
        fclose($handle);
    }

    if (($handle = fopen($answers_file, "a")) !== FALSE) {
		fputcsv($handle, $line_to_add,";");
        fclose($handle);
    }
}



/***************** VIEW *****************/

function list_answers(){
    global $answers_file;
    if (($handle = fopen($answers_file, "r")) !== FALSE) {
        $r = fread($handle,filesize($answers_file));
		echo $r;
        fclose($handle);
    }
}

function display_form($last_record){
	global $_URL;
	$last_answer_date = $last_answer[0];
	$answer_array = $last_answer[1];

	function get_answer_from_key($key, $answer_array){
		//global $answer_array;
		if (array_key_exists($key, $answer_array))
			return $answer_array[$key];
		else
			return '';
	}
	
echo '<html>
<head>
	<title>PAS - Données projet</title>
	<meta charset="UTF-8" />
    <link href="jquery-ui-1.12.1.custom/jquery-ui.min.css" rel="stylesheet" />
</head>
<body>
';


	if (get_admin_status()==True) {
		echo '<br><br><b>Menu administrateur :</b>
		<br><form  method="POST" action="'.$_URL.'?action=list_answers&hash='.get_session_hash().'"><input type="submit" name="k" value="Voir les réponses" /></form>
		<form name="world" id="world" method="POST" action="'.$_URL.'?hash='.get_session_hash().'&action=vote">
		<input type="submit" name="rebuild_answer_file" value="Regénérer le fichier de réponses"/>
		<br><br>';
	}

	echo '

	<h1>Prélèvement à la source - Collecte de données projet</h1>
	Organisme : '.get_user_name().' <br>
	Application : '.get_user_application().' <br>
	Date de la dernière réponse : '.$last_answer_date.' 

NOTICE : les dates renseignées sont prévisionnéelles si elles osnt situées dna sle futur par rapport à la date de remplissage du questionnaire. Si non ce sont les date effectives.

	<br><br>
	<h2>Périmètre</h2>
	<table>
	<tr style="text-align:center;"><td>Questions</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>

// PRODUIT MAISON OU PAS<br>
// Si éditeur, quel éditeur<br>
// CIRCUIT : DSN ou PASRAU<br>
//ECHAPPER via fput
	<tr><td>Nombre de bénéficiaires</td>
		<td style="text-align:center;">'.get_answer_from_key('perimetre_nb_beneficiaires',$answer_array).'</td>
		<td><input type="number" name="perimetre_nb_beneficiaire" value="'.get_answer_from_key('perimetre_nb_beneficiaire',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Nombre d\'usagers dont le NIR est connu</td>
		<td style="text-align:center;">'.get_answer_from_key('identification_nb_nir_connus',$answer_array).'</td>
		<td><input type="date" name="identification_nb_nir_connus" value="'.get_answer_from_key('identification_nb_nir_connus',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Nombre d\'usagers dont le NIR est certifié</td>
		<td style="text-align:center;">'.get_answer_from_key('identification_nb_nir_certifies',$answer_array).'</td>
		<td><input type="date" name="identification_nb_nir_certifies" value="'.get_answer_from_key('identification_nb_nir_certifies',$answer_array).'" style="text-align:center;"/></td>
	</tr>

	<tr><td>Total des sommes versées sur l\'année (qui auraient été soumises au PAS au 1/1/2018)</td>
		<td style="text-align:center;">'.get_answer_from_key('perimetre_assiette',$answer_array).'</td>
		<td><input type="number" name="perimetre_assiette" value="'.get_answer_from_key('perimetre_assiette',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>

	<h2>Avancement dans les jalons projet</h2>
	<table>
	<tr>
		<td>ID</td>
		<td>Nom du lot</td>
		<td>Date de fin de développements et de la recette unitaire (effective ou prévisionnelle)</td>
		<td>Date d\'entrée en "pilote" (effective ou prévisionnelle)</td>
		<td>Date de fin de VABF (effective ou prévisionnelle)</td>
		<td>Date de fin de VSR (effective ou prévisionnelle)</td>
//AJOUTER UNE COLONNE NOIR / SEPARATEUR
		<td>Nombre d\'anomalies bloquantes ouvertes à date</td>
		<td>Nombre d\'anomalies total ouvertes à date</td>
	</tr>

Si lotissement du projet en plusieurs lots, merci de remplir le tableau suivant selon ce lotissement.

	<tr>
		<td>#1</td>
		<td><input type="text" name="lot1_nom" value="'.get_answer_from_key('lot1_nom',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot1_date_fin_dev" id="lot1_date_fin_dev" value="'.get_answer_from_key('lot1_date_fin_dev',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot1_date_entree_pilote" value="'.get_answer_from_key('lot1_date_entree_pilote',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot1_date_fin_VABF" value="'.get_answer_from_key('lot1_date_fin_VABF',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot1_date_fin_VSR" value="'.get_answer_from_key('lot1_date_fin_VSR',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot1_nb_anomalies_bloquantes" value="'.get_answer_from_key('lot1_nb_anomalies_bloquantes',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot1_nb_anomalies_total" value="'.get_answer_from_key('lot1_nb_anomalies_total',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr>
		<td>#2</td>
		<td><input type="text" name="lot2_nom" value="'.get_answer_from_key('lot2_nom',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot2_date_fin_dev" value="'.get_answer_from_key('lot2_date_fin_dev',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot2_date_entree_pilote" value="'.get_answer_from_key('lot2_date_entree_pilote',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot2_date_fin_VABF" value="'.get_answer_from_key('lot2_date_fin_VABF',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot2_date_fin_VABF" value="'.get_answer_from_key('lot1_date_fin_VABF',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot2_nb_anomalies_bloquantes" value="'.get_answer_from_key('lot2_nb_anomalies_bloquantes',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot2_nb_anomalies_total" value="'.get_answer_from_key('lot2_nb_anomalies_total',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr>
		<td>#3</td>
		<td><input type="text" name="lot3_nom" value="'.get_answer_from_key('lot3_nom',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot3_date_fin_dev" value="'.get_answer_from_key('lot3_date_fin_dev',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot3_date_entree_pilote" value="'.get_answer_from_key('lot3_date_entree_pilote',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot3_date_fin_VABF" value="'.get_answer_from_key('lot3_date_fin_VABF',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot3_date_fin_VABF" value="'.get_answer_from_key('lot1_date_fin_VABF',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot3_nb_anomalies_bloquantes" value="'.get_answer_from_key('lot3_nb_anomalies_bloquantes',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot3_nb_anomalies_total" value="'.get_answer_from_key('lot3_nb_anomalies_total',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>

	<br><br>
	<table>
	<tr style="text-align:center;"><td>Questions</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr><td>Déclaration de conformité réalisée auprès de la CNIL</td>
		<td style="text-align:center;">'.get_answer_from_key('date_mep_flux',$answer_array).'</td>
// TYPE : OUI / NON
		<td><input type="date" name="date_mep_flux" value="'.get_answer_from_key('date_mep_flux',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Date de mise en production du lot de gestion des flux (réelle ou prévisionnelle)</td>
		<td style="text-align:center;">'.get_answer_from_key('date_mep_flux',$answer_array).'</td>
		<td><input type="date" name="date_mep_flux" value="'.get_answer_from_key('date_mep_flux',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Date effective de l\'initialisation des taux (réelle ou prévisionnelle)</td>
		<td style="text-align:center;">'.get_answer_from_key('date_init_taux',$answer_array).'</td>
		<td><input type="date" name="date_init_taux" value="'.get_answer_from_key('date_init_taux',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Date de mise en production du lot de prélèvement (réelle ou prévisionnelle)</td>
		<td style="text-align:center;">'.get_answer_from_key('date_mep_prelevement',$answer_array).'</td>
		<td><input type="date" name="date_mep_prelevement" value="'.get_answer_from_key('date_mep_prelevement',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Date effective du premier prélèvement (réelle ou prévisionnelle)</td>
		<td style="text-align:center;">'.get_answer_from_key('date_premier_prelevement',$answer_array).'</td>
		<td><input type="date" name="date_premier_prelevement" value="'.get_answer_from_key('date_premier_prelevement',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>


	<h2>Éléments budgétaires</h2>
	<table>
	<tr style="text-align:center;"><td>Questions</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr><td>Budget SI MOE prévu pour le projet (interne et externe, toutes phases confondues)</td>
		<td style="text-align:center;">'.get_answer_from_key('budget_total',$answer_array).'</td>
		<td><input type="number" name="budget_total" value="'.get_answer_from_key('budgte_total',$answer_array).'" style="text-align:center;"/></td>
	</tr>
//Dissocier interne en jh et externe en euros
	<tr><td>Budget SI MOE actuellement engagé (interne et externe, toutes phases confondues)</td>
		<td style="text-align:center;">'.get_answer_from_key('budget_engage',$answer_array).'</td>
		<td><input type="number" name="budget_engage" value="'.get_answer_from_key('budget_engage',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>


	<h2>Points ouverts</h2>
	<table>
	<tr style="text-align:center;"><td>Questions</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr><td>Nombre de points ouverts fonctionnelles en instance auprès de la DGFiP</td>
		<td style="text-align:center;">'.get_answer_from_key('nb_questions_fonctionnelles',$answer_array).'</td>
		<td><input type="number" name="nb_questions_fonctionnelles" value="'.get_answer_from_key('nb_questions_fonctionnelles',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Nombre de points ouverts techniques en instance auprès du GIP MDS</td>
		<td style="text-align:center;">'.get_answer_from_key('nb_questions_techniques',$answer_array).'</td>
		<td><input type="number" name="nb_questions_techniques" value="'.get_answer_from_key('nb_questions_techniques',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>

	<br><br>
	<h2>Suivi de pratiques de sécurisation des paiements</h2>
	<table>
	<tr style="text-align:center;"><td>Questions</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
// OUI / NON
// SI oui date de début
// Si oui date de fin

	<tr><td>Période de paiement à blanc</td>
		<td style="text-align:center;">'.get_answer_from_key('periode_paiement_blanc',$answer_array).'</td>
		<td><input type="number" name="periode_paiement_blanc" value="'.get_answer_from_key('periode_paiement_blanc',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Période de paiement en double</td>
		<td style="text-align:center;">'.get_answer_from_key('periode_paiement_double',$answer_array).'</td>
		<td><input type="number" name="periode_paiement_double" value="'.get_answer_from_key('periode_paiement_double',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Période de correction</td>
		<td style="text-align:center;">'.get_answer_from_key('periode_correction',$answer_array).'</td>
		<td><input type="number" name="periode_paiement_correction" value="'.get_answer_from_key('periode_paiement_courrection',$answer_array).'" style="text-align:center;"/></td>
	</tr>

	<tr><td>Possibilité de paramétrer une date d\'activation du prélèvement</td>
// OUI / NON
		<td style="text-align:center;">'.get_answer_from_key('process_crise_desactiver_prelevement',$answer_array).'</td>
		<td><input type="date" name="process_crise_desactiver_prelevement" value="'.get_answer_from_key('process_crise_desactiver_prelevement',$answer_array).'" style="text-align:center;"/></td>
	</tr>

	</table>


	<br><br>
	<h2>Préparation du processus de prélèvement de l\'impôt collecté par la DGFiP</h2>
	<table>
	<tr style="text-align:center;"><td>Questions</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
// Besoin de création d\'un SIRET : OUI / NON
// Nouveau SIRET effectivement créé par l\'INSEE : OUI / NON

	<tr><td>Date de création des SIRET à l\'INSEE</td>
		<td style="text-align:center;">'.get_answer_from_key('creation_siret_insee',$answer_array).'</td>
		<td><input type="date" name="creation_siret_insee" value="'.get_answer_from_key('creation_siret_insee',$answer_array).'" style="text-align:center;"/></td>
	</tr>
// INSCRIPTION sur netentreprise : OUI / NON
// INSCIRPTION de tous les SIRET dans la déclaration PASRAU/DSN : OUI / NON
	<tr><td>Date d\'inscription sur NetEntreprise</td>
		<td style="text-align:center;">'.get_answer_from_key('inscription_net_entreprise',$answer_array).'</td>
		<td><input type="date" name="inscription_net_entreprise" value="'.get_answer_from_key('incription_net_entreprise',$answer_array).'" style="text-align:center;"/></td>
	</tr>
//VALIDATION par l\'agence comptable : OUI / NON
	</table>


	<br><br>
	<h2>Formalisation du processus de gestion de crise</h2>
	<table>
	<tr style="text-align:center;"><td>Questions</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>

// Identification de solutions de fonctionnement dégradé afin d\'assurer la continuité des versements aux bénéficiaires : OUI / NON
// Identification de solutions de fonctionnement dégradé afin d\'assurer la continuité des prélèvements par la DGFIP de l\'impôt collecté : OUI / NON

	<tr><td>Organisation de la cellule de cris et identification de ses membres de la cellule de crise</td>
OUI/NON
		<td style="text-align:center;">'.get_answer_from_key('process_crise_membres_cellule_crise',$answer_array).'</td>
		<td><input type="date" name="process_crise_membres_cellule_crise" value="'.get_answer_from_key('process_crise_membres_cellule_crise',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>



	<br><br>
	<h2>Données d\'identification</h2>
	<table>
	<tr style="text-align:center;"><td>Questions</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
<!--
	<tr><td>Nombre d\'usagers</td>
		<td style="text-align:center;">'.get_answer_from_key('identification_nb_usagers',$answer_array).'</td>
		<td><input type="date" name="identification_nb_usagers" value="'.get_answer_from_key('identification_nb_usagers',$answer_array).'" style="text-align:center;"/></td>
	</tr>
-->
	</table>
<script src="jquery-ui-1.12.1.custom/external/jquery/jquery.js"></script>
<script src="jquery-ui-1.12.1.custom/jquery-ui.min.js"></script>
<script>
   (function() {
      var elem = document.createElement("input");
      elem.setAttribute("type", "date");
 
      if ( elem.type === "text" ) {
         $("#lot1_date_fin_dev").datepicker(); 
      }
   })();
</script>

	<br>
	<input type="submit" name="submit" style="width: 150px; height: 35px; display:block; margin:auto;" value="Valider"/>
	</form>
</body>
</html>';
}

function vote_closed_view(){
	echo 'Le vote est fermé.';
}
function admin_required_view(){
	echo 'Droits administrateur requis. Tu n\'as pas de droits  administrateur '.get_user_name().'.';
}

function new_answers_file_build_view(){
	echo 'L\'ancien fichier de réponse a été archivé et le nouveau généré.';
}
main();
?>
