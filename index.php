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
		print_r($headers);
		array_shift($headers); //unstack timestamp
		array_shift($headers); //unstack login
		print_r($headers);
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
<body>';

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
	<h1>Prélèvement à la source - Collecte de données projet</h1>
	Organisme : '.get_user_name().' <br>
	Dernière réponse : '.$last_answer_date.' 

	<br><br>
	<h2>Questions en instance</h2>
	<table>
	<tr style="text-align:center;"><td>Question</td>
		<td>Valeur précédente</td>
		<td>Nouvelle valeur</td>
	</tr>
	<tr><td>Nombre de questions fonctionnelles en instance auprès de la DGFiP</td>
		<td style="text-align:center;">'.get_answer_from_key('nb_questions_fonctionnelles',$answer_array).'</td>
		<td><input type="text" name="nb_questions_fonctionnelles" value="'.get_answer_from_key('nb_questions_fonctionnelles',$answer_array).'" style="text-align:center;"/></td>
	</tr>
	</table>
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
