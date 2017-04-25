<?php
$_TEST_MOD=1;

if ($_TEST_MOD==1){
	ini_set('display_errors', 'On');
	error_reporting(E_ALL);
}
header('Content-Type: text/html; charset=utf-8');

// PARAMETERS
$answers_file = "private/reponses.csv";
$_USERS_FILE = "private/collecteurs.csv";
date_default_timezone_set('Europe/Paris');
$VOTE_END_DATE = strtotime('01/01/2018');
$VOTE_END_DATE_STRING = date('d/m/Y à  H\hi',$VOTE_END_DATE);
$_URL = "https://www.dumaine.me/www/pas_dashboard/index.php";

$_USERS = array();


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
			$temp=array($login,$admin_bool,$name);
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

function get_session_hash(){
	if (isset($_GET['hash']))
		return $_GET['hash'];
	else
		if (isset($_POST['hash']))
			return $_POST['hash'];
		else
			return false;
}

init_user_list();
//var_dump($_USERS);

$t="";
foreach ($_POST as $key=>$value)
	$t.=$key.";";
print $t;

function get_last_answer($targeted_login){
	global $answers_file;
	$last_answers = [];
	$last_answers_date = null;

    if (($handle = fopen($answers_file, "r")) !== FALSE) {
		$headers = fgetcsv($handle, 1000, ";");
		array_shift($headers); //unstack timestamp
		array_shift($headers); //unstack login

        while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
			$index =0;
			if ($data[1] == $targeted_login){
				foreach ($headers as $h){
					$last_answers[$h] = $data[$index+2];
					$index++;
				}
				$last_answers_date = $data[0];
			}
        }   
        fclose($handle);
    }
	return array($last_answers_date,$last_answers);
}


