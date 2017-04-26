<?php

/*
PAS Dashboard
Copyright [2017-2018] Aurélien DUMAINE (aurelien@dumaine.me) / Fontaine Consultants

Licensed to the Apache Software Foundation (ASF) under one
or more contributor license agreements.  See the NOTICE file
distributed with this work for additional information
regarding copyright ownership.  The ASF licenses this file
to you under the Apache License, Version 2.0 (the
"License"); you may not use this file except in compliance
with the License.  You may obtain a copy of the License at

  http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing,
software distributed under the License is distributed on an
"AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
KIND, either express or implied.  See the License for the
specific language governing permissions and limitations
under the License.
*/

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
        while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
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

	$t=array("primary_key", "login", "date", "application");
	foreach ($_POST as $key=>$value)
		if (($key != "submit") && ($key != "rebuild_answer_file"))
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
		$headers = fgetcsv($handle, 0, ";");
		array_shift($headers); //unstack composed_key
		array_shift($headers); //unstack timestamp
		array_shift($headers); //unstack login
		array_shift($headers); //unstack application

        while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
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
	return date("dmY_His");
}


function add_vote(){
	global $answers_file;
	$pkey=get_date().'-'.get_user_login().'-'.get_user_application();
	$line_to_add = array($pkey,get_user_login(),get_date(),get_user_application());

    if (($handle = fopen($answers_file, "r")) !== FALSE) {
		$headers = fgetcsv($handle, 0, ";");
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

	function is_selected($field_name,$answer_array,$target){
		if(get_answer_from_key($field_name,$answer_array) == $target){
			return 'selected="selected"';
		}else{
			return "";
		}
	}
echo '<html>
<head>
	<title>PAS - Données projet</title>
	<meta charset="UTF-8" />
    <link href="jquery-ui-1.12.1.custom/jquery-ui.min.css" rel="stylesheet" />
	<meta name="author" content="Aurélien DUMAINE (aurelien@dumaine.me) / Fontaine Consultants">
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

<br><br>NOTICE : les dates renseignées sont prévisionnelles si elles sont situées dna sle futur par rapport à la date de remplissage du questionnaire. Si non ce sont les dates effectives.

	<br><br>
	<h2>Périmètre</h2>
	<table>
	<tr style="text-align:center;"><td>Questions</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>

	<tr><td>Mode de déclaration</td>
		<td style="text-align:center;">'.get_answer_from_key('perimetre_mode_declaration',$answer_array).'</td>
		<td>
			<select name="perimetre_mode_declaration">
				<option value="pasrau" '.is_selected('perimetre_mode_declaration',$answer_array,'pasrau').'>PASRAU</option>
				<option value="dsn" '.is_selected('perimetre_mode_declaration',$answer_array,'dsn').'>DSN</option>
			</select>
		</td>
	</tr>
	<tr><td>Type de produit mis en place</td>
		<td style="text-align:center;">'.get_answer_from_key('perimetre_type_produit',$answer_array).'<br>'.get_answer_from_key('perimetre_nom_editeur',$answer_array).' '.get_answer_from_key('perimetre_nom_editeur_libre',$answer_array).'</td>
		<td>
			<select name="perimetre_type_produit">
				<option value="pgi" '.is_selected('perimetre_type_produit',$answer_array,'pgi').'>Progiciel du marché</option>
				<option value="interne" '.is_selected('perimetre_type_produit',$answer_array,'interne').'>Produit développé en interne</option>
			</select>
			<br>Si c\'est un produit du marché, précisez l\'éditeur :<br>
			<select name="perimetre_nom_editeur">
				<option value="sopra" '.is_selected('perimetre_nom_editeur',$answer_array,'sopra').'>Sopra</option>
				<option value="sap" '.is_selected('perimetre_nom_editeur',$answer_array,'sap').'>SAP</option>
				<option value="gfi" '.is_selected('perimetre_nom_editeur',$answer_array,'gfi').'>GFI</option>
				<option value="adp" '.is_selected('perimetre_nom_editeur',$answer_array,'adp').'>ADP</option>
				<option value="sage" '.is_selected('perimetre_nom_editeur',$answer_array,'sage').'>Sage</option>
				<option value="meta4" '.is_selected('perimetre_nom_editeur',$answer_array,'meta4').'>Meta4</option>
				<option value="berger-levrault" '.is_selected('perimetre_nom_editeur',$answer_array,'berger-levrault').'>Berger-Levrault</option>
				<option value="cegid" '.is_selected('perimetre_nom_editeur',$answer_array,'cegid').'>Cegid</option>
				<option value="ciril" '.is_selected('perimetre_nom_editeur',$answer_array,'ciril').'>Ciril</option>
				<option value="" '.is_selected('perimetre_nom_editeur',$answer_array,'').'>-</option>
			</select>
			Autre, précisez : 
			<input type="text" name="perimetre_nom_editeur_libre" value="'.get_answer_from_key('perimetre_nom_editeur_libre',$answer_array).'" style="text-align:center;"/></td>
		</td>
	</tr>
	<tr><td>Nombre de bénéficiaires</td>
		<td style="text-align:center;">'.get_answer_from_key('perimetre_nb_beneficiaire',$answer_array).'</td>
		<td><input type="number" name="perimetre_nb_beneficiaire" class="nbr" value="'.get_answer_from_key('perimetre_nb_beneficiaire',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Nombre d\'usagers dont le NIR est connu</td>
		<td style="text-align:center;">'.get_answer_from_key('identification_nb_nir_connus',$answer_array).'</td>
		<td><input type="number" name="identification_nb_nir_connus" value="'.get_answer_from_key('identification_nb_nir_connus',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Nombre d\'usagers dont le NIR est certifié</td>
		<td style="text-align:center;">'.get_answer_from_key('identification_nb_nir_certifies',$answer_array).'</td>
		<td><input type="number" name="identification_nb_nir_certifies" value="'.get_answer_from_key('identification_nb_nir_certifies',$answer_array).'" style="text-align:center;"/></td>
	</tr>

	<tr><td>Total des sommes versées sur l\'année (qui auraient été soumises au PAS au 1/1/2018)</td>
		<td style="text-align:center;">'.get_answer_from_key('perimetre_assiette',$answer_array).'</td>
		<td><input type="number" name="perimetre_assiette" value="'.get_answer_from_key('perimetre_assiette',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>

	<h2>Avancement dans les jalons projet</h2>
	<table>
	<tr style="text-align:center;">
		<td>ID</td>
		<td>Nom du lot</td>
		<td>Date de fin de développements et de la recette unitaire (effective ou prévisionnelle)</td>
		<td>Date d\'entrée en "pilote" (effective ou prévisionnelle)</td>
		<td>Date de fin de VABF (effective ou prévisionnelle)</td>
		<td>Date de fin de VSR (effective ou prévisionnelle)</td>
		<td style="background-color:black;">-</td>
		<td>Nombre d\'anomalies bloquantes ouvertes à date</td>
		<td>Nombre d\'anomalies total ouvertes à date</td>
	</tr>

Si lotissement du projet en plusieurs lots, merci de remplir le tableau suivant selon ce lotissement.

	<tr>
		<td>#1</td>
		<td><input type="text" name="lot1_nom" value="'.get_answer_from_key('lot1_nom',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot1_date_fin_dev" class="widget_calendar" value="'.get_answer_from_key('lot1_date_fin_dev',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot1_date_entree_pilote" class="widget_calendar" value="'.get_answer_from_key('lot1_date_entree_pilote',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot1_date_fin_VABF" class="widget_calendar" value="'.get_answer_from_key('lot1_date_fin_VABF',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot1_date_fin_VSR" class="widget_calendar" value="'.get_answer_from_key('lot1_date_fin_VSR',$answer_array).'" style="text-align:center;"/></td>
		<td style="background-color:black;">-</td>
		<td><input type="number" name="lot1_nb_anomalies_bloquantes" value="'.get_answer_from_key('lot1_nb_anomalies_bloquantes',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot1_nb_anomalies_total" value="'.get_answer_from_key('lot1_nb_anomalies_total',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr>
		<td>#2</td>
		<td><input type="text" name="lot2_nom" value="'.get_answer_from_key('lot2_nom',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot2_date_fin_dev" class="widget_calendar" value="'.get_answer_from_key('lot2_date_fin_dev',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot2_date_entree_pilote" class="widget_calendar" value="'.get_answer_from_key('lot2_date_entree_pilote',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot2_date_fin_VABF" class="widget_calendar" value="'.get_answer_from_key('lot2_date_fin_VABF',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot2_date_fin_VSR" class="widget_calendar" value="'.get_answer_from_key('lot2_date_fin_VSR',$answer_array).'" style="text-align:center;"/></td>
		<td style="background-color:black;">-</td>
		<td><input type="number" name="lot2_nb_anomalies_bloquantes" value="'.get_answer_from_key('lot2_nb_anomalies_bloquantes',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot2_nb_anomalies_total" value="'.get_answer_from_key('lot2_nb_anomalies_total',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr>
		<td>#3</td>
		<td><input type="text" name="lot3_nom" value="'.get_answer_from_key('lot3_nom',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot3_date_fin_dev" class="widget_calendar" value="'.get_answer_from_key('lot3_date_fin_dev',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot3_date_entree_pilote" class="widget_calendar" value="'.get_answer_from_key('lot3_date_entree_pilote',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot3_date_fin_VABF" class="widget_calendar" value="'.get_answer_from_key('lot3_date_fin_VABF',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot3_date_fin_VSR" class="widget_calendar" value="'.get_answer_from_key('lot3_date_fin_Vparametrage_date_effet_prelevemeniSR',$answer_array).'" style="text-align:center;"/></td>
		<td style="background-color:black;">-</td>
		<td><input type="number" name="lot3_nb_anomalies_bloquantes" value="'.get_answer_from_key('lot3_nb_anomalies_bloquantes',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot3_nb_anomalies_total" value="'.get_answer_from_key('lot3_nb_anomalies_total',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr>
		<td>#4</td>
		<td><input type="text" name="lot4_nom" value="'.get_answer_from_key('lot4_nom',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot4_date_fin_dev" class="widget_calendar" value="'.get_answer_from_key('lot4_date_fin_dev',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot4_date_entree_pilote" class="widget_calendar" value="'.get_answer_from_key('lot4_date_entree_pilote',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot4_date_fin_VABF" class="widget_calendar" value="'.get_answer_from_key('lot4_date_fin_VABF',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot4_date_fin_VSR" class="widget_calendar" value="'.get_answer_from_key('lot4_date_fin_VSR',$answer_array).'" style="text-align:center;"/></td>
		<td style="background-color:black;">-</td>
		<td><input type="number" name="lot4_nb_anomalies_bloquantes" value="'.get_answer_from_key('lot4_nb_anomalies_bloquantes',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot4_nb_anomalies_total" value="'.get_answer_from_key('lot4_nb_anomalies_total',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr>
		<td>#5</td>
		<td><input type="text" name="lot5_nom" value="'.get_answer_from_key('lot5_nom',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot5_date_fin_dev" class="widget_calendar" value="'.get_answer_from_key('lot5_date_fin_dev',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot5_date_entree_pilote" class="widget_calendar" value="'.get_answer_from_key('lot5_date_entree_pilote',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot5_date_fin_VABF" class="widget_calendar" value="'.get_answer_from_key('lot5_date_fin_VABF',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="lot5_date_fin_VSR" class="widget_calendar" value="'.get_answer_from_key('lot5_date_fin_VSR',$answer_array).'" style="text-align:center;"/></td>
		<td style="background-color:black;">-</td>
		<td><input type="number" name="lot5_nb_anomalies_bloquantes" value="'.get_answer_from_key('lot5_nb_anomalies_bloquantes',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="number" name="lot5_nb_anomalies_total" value="'.get_answer_from_key('lot5_nb_anomalies_total',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>

	<br><br>
	<table>
	<tr style="text-align:center;"><td>Questions</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr><td>Déclaration de conformité réalisée auprès de la CNIL</td>
		<td style="text-align:center;">'.get_answer_from_key('declaration_cnil',$answer_array).'</td>
		<td>
			<select name="declaration_cnil">
				<option value="oui" '.is_selected('declaration_cnil',$answer_array,'oui').'>OUI</option>
				<option value="non" '.is_selected('declaration_cnil',$answer_array,'non').'>NON</option>
				<option value="" '.is_selected('declaration_cnil',$answer_array,'').'>-</option>
			</select>
		</td>
	</tr>
	<tr><td>Date de mise en production du lot de gestion des flux (réelle ou prévisionnelle)</td>
		<td style="text-align:center;">'.get_answer_from_key('date_mep_flux',$answer_array).'</td>
		<td><input type="text" class="widget_calendar" name="date_mep_flux" value="'.get_answer_from_key('date_mep_flux',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Date effective de l\'initialisation des taux (réelle ou prévisionnelle)</td>
		<td style="text-align:center;">'.get_answer_from_key('date_init_taux',$answer_array).'</td>
		<td><input type="text" name="date_init_taux" class="widget_calendar" value="'.get_answer_from_key('date_init_taux',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Date de mise en production du lot de prélèvement (réelle ou prévisionnelle)</td>
		<td style="text-align:center;">'.get_answer_from_key('date_mep_prelevement',$answer_array).'</td>
		<td><input type="text" name="date_mep_prelevement" class="widget_calendar" value="'.get_answer_from_key('date_mep_prelevement',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Date effective du premier prélèvement (réelle ou prévisionnelle)</td>
		<td style="text-align:center;">'.get_answer_from_key('date_premier_prelevement',$answer_array).'</td>
		<td><input type="text" name="date_premier_prelevement" class="widget_calendar" value="'.get_answer_from_key('date_premier_prelevement',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>


	<h2>Éléments budgétaires</h2>
	<table>
	<tr style="text-align:center;"><td>Type de budget</td>
		<td>Dépensé/engagé</td>
		<td>Total</td>
	</tr>
	<tr><td>Budget SI MOE interne (exprimé en jh)</td>
		<td><input type="number" name="budget_engage_interne" value="'.get_answer_from_key('budget_engage_interne',$answer_array).'" style="text-align:center;"/> jh</td>
		<td><input type="number" name="budget_total_interne" value="'.get_answer_from_key('budget_total_interne',$answer_array).'" style="text-align:center;"/> jh</td>
	</tr>
	<tr><td>Budget SI MOE externe (exprimé en euros, HT)</td>
		<td><input type="number" name="budget_engage_externe" value="'.get_answer_from_key('budget_engage_externe',$answer_array).'" style="text-align:center;"/>€</td>
		<td><input type="number" name="budget_total_externe" value="'.get_answer_from_key('budget_total_externe',$answer_array).'" style="text-align:center;"/>€</td>
	</tr>
	</table>


	<h2>Points ouverts</h2>
	<table>
	<tr style="text-align:center;"><td>Questions</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr><td>Nombre de points ouverts fonctionnels encore ouverts auprès de la DGFiP</td>
		<td style="text-align:center;">'.get_answer_from_key('nb_questions_fonctionnelles',$answer_array).'</td>
		<td><input type="number" name="nb_questions_fonctionnelles" value="'.get_answer_from_key('nb_questions_fonctionnelles',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Nombre de points ouverts techniques encore ouverts auprès du GIP MDS</td>
		<td style="text-align:center;">'.get_answer_from_key('nb_questions_techniques',$answer_array).'</td>
		<td><input type="number" name="nb_questions_techniques" value="'.get_answer_from_key('nb_questions_techniques',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>

	<br><br>
	<h2>Suivi de pratiques de sécurisation des paiements</h2>
	<table>
	<tr style="text-align:center;">
		<td>Type de période</td>
		<td>Prévue dans le planning ?</td>
		<td>Date de début</td>
		<td>Date de fin</td>
	</tr>

	<tr><td>Période de paiement à blanc</td>
		<td>
			<select name="periode_paiement_blanc_bool">
				<option value="oui" '.is_selected('periode_paiement_blanc_bool',$answer_array,'oui').'>OUI</option>
				<option value="non" '.is_selected('periode_paiement_blanc_bool',$answer_array,'non').'>NON</option>
				<option value="" '.is_selected('periode_paiement_blanc_bool',$answer_array,'').'>-</option>
			</select> si oui ->
		</td>
		<td><input type="text" name="debut_periode_paiement_blanc" class="widget_calendar" value="'.get_answer_from_key('debut_periode_paiement_blanc',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="fin_periode_paiement_blanc" class="widget_calendar" value="'.get_answer_from_key('fin_periode_paiement_blanc',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Période de paiement en double</td>
		<td>
			<select name="periode_paiement_double_bool">
				<option value="oui" '.is_selected('periode_paiement_double_bool',$answer_array,'oui').'>OUI</option>
				<option value="non" '.is_selected('periode_paiement_double_bool',$answer_array,'non').'>NON</option>
				<option value="" '.is_selected('periode_paiement_double_bool',$answer_array,'').'>-</option>
			</select> si oui ->
		</td>
		<td><input type="text" name="debut_periode_paiement_double" class="widget_calendar" value="'.get_answer_from_key('debut_periode_paiement_double',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="fin_periode_paiement_double" class="widget_calendar" value="'.get_answer_from_key('fin_periode_paiement_double',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	<tr><td>Période de correction</td>
		<td>
			<select name="periode_correction_bool">
				<option value="oui" '.is_selected('periode_correction_bool',$answer_array,'oui').'>OUI</option>
				<option value="non" '.is_selected('periode_correction_bool',$answer_array,'non').'>NON</option>
				<option value="" '.is_selected('periode_correction_bool',$answer_array,'').'>-</option>
			</select> si oui -> 
		</td>
		<td><input type="text" name="debut_periode_correction" class="widget_calendar" value="'.get_answer_from_key('debut_periode_correction',$answer_array).'" style="text-align:center;"/></td>
		<td><input type="text" name="fin_periode_correction" class="widget_calendar" value="'.get_answer_from_key('fin_periode_correction',$answer_array).'" style="text-align:center;"/></td>
	</tr>

	<tr><td>Possibilité de paramétrer une date d\'activation du prélèvement</td>
		<td>
			<select name="parametrage_date_effet_prelevement">
				<option value="oui" '.is_selected('parametrage_date_effet_prelevement',$answer_array,'oui').'>OUI</option>
				<option value="non" '.is_selected('parametrage_date_effet_prelevement',$answer_array,'non').'>NON</option>
				<option value="" '.is_selected('parametrage_date_effet_prelevement',$answer_array,'').'>-</option>
			</select>
		</td>
	</tr>

	</table>


	<br><br>
	<h2>Préparation du processus de prélèvement de l\'impôt collecté par la DGFiP</h2>
	<table>
	<tr style="text-align:center;"><td>Questions</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr>
		<td>Nécessité d\'enregistrer de nouveaux SIRET auprès de l\'INSEE pour mettre en place le PAS</td>
		<td style="text-align:center;">'.get_answer_from_key('necessite_enregistrement_siret',$answer_array).'</td>
		<td>
			<select name="necessite_enregistrement_siret">
				<option value="oui" '.is_selected('necessite_enregistrement_siret',$answer_array,'oui').'>OUI</option>
				<option value="non" '.is_selected('necessite_enregistrement_siret',$answer_array,'non').'>NON</option>
				<option value="" '.is_selected('necessite_enregistrement_siret',$answer_array,'').'>-</option>
			</select>
		</td>
	</tr>
	<tr>
		<td>Nouveaux SIRET effectivement créés</td>
		<td style="text-align:center;">'.get_answer_from_key('enregistrement_effectif_siret',$answer_array).'</td>
		<td>
			<select name="enregistrement_effectif_siret">
				<option value="oui" '.is_selected('enregistrement_effectif_siret',$answer_array,'oui').'>OUI</option>
				<option value="non" '.is_selected('enregistrement_effectif_siret',$answer_array,'non').'>NON</option>
				<option value="" '.is_selected('enregistrement_effectif_siret',$answer_array,'').'>-</option>
			</select>
		</td>
	</tr>
	<tr>
		<td>Inscription du collecteur sur le portail NetEntreprises réalisée</td>
		<td style="text-align:center;">'.get_answer_from_key('inscription_collecteur_netentreprises',$answer_array).'</td>
		<td>
			<select name="inscription_collecteur_netentreprises">
				<option value="oui" '.is_selected('inscription_collecteur_netentreprises',$answer_array,'oui').'>OUI</option>
				<option value="non" '.is_selected('inscription_collecteur_netentreprises',$answer_array,'non').'>NON</option>
				<option value="" '.is_selected('inscription_collecteur_netentreprises',$answer_array,'').'>-</option>
			</select>
		</td>
	</tr>
	<tr>
		<td>Inscription des SIRET du collecteur dans le dispositif déclaratif PASRAU sur le portail NetEntreprises réalisée</td>
		<td style="text-align:center;">'.get_answer_from_key('inscription_siret_netentreprises',$answer_array).'</td>
		<td>
			<select name="inscription_siret_netentreprises">
				<option value="oui" '.is_selected('inscription_siret_netentreprises',$answer_array,'oui').'>OUI</option>
				<option value="non" '.is_selected('inscription_siret_netentreprises',$answer_array,'non').'>NON</option>
				<option value="" '.is_selected('inscription_siret_netentreprises',$answer_array,'').'>-</option>
			</select>
		</td>
	</tr>
	<tr>
		<td>Validation par l\'agence comptable du collecteur</td>
		<td style="text-align:center;">'.get_answer_from_key('validation_agence_comptable',$answer_array).'</td>
		<td>
			<select name="validation_agence_comptable">
				<option value="oui" '.is_selected('validation_agence_comptable',$answer_array,'oui').'>OUI</option>
				<option value="non" '.is_selected('validation_agence_comptable',$answer_array,'non').'>NON</option>
				<option value="" '.is_selected('validation_agence_comptable',$answer_array,'').'>-</option>
			</select>
		</td>
	</tr>
	</table>


	<br><br>
	<h2>Formalisation du processus de gestion de crise</h2>
	<table>
	<tr style="text-align:center;"><td>Questions</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>

	<tr>
		<td>Identification de solutions de fonctionnement dégradé afin d\'assurer la continuité des versements aux bénéficiaires </td>
		<td style="text-align:center;">'.get_answer_from_key('mode_degrade_coeur_metier',$answer_array).'</td>
		<td>
			<select name="mode_degrade_coeur_metier">
				<option value="oui" '.is_selected('mode_degrade_coeur_metier',$answer_array,'oui').'>OUI</option>
				<option value="non" '.is_selected('mode_degrade_coeur_metier',$answer_array,'non').'>NON</option>
				<option value="" '.is_selected('mode_degrade_coeur_metier',$answer_array,'').'>-</option>
			</select>
		</td>
	</tr>
	<tr>
		<td>Identification de solutions de fonctionnement dégradé afin d\'assurer la continuité des prélèvements par la DGFIP de l\'impôt collecté</td>
		<td style="text-align:center;">'.get_answer_from_key('mode_degrade_prelevement_impot_collecte',$answer_array).'</td>
		<td>
			<select name="mode_degrade_prelevement_impot_collecte">
				<option value="oui" '.is_selected('mode_degrade_prelevement_impot_collecte',$answer_array,'oui').'>OUI</option>
				<option value="non" '.is_selected('mode_degrade_prelevement_impot_collecte',$answer_array,'non').'>NON</option>
				<option value="" '.is_selected('mode_degrade_prelevement_impot_collecte',$answer_array,'').'>-</option>
			</select>
		</td>
	</tr>
	<tr>
		<td>Organisation de la cellule de cris et identification de ses membres de la cellule de crise</td>
		<td style="text-align:center;">'.get_answer_from_key('process_crise_membres_cellule_crise',$answer_array).'</td>
		<td>
			<select name="process_crise_membres_cellule_crise">
				<option value="oui" '.is_selected('process_crise_membres_cellule_crise',$answer_array,'oui').'>OUI</option>
				<option value="non" '.is_selected('process_crise_membres_cellule_crise',$answer_array,'non').'>NON</option>
				<option value="" '.is_selected('process_crise_membres_cellule_crise',$answer_array,'').'>-</option>
			</select>
		</td>
	</tr>
	</table>

<script src="jquery-ui-1.12.1.custom/external/jquery/jquery.js"></script>
<script src="jquery-ui-1.12.1.custom/jquery-ui.min.js"></script>
<script>
   (function() {
        $(".widget_calendar").datepicker({
			dateFormat: "dd/mm/yy"
		}); 
   })();

/*
   (function() {
        $(".nbr").number(true, 2);
   })();
*/
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