function add_vote(){
	global $answers_file;
	$line_to_add = array(date("d/M/Y H:i:s "), get_user_login());

    if (($handle = fopen($answers_file, "r")) !== FALSE) {
		$headers = fgetcsv($handle, 1000, ";");
		array_shift($headers); //unstack timestamp
		array_shift($headers); //unstack login
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

if (isset($_GET['action'])  && get_session_hash() && exists_user()){
	$admin_status = get_admin_status();
	if ($_GET['action']=='list_results')
		if ($admin_status==True)
			list_result();
		else
			admin_required_view();
	if ($_GET['action']=='vote'){
		if ($VOTE_END_DATE < strtotime('now')) {
			vote_closed_view();
		} else { 
			if (isset($_POST['submit'])){
				add_vote();
			}else{
				$last_answer = get_last_answer(get_user_login());
				display_form($last_answer);
			}
		}
	}
} else {
	echo "Pas d'action, pas de hash ou utilsateur inconnu.";
}

// VUE


function display_form($last_answer){
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
</head>
<body>
';

/*
	if (get_admin_status()==True) {
		echo '<br><br><b>Menu administrateur :</b>
		<br><a href="'.$_URL.'?action=list_results&hash='.get_session_hash().'">Voir l\'historique des votes.</a>
		<br><a href="'.$_URL.'?action=count_results&hash='.get_session_hash().'">Voir le classement.</a>
		<br><br>';
	}
*/
	echo '
	<form name="world" id="world" method="POST" action="'.$_URL.'?hash='.get_session_hash().'&action=vote">
<script src="//cdn.jsdelivr.net/webshim/1.14.5/polyfiller.js"></script>
<script>
    webshims.setOptions("forms-ext", {types: "date"});
webshims.polyfill("forms forms-ext");
</script>

	<h1>Prélèvement à la source - Collecte de données projet</h1>
	Organisme : '.get_user_name().' <br>
	Dernière réponse : '.$last_answer_date.' 

	<br><br>
	<h2>Périmètre</h2>
	<table>
	<tr style="text-align:center;"><td>Question</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr><td>Nombre de bénéficiaires</td>
		<td style="text-align:center;">'.get_answer_from_key('perimetre_nb_beneficiaires',$answer_array).'</td>
		<td><input type="number" name="perimetre_nb_beneficiaire" value="'.get_answer_from_key('perimetre_nb_beneficiaire',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Total des sommes versées sur l\'année 2016  potentiellement imposables</td>
		<td style="text-align:center;">'.get_answer_from_key('perimetre_assiette',$answer_array).'</td>
		<td><input type="number" name="perimetre_assiette" value="'.get_answer_from_key('perimetre_assiette',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>

	<h2>Avancement dans les jalons projet</h2>
	<table>
	<tr>
		<td>ID</td>
		<td>Nom du lot</td>
		<td>Date de fin de développements</td>
		<td>Date de fin des tests fabriquant</td>
		<td>Date de fin de la recette fonctionnelle assemblée</td>
		<td>Date d\'entrée en pilote</td>
		<td>Nombre d\'anomalies bloquantes ouvertes</td>
		<td>Nombre d\'anomalies total ouvertes</td>
	</tr>
	<tr>
		<td>#1</td>
		<td><input type="text" name="lot1_nom" value="'.get_answer_from_key('lot1_nom',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot1_date_fin_dev" value="'.get_answer_from_key('lot1_date_fin_dev',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot1_date_fin_test_fab" value="'.get_answer_from_key('lot1_date_fin_test_fab',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot1_date_fin_recette_fonctionnelle" value="'.get_answer_from_key('lot1_date_fin_recette_fonctionnelle',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot1_date_entree_pilote" value="'.get_answer_from_key('lot1_date_entree_pilote',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot1_date_nb_anomalies_bloquantes" value="'.get_answer_from_key('lot1_nb_anomalies_bloquantes',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot1_date_nb_anomalies_total" value="'.get_answer_from_key('lot1_nb_anomalies_total',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr>
		<td>#2</td>
		<td><input type="text" name="lot2_nom" value="'.get_answer_from_key('lot2_nom',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot2_date_fin_dev" value="'.get_answer_from_key('lot2_date_fin_dev',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot2_date_fin_test_fab" value="'.get_answer_from_key('lot2_date_fin_test_fab',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot2_date_fin_recette_fonctionnelle" value="'.get_answer_from_key('lot2_date_fin_recette_fonctionnelle',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot2_date_entree_pilote" value="'.get_answer_from_key('lot2_date_entree_pilote',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot2_date_nb_anomalies_bloquantes" value="'.get_answer_from_key('lot2_nb_anomalies_bloquantes',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot2_date_nb_anomalies_total" value="'.get_answer_from_key('lot2_nb_anomalies_total',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr>
		<td>#3</td>
		<td><input type="text" name="lot3_nom" value="'.get_answer_from_key('lot3_nom',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot3_date_fin_dev" value="'.get_answer_from_key('lot3_date_fin_dev',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot3_date_fin_test_fab" value="'.get_answer_from_key('lot3_date_fin_test_fab',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot3_date_fin_recette_fonctionnelle" value="'.get_answer_from_key('lot3_date_fin_recette_fonctionnelle',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="date" name="lot3_date_entree_pilote" value="'.get_answer_from_key('lot3_date_entree_pilote',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot3_date_nb_anomalies_bloquantes" value="'.get_answer_from_key('lot3_nb_anomalies_bloquantes',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot3_date_nb_anomalies_total" value="'.get_answer_from_key('lot3_nb_anomalies_total',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>

	<br><br>
	<table>
	<tr style="text-align:center;"><td>Question</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr><td>Date de mise en production du lot de gestion des flux</td>
		<td style="text-align:center;">'.get_answer_from_key('date_mep_flux',$answer_array).'</td>
		<td><input type="date" name="date_mep_flux" value="'.get_answer_from_key('date_mep_flux',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Date effective de l\'initialisation des taux</td>
		<td style="text-align:center;">'.get_answer_from_key('date_init_taux',$answer_array).'</td>
		<td><input type="date" name="date_init_taux" value="'.get_answer_from_key('date_init_taux',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Date de mise en production du lot de prélèvement</td>
		<td style="text-align:center;">'.get_answer_from_key('date_mep_prelevement',$answer_array).'</td>
		<td><input type="date" name="date_mep_prelevement" value="'.get_answer_from_key('date_mep_prelevement',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Date effective du premier prélèvement</td>
		<td style="text-align:center;">'.get_answer_from_key('date_premier_prelevement',$answer_array).'</td>
		<td><input type="date" name="date_premier_prelevement" value="'.get_answer_from_key('date_premier_prelevement',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>


	<h2>Éléments budgétaires</h2>
	<table>
	<tr style="text-align:center;"><td>Question</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr><td>Budget total prévu pour le projet (interne et externe, toutes phases confondues)</td>
		<td style="text-align:center;">'.get_answer_from_key('budget_total',$answer_array).'</td>
		<td><input type="number" name="budget_total" value="'.get_answer_from_key('budgte_total',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Budget actuellement engagé (interne et externe, toutes phases confondues)</td>
		<td style="text-align:center;">'.get_answer_from_key('budget_engage',$answer_array).'</td>
		<td><input type="number" name="budget_engage" value="'.get_answer_from_key('budget_engage',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>


	<h2>Questions en instance</h2>
	<table>
	<tr style="text-align:center;"><td>Question</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr><td>Nombre de questions fonctionnelles en instance auprès de la DGFiP</td>
		<td style="text-align:center;">'.get_answer_from_key('nb_questions_fonctionnelles',$answer_array).'</td>
		<td><input type="number" name="nb_questions_fonctionnelles" value="'.get_answer_from_key('nb_questions_fonctionnelles',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Nombre de questions tecniques  en instance auprès du GIP MDS</td>
		<td style="text-align:center;">'.get_answer_from_key('nb_questions_techniques',$answer_array).'</td>
		<td><input type="number" name="nb_questions_techniques" value="'.get_answer_from_key('nb_questions_techniques',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>

	<br><br>
	<h2>Bonnes pratiques de sécurisation des paiements</h2>
	<table>
	<tr style="text-align:center;"><td>Question</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr><td>Durée de la période de paiement à blanc</td>
		<td style="text-align:center;">'.get_answer_from_key('periode_paiement_blanc',$answer_array).'</td>
		<td><input type="number" name="periode_paiement_blanc" value="'.get_answer_from_key('periode_paiement_blanc',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Durée de la période de paiement en double</td>
		<td style="text-align:center;">'.get_answer_from_key('periode_paiement_double',$answer_array).'</td>
		<td><input type="number" name="periode_paiement_double" value="'.get_answer_from_key('periode_paiement_double',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Durée de la période de correction</td>
		<td style="text-align:center;">'.get_answer_from_key('periode_correction',$answer_array).'</td>
		<td><input type="number" name="periode_paiement_correction" value="'.get_answer_from_key('periode_paiement_courrection',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>


	<br><br>
	<h2>Calage du processus de reversement à la DGFiP</h2>
	<table>
	<tr style="text-align:center;"><td>Question</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr><td>Date de création des SIRET à l\'INSEE</td>
		<td style="text-align:center;">'.get_answer_from_key('creation_siret_insee',$answer_array).'</td>
		<td><input type="date" name="creation_siret_insee" value="'.get_answer_from_key('creation_siret_insee',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Date d\'inscription sur NetEntreprise</td>
		<td style="text-align:center;">'.get_answer_from_key('inscription_net_entreprise',$answer_array).'</td>
		<td><input type="date" name="inscription_net_entreprise" value="'.get_answer_from_key('incription_net_entreprise',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Date d\'ouverture des comptes ACOSS le cas échéant</td>
		<td style="text-align:center;">'.get_answer_from_key('ouverture_comptes_accoss',$answer_array).'</td>
		<td><input type="date" name="ouverture_comptes_accoss" value="'.get_answer_from_key('ouverture_comptes_accoss',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Information des comptables et directions juridiques du réseau</td>
		<td style="text-align:center;">'.get_answer_from_key('info_comptables_reseau',$answer_array).'</td>
		<td><input type="date" name="info_comptables_reseau" value="'.get_answer_from_key('info_comptables_reseau',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>


	<br><br>
	<h2>Calage du processus de déclaration dégradé</h2>
	<table>
	<tr style="text-align:center;"><td>Question</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr><td>Identification des acteurs</td>
		<td style="text-align:center;">'.get_answer_from_key('process_dec_degrade_acteurs',$answer_array).'</td>
		<td><input type="date" name="process_dec_degrade_acteurs" value="'.get_answer_from_key('process_dec_degrade_acteurs',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Formation au dépôt manuel</td>
		<td style="text-align:center;">'.get_answer_from_key('process_dec_degrade_formation_depot_manuel',$answer_array).'</td>
		<td><input type="date" name="process_dec_degrade_formation_depot_manuel" value="'.get_answer_from_key('process_dec_degrade_formation_depot_manuel',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Mise à disposition des accès</td>
		<td style="text-align:center;">'.get_answer_from_key('process_dec_degrade_codes_acces',$answer_array).'</td>
		<td><input type="date" name="process_dec_degrade_codes_acces" value="'.get_answer_from_key('process_dec_degrade_codes_acces',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>

	<br><br>
	<h2>Formalisation du processus de gestion de crise</h2>
	<table>
	<tr style="text-align:center;"><td>Question</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr><td>Déterminer les membres de la cellule de crise</td>
		<td style="text-align:center;">'.get_answer_from_key('process_crise_membres_cellule_crise',$answer_array).'</td>
		<td><input type="date" name="process_crise_membres_cellule_crise" value="'.get_answer_from_key('process_crise_membres_cellule_crise',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Capacité à stopper le prélèvement (désactivation ou paramétrage d\'une date d\'effet)</td>
		<td style="text-align:center;">'.get_answer_from_key('process_crise_desactiver_prelevement',$answer_array).'</td>
		<td><input type="date" name="process_crise_desactiver_prelevement" value="'.get_answer_from_key('process_crise_desactiver_prelevement',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Déterminer les cannaux de communication</td>
		<td style="text-align:center;">'.get_answer_from_key('process_crise_cannaux_com',$answer_array).'</td>
		<td><input type="date" name="process_crise_cannaux_com" value="'.get_answer_from_key('process_crise_cannaux_com',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>



	<br><br>
	<h2>Données d\'identification</h2>
	<table>
	<tr style="text-align:center;"><td>Question</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
<!--
	<tr><td>Nombre d\'usagers</td>
		<td style="text-align:center;">'.get_answer_from_key('identification_nb_usagers',$answer_array).'</td>
		<td><input type="date" name="identification_nb_usagers" value="'.get_answer_from_key('identification_nb_usagers',$answer_array).'" style="text-align:center;"/></td>
	</tr>
-->
	<tr><td>Nombre d\'usagers dont le NIR est connu</td>
		<td style="text-align:center;">'.get_answer_from_key('identification_nb_nir_connus',$answer_array).'</td>
		<td><input type="date" name="identification_nb_nir_connus" value="'.get_answer_from_key('identification_nb_nir_connus',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Nombre d\'usagers dont le NIR est connu</td>
		<td style="text-align:center;">'.get_answer_from_key('identification_nb_nir_certifies',$answer_array).'</td>
		<td><input type="date" name="identification_nb_nir_certifies" value="'.get_answer_from_key('identification_nb_nir_certifies',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>

	<br><br>
	<h2>Automatisation des régularisations</h2>


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

?>
