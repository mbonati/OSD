<?php
# Quella che segue è l'unica chiave da configurare e si tratta del percorso
# assoluto di installazione dello script (includere anche la barra finale).
# per esempio: $instdir_snl = "/home/mhd/www.nomedelsito.com/htdocs/snl/";
$instdir_snl = "/home/mhd/www.nomedelsito.com/htdocs/snl/";

# Autoconfigurazione dello script. Se si incontrano problemi commentare la riga
# seguente e configurare la variabile precedente. Per commentare la riga basta 
# aggiungere un caratter "#" come primo carattere.
$instdir_snl = substr(__FILE__,0,-7);


###########################################################################
# PHP Simply News-Letter 1.1.2
###########################################################################
# Semplice script per la gestione delle newsletter liberamente tratto
# da uno script di David Broker.
#
# Compatibile e testato con i seguenti parametri:
# - REGISTER_GLOBALS OFF
# - PHP 4.3.10 / 4.3.11
# 
# Questo script è gratuito e senza garanzia alcuna
###########################################################################
# Da questo punto in poi non modificare nulla
###########################################################################
error_reporting(0);
#
# percorso assoluto del file di configurazione 
$config_file = $instdir_snl."inc/snl_config.dat";

# Sezione di compatibilità per REGISTER_GLOBALS OFF
$ArrayList = array("_GET", "_POST", "_SESSION", "_COOKIE", "_SERVER");
foreach($ArrayList as $gblArray){
	if (!empty($$gblArray)){
		$keys = array_keys($$gblArray);
		foreach($keys as $key){
			$$key = trim(${$gblArray}[$key]);
		}
	}
}
$remove_address_arr = $_POST[remove_address_arr];

# Versione dello script
$version = "1.1.1";

# Chiave per entrare nell'area di amministrazione. Per esempio: action=?admin
$admin_keyword = "admin";

# Lettura del file di configurazione
$config = @file($config_file) or die(print_template ("err_write.html"));

# Settaggio variabili lette dal file di configurazione
$user = trim($config[0]);
$pass = trim($config[1]);
$list_name = trim($config[2]);
$list_file = trim($config[3]);
$mail_group = trim($config[4]);
$owner_email = trim($config[5]);
$send_confirm = trim($config[6]);
$confirm_subject = trim($config[7]);
$send_welcome = trim($config[8]);
$welcome_subject = trim($config[9]);
$send_goodbye = trim($config[10]);
$goodbye_subject = trim($config[11]);
$default_per_page = trim($config[12]);
$confirm_msg = get_data($config, "conferma");
$welcome_msg = get_data($config, "benvenuto");
$goodbye_msg = get_data($config, "cancellazione");
$sig = get_data($config, "firma");
$banned_addresses = get_data($config, "escludi");

###########################################################################
# Output pagina
###########################################################################
# stampa intestazione
print_header();

# Controllo varibili GET per determinare l'azione da fare e output del risultato
if ($action == $admin_keyword)   admin();
elseif ($action == "sub")        subscribe(strip_tags(trim($email)), $send_confirm, $send_welcome);
elseif ($action == "confirm")    confirm($email, $conf);
elseif ($action == "unsub")      unsubscribe(trim($email));
else                             print_subscribe_form();

# stampa pie di pagina
print_footer();

###########################################################################
# Funzioni di gestione della news letter
###########################################################################
# Controlla le opzioni admin
function admin() {
	global $q, $username, $password, $user, $pass;
	if (($user == $username) && ($pass == $password)) {
		if ($q == "send_msg")              admin_send_msg();
		elseif ($q == "send_msg_confirm")  admin_send_msg_confirm();
		elseif ($q == "write_msg")         admin_write_msg();
		elseif ($q == "view_subs")         admin_view_subs();
		elseif ($q == "config")            admin_config();
		elseif ($q == "add_remove")        admin_add_remove();
		elseif ($q == "exit")	{
			$username = "";
			$password = "";
			admin_print_logon("Uscita effettuata con successo.");
		}
		if ($q != "exit")	admin_print_options();
	}
	else {
		if (($username == "") && ($password == "")) admin_print_logon("");
		else admin_print_logon("<font color=#FF0000>ERRORE: Nome utente o password errati</font>");
	}
}

# iscrizione alla lista
function subscribe($email, $send_confirm, $send_welcome) {
 global $list_name;
 if ($email == "") {
 	print_subscribe_form();
 }
 else {
 	if (!banned($email)) {
 		if (!on_list($email)) {
 			if (valid_email($email)) {
 				if ($send_confirm == "1") { 
 					send_confirm($email);
 					$lista_sost = array ("list_name" => $list_name, "email" => $email);
					print_template ("sub_confirm.html", $lista_sost);
				}
				else { 
					if (add_to_list($email)) {
						$lista_sost = array ("list_name" => $list_name, "email" => $email);
						print_template ("sub_success.html", $lista_sost);
						if ($send_welcome == "1") {
							send_welcome($email);
							print_template ("sub_mailmsg.html");
						}
					}
				}
			}
			else {
				$lista_sost = array ("list_name" => $list_name, "email" => $email);
				print_template ("sub_invalid.html", $lista_sost);
			}
		}
		else {
			$lista_sost = array ("list_name" => $list_name, "email" => $email);
			print_template ("sub_already.html", $lista_sost);
		}
	}
	else {
		$lista_sost = array ("list_name" => $list_name, "email" => $email);
		print_template ("sub_banned.html", $lista_sost);
	}
 }
}

# cancellazione mail se gia esistente
function unsubscribe($email) {
	global $list_file, $list_name, $send_goodbye;
	if ($email == "") {
		print_subscribe_form();
	}
	else {
		if (remove_from_list($email)) {
			$lista_sost = array ("list_name" => $list_name, "email" => $email);
			print_template ("uns_success.html", $lista_sost);
			if ($send_goodbye == "1") { 
				send_goodbye($email);
				print_template ("uns_mailmsg.html");
			}
		}
  		else {
			$lista_sost = array ("list_name" => $list_name, "email" => $email);
			print_template ("uns_invalid.html", $lista_sost);
		}
 	}
}

# conferma iscrizione
function confirm($email, $conf) {
	$check_code = substr(strrev(md5($email).strtolower(soundex(email))),0,5);
	if ($conf == $check_code) {
		subscribe($email, "off", $GLOBALS['send_welcome']);
	}
 	else {
		print_template ("sub_confirm_ko.html");
 	}
}

# elimina email dal file
function remove_from_list($email) {
	global $list_file;
	$file = @file($list_file);
	$success = false;
	$fd = @fopen($list_file, "w") or die(print_template ("err_write.html"));
	if ($file) {
		foreach($file as $address) {
			$address = trim($address);
			if ($address != $email) fputs($fd, "$address\n");
			else $success = true;
  		}
  		fclose($fd); 
 	}
 	return $success;
}

# aggiungi email al file
function add_to_list($email) {
	global $list_file;
	$fd = @fopen($list_file, "a") or die(print_template ("err_write.html"));
	fputs($fd, strtolower($email)."\n");
	fclose($fd);
	return true;
}

# legge i messaggi racchiusi tra i tag <$keyword> & </$keyword>
function get_data($array, $keyword) {
	foreach ($array as $i => $line) {
		if (trim($line) == "<$keyword>") {
			$i++;
			break;
		}
	}
	$array[$i] = trim($array[$i]);
	while ($array[$i] != "</$keyword>") {
		$tmp = stripslashes($array[$i]);
		$msg .= "\n$tmp";
		$i++;
		$array[$i] = trim($array[$i]);
	}
 	return $msg;
}

# controlla al validità dell'indirizzo email
function valid_email($email) {
	$pattern = "^[-_.[:alnum:]]+@((([[:alnum:]]|[[:alnum:]][[:alnum:]-]*[[:alnum:]])\.)+(ad|ae|aero|af|ag|ai|al|am|an|ao|aq|ar|arpa|as|at|au|aw|az|ba|bb|bd|be|bf|bg|bh|bi|biz|bj|bm|bn|bo|br|bs|bt|bv|bw|by|bz|ca|cc|cd|cf|cg|ch|ci|ck|cl|cm|cn|co|com|coop|cr|cs|cu|cv|cx|cy|cz|de|dj|dk|dm|do|dz|ec|edu|ee|eg|eh|er|es|et|eu|fi|fj|fk|fm|fo|fr|ga|gb|gd|ge|gf|gh|gi|gl|gm|gn|gov|gp|gq|gr|gs|gt|gu|gw|gy|hk|hm|hn|hr|ht|hu|id|ie|il|in|info|int|io|iq|ir|is|it|jm|jo|jp|ke|kg|kh|ki|km|kn|kp|kr|kw|ky|kz|la|lb|lc|li|lk|lr|ls|lt|lu|lv|ly|ma|mc|md|mg|mh|mil|mk|ml|mm|mn|mo|mp|mq|mr|ms|mt|mu|museum|mv|mw|mx|my|mz|na|name|nc|ne|net|nf|ng|ni|nl|no|np|nr|nt|nu|nz|om|org|pa|pe|pf|pg|ph|pk|pl|pm|pn|pr|pro|ps|pt|pw|py|qa|re|ro|ru|rw|sa|sb|sc|sd|se|sg|sh|si|sj|sk|sl|sm|sn|so|sr|st|su|sv|sy|sz|tc|td|tf|tg|th|tj|tk|tm|tn|to|tp|tr|tt|tv|tw|tz|ua|ug|uk|um|us|uy|uz|va|vc|ve|vg|vi|vn|vu|wf|ws|ye|yt|yu|za|zm|zw)|(([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5])\.){3}([0-9][0-9]?|[0-1][0-9][0-9]|[2][0-4][0-9]|[2][5][0-5]))$"; 
	if(eregi($pattern, $email)) return true;
	else return false;
}

# controlla se la mail è in elenco ed eventualmente ritorna 'true'
function on_list($email) {
	global $list_file;
	$found = false;
	$file = @file($list_file);
	if ($file) {
		foreach ($file as $address) {
			if (trim($address) == trim($email)) {
				$found = true;
			}
		}
	}
	return $found;
}

# Controlla se la mail deve essere esclusa ed eventualmente ritorna 'true'
function banned($email) {
	global $banned_addresses;
	$banned = false;
	$addresses = explode("\n", $banned_addresses);
	foreach ($addresses as $add) {
		if ($email == $add) {
			$banned = true;
			break;
		}
	}
	return $banned;
}

# manda mail di iscrizione avvenuta
function send_welcome($email) {
	global $welcome_msg, $welcome_subject, $list_name, $owner_email, $sig, $version;
	$welcome_subject = replace_shortcuts($welcome_subject, $email);
	$headers = "From: \"$list_name\" <$owner_email>\r\nReply-To: $owner_email\r\nX-Mailer: PHP Simply News-Letter V$version http://www.prozone.it/\r\n";
	$welcome_msg_final = $welcome_msg."\n$sig";
	$welcome_msg_final = replace_shortcuts($welcome_msg_final, $email);
	$welcome_msg_final = trim($welcome_msg_final);
	ini_set("sendmail_from", $owner_email);
	mail($email, $welcome_subject, $welcome_msg_final, $headers);
}

# manda messaggio per richiedere la conferma dell'iscrizione
function send_confirm($email) {
	global $confirm_msg, $confirm_subject, $list_name, $owner_email, $version, $sig;
	$confirm_subject = replace_shortcuts($confirm_subject, $email);
	$headers = "From: \"$list_name\" <$owner_email>\r\nReply-To: $owner_email\r\nX-Mailer: PHP Simply News-Letter V$version http://www.prozone.it/\r\n";
	$confirm_msg_final = replace_shortcuts($confirm_msg, $email)."\n$sig";
	$confirm_msg_final = trim($confirm_msg_final);
	mail($email, $confirm_subject, $confirm_msg_final, $headers);
}

# manda messaggio di cancellazione
function send_goodbye($email) {
	global $goodbye_msg, $goodbye_subject, $list_name, $owner_email, $version, $sig;
	$goodbye_subject = replace_shortcuts($goodbye_subject, $email);
	$headers = "From: \"$list_name\" <$owner_email>\r\nReply-To: $owner_email\r\nX-Mailer: PHP Simply News-Letter V$version http://www.prozone.it/\r\n";
	$goodbye_msg_final = replace_shortcuts($goodbye_msg, $email)."\n$sig";
	$goodbye_msg_final = trim($goodbye_msg_final);
	mail($email, $goodbye_subject, $goodbye_msg_final, $headers);
}

# rimpiazza le variabili nei messaggi delle mail inviate
function replace_shortcuts($msg, $email) {
	global $list_name, $HTTP_HOST, $SCRIPT_NAME;
	$conf_code = substr(strrev(md5($email).strtolower(soundex(email))),0,5);
	$msg = str_replace("%list_name%", $list_name, $msg);
	$msg = str_replace("%email%", $email, $msg);
	$msg = str_replace("%confirm_url%", "http://$HTTP_HOST$SCRIPT_NAME?action=confirm&email=$email&conf=$conf_code", $msg);
	$msg = str_replace("%subscribe_url%", "http://$HTTP_HOST$SCRIPT_NAME?action=sub&email=$email", $msg);
	$msg = str_replace("%unsubscribe_url%", "http://$HTTP_HOST$SCRIPT_NAME?action=unsub&email=$email", $msg);
	return $msg;
}

# modulo per inviare un nuovo messaggio
function admin_write_msg() {
 	global $username, $password, $owner_email, $list_name, $admin_keyword;
	$lista_sost = array ("list_name" => $list_name,
						"username" => $username,
						"password" => $password,
						"owner_email" => $owner_email,
						"admin_keyword" => $admin_keyword,
						"percentuale" => "%");
	print_template ("adm_write.html", $lista_sost);
}

# conferma il messaggio prima di spedirlo
function admin_send_msg_confirm() {
	global $username, $password, $subject, $message, $owner_email, $list_name, $list_file, $admin_keyword, $use_sig;
	$subject = stripslashes($subject);
	$message = stripslashes($message);
	$subject_1 = str_replace("\"", "%%", $subject);
	$message_1 = str_replace("\"", "%%", $message);
	if ($use_sig == "1") $view_sig = $GLOBALS['sig'];
	$lista_sost = array ("username" => $username,
						"password" => $password,
						"subject" => $subject,
						"message" => $message,
						"message_1" => $message_1,
						"subject_1" => $subject_1,
						"owner_email" => $owner_email,
						"list_name" => $list_name,
						"admin_keyword" => $admin_keyword,
						"use_sig" => $use_sig,
						"view_sig" => $view_sig);
	print_template ("adm_write_confirm.html", $lista_sost);
}

# manda il messaggio a tutti gli iscritti della lista
function admin_send_msg() {
	global $sucess_count, $fail_count, $use_sig, $username, $password, $admin_keyword, $list_name, $list_file, $owner_email, $mail_group, $group_max, $group_current, $HTTP_HOST, $SCRIPT_NAME;
	# preparazione e-mail
	set_time_limit(0);
	$subject = stripslashes($GLOBALS[subject]);
	$message = stripslashes($GLOBALS[message]);
	$subject_1 = $subject;
	$message_1 = $message;
	$subject = str_replace("%%", "\"", $subject);
	$message = str_replace("%%", "\"", $message);
	$headers = "From: \"$list_name\" <$owner_email>\r\nReply-To: $owner_email\r\nX-Sender: $owner_email\r\nX-UnsubscribeURL: http://$GLOBALS[HTTP_HOST]$GLOBALS[SCRIPT_NAME]\r\nX-Mailer: PHP Simply News-Letter $GLOBALS[version] http://www.prozone.it/\r\nX-AntiSpam: PHP Simply News-Letter did not send you this email, review below for sender info.\r\nX-AntiSpam: Sent from IP $GLOBALS[REMOTE_ADDR]\r\n";
	if ($GLOBALS[HTTP_HOST]) $headers .= "X-AntiSpam: Mail server address http://$GLOBALS[HTTP_HOST]\r\n"; 
	if ($GLOBALS['use_sig'] == "1") $message .= $GLOBALS['sig'];
	if ($sucess_count == "") {
		$sucess_count = 0;
		$fail_count = 0;
	}
	$addresses = @file($list_file) or die(print_template ("err_write.html"));
	$addresses[] = $owner_email;
	set_time_limit(0);

	# suddivisione indirizzi in sotto-array con 'tot' indirizzi ciascuno: $mail_group
	foreach ($addresses as $valore) {
		$conta++;
		$blocco_dest[$conta] = $valore;
		if ($conta == $mail_group) {
			$key_array++;
			$dest_array[$key_array] = $blocco_dest;
			$conta = 0;
			$blocco_dest = "";
		}
	}
	if ($blocco_dest != "") {
		$key_array++;
		$dest_array[$key_array] = $blocco_dest;
		$conta = 0;
		$blocco_dest = "";
	}
	# memorizza il numero massimo di gruppi ed il gruppo corrente
	$group_max = $key_array;
	if ($group_current == "") $group_current = 1;
	
	#invio mail di ogni gruppo al termine di un singolo array
	foreach ($dest_array[$group_current] as $email) {
		$email = trim($email);
		$message_new = str_replace("%unsub%", "http://$HTTP_HOST$SCRIPT_NAME?action=unsub&email=$email", $message);
		if (mail($email, $subject, $message_new, $headers)) $sucess_count++;
		else $fail_count++;
	}
	$group_current++;

	# visualizza risultati
	if ($group_current <= $group_max) {
		$lista_sost = array ("username" => $username,
						"password" => $password,
						"subject" => $subject,
						"message" => $message,
						"message_1" => $message_1,
						"subject_1" => $subject_1,
						"owner_email" => $owner_email,
						"list_name" => $list_name,
						"admin_keyword" => $admin_keyword,
						"group_max" => $group_max,
						"group_current" => $group_current,
						"sucess_count" => $sucess_count,
						"fail_count" => $fail_count,
						"use_sig" => $use_sig,
						"view_sig" => $view_sig);
		print_template ("adm_write_confirmgroup.html", $lista_sost);
	}
	else {
		$lista_sost = array ("sucess" => $sucess_count,
							"fail" => $fail_count);
		print_template ("adm_send.html", $lista_sost);
	}
}

# visualizza gli iscritti alla lista
function admin_view_subs() {
	global $per_page, $page;
	if (!$per_page) $per_page = $GLOBALS[default_per_page];
	if (!$page) $page = 1;
	$address_arr = @file($GLOBALS[list_file]) or die(print_template ("err_write_empty.html"));
	sort($address_arr);
	$count = count($address_arr);
	$total_page = floor($count/$per_page) + 1;
	$start_num = (($page * $per_page) - $per_page) + 1;
	if (($page+1) > $total_page) $end_num = $count;
	else $end_num = $page * $per_page;
	
	# crea elenco indirizzi per tabella iscritti: $elenco_indirizzi
	$elenco_indirizzi = "";
	$start1 = $start_num -1;
	$end_num--;
	$j = $start_num;
	for($i = $start1; $i <= $end_num; $i++) {
		$elenco_indirizzi .= "<tr bgcolor=#FFFFFF><td align=\"center\">$j</td><td align=\"center\">$address_arr[$i]</td></tr>\n";
		$j++;
	}

	# crea elenco pagine a pie' di pagina tipo google: $prec_succ
	$prec_succ = "";
	if ($count > $per_page) {
		$prev_page = $page - 1;
		$next_page = $page + 1;
		# numero di pagina a pie' di pagina
		$prec_succ .= "<br>";
		if ($prev_page > 0) {
			$prec_succ .= "<a href=\"\" Onclick=\"page.value='$prev_page';per_page.value='$per_page';submit();return false;\">Precedenti $per_page</a>";
			$prec_succ .= " | ";
	 	}
		for($i=1; $i <= $total_page; $i++) {
  			if ($i == $page) {
				$prec_succ .= "<b>$i</b> | ";
			}
			else {
				$prec_succ .= "<a href=\"\" Onclick=\"per_page.value='$per_page';page.value='$i';submit();return false;\">$i</a> | ";
			}
		}
		if ($next_page <= $total_page) {
			$prec_succ .= "<a href=\"\" Onclick=\"per_page.value='$per_page';page.value='$next_page';submit();return false;\">Successive $per_page</a>";
		}
		$prec_succ .= "<br>";
	}

	# output pagina
	$end_num = $end_num + 1;
	$lista_sost = array ("count" => $count,
						"start_num" => $start_num,
						"end_num" => $end_num,
						"elenco_indirizzi" => $elenco_indirizzi,
						"username" => $username,
						"password" => $password,
						"admin_keyword" => $admin_keyword,
						"prec_succ" => $prec_succ,
						"page" => $page,
						"total_page" => $total_page);
	print_template ("adm_sublist.html", $lista_sost);
}

# aggiunge o elimina iscritti via pannello admin
function admin_add_remove() {
	global $username, $password, $remove_address_arr, $list_file, $x, $y, $admin_keyword, $email, $admin_add_email_opt, $list_name;
	if ($x == "remove") {
		$file = @file($list_file) or die(print_template ("err_write_empty.html"));
		sort($file);
		foreach ($file as $key => $address) {
			$address = trim($address);
			$address_list .= "<option value=\"$address\">&nbsp;$address&nbsp;&nbsp;&nbsp;</option>\n";
		}
		$lista_sost = array ("username" => $username,
						"password" => $password,
						"admin_keyword" => $admin_keyword,
						"address_list" => $address_list);
		print_template ("adm_remove.html", $lista_sost);
	}
	elseif ($x == "remove_submit") {
		$sucess_count = 0;
		foreach ($remove_address_arr as $rm_add) {
			if (remove_from_list($rm_add)) {
				$sucess_count++;
			}
		}
		$lista_sost = array ("sucess_count" => $sucess_count);
		print_template ("adm_remove_ok.html", $lista_sost);
	}
	elseif ($x == "remove_all") {
		$lista_sost = array ("username" => $username,
						"password" => $password,
						"admin_keyword" => $admin_keyword);
		print_template ("adm_removeall.html", $lista_sost);
		}
		elseif ($x == "remove_all_submit") {
			$fd = fopen($list_file, "w") or die(print_template ("err_write.html"));
			fclose($fd);
			print_template ("adm_removeall_ok.html");
		}
		elseif ($x == "add") {
			$email = trim($email);
			$welcome = "off";
			$confirm = "off";
			if ($admin_add_email_opt == "confirm") $confirm = "1";
			elseif ($admin_add_email_opt == "welcome") $welcome = "1";
			if ($y == "single") subscribe($email, $confirm, $welcome);
			elseif ($y == "many") {
				$email_arr = explode("\n", $email);
				foreach ($email_arr as $email) subscribe(trim($email), $confirm, $welcome);
			}
		else admin_print_add();
	}
	admin_print_addremove_options();
}

# modulo per aggiungere gli indirizzi 
function admin_print_add() {
	global $username, $password, $admin_keyword;
	$lista_sost = array ("username" => $username,
						"password" => $password,
						"admin_keyword" => $admin_keyword);
	print_template ("adm_add.html", $lista_sost);
}

# opzioni del menù aggiungi o rimuovi indirizzo
function admin_print_addremove_options() {
	global $username, $password, $admin_keyword;
	$lista_sost = array ("username" => $username,
						"password" => $password,
						"admin_keyword" => $admin_keyword);
	print_template ("adm_optionaddress.html", $lista_sost);
}

# Visualizza le opzioni admin
function admin_print_options() {
	global $username, $password, $admin_keyword;
	$lista_sost = array ("username" => $username,
						"password" => $password,
						"admin_keyword" => $admin_keyword);
	print_template ("adm_option.html", $lista_sost);
}

# cambia la configurazione dello script
function admin_config() {
	global $s, $config_file, $newsig, $newusername, $newbanned_addresses, $newpassword, $newpassword1, $newlist_name, $newlist_file, $newmail_group, $newowner_email, $newsend_welcome, $newwelcome_subject, $newwelcome_msg, $newsend_goodbye, $newgoodbye_subject, $newgoodbye_msg, $newsend_confirm, $newconfirm_subject, $newconfirm_msg, $newdefault_per_page;
	if ($s == "submit") {
		$main_msg = "<font color=#FF0000>ERRORE: I cambiamenti non sono stati salvati</font>";
		$back_button = "<br><br><input align=\"center\" type=\"submit\" value=\"Torna indietro\" OnClick=\"history.go(-1); return true;\">";
		if (!valid_email($newowner_email)) $msg = "E-mail proprietario non valida.".$back_button;
		elseif ($newusername == "") $msg = "Non è stato inserito il nome utente.".$back_button;
		elseif ($newpassword == "") $msg = "Non è stata specificata la password.".$back_button;
		elseif ($newpassword1 != $newpassword) $msg = "Le password inserite non coincidono.".$back_button;
		elseif (strlen($newpassword) < 4) $msg = "Inserire una password di almeno 4 caratteri.".$back_button;
		elseif ($newlist_name == "") $msg = "Specificare il nome della news-letter.".$back_button;
		elseif ($newlist_file == "") $msg = "Non è stato inserito il percorso del file degli indirizzi.".$back_button;
		elseif ($newmail_group == "") $msg = "Specificare il numero anti-timeout di mail inviate.".$back_button;
		elseif (!is_numeric($newmail_group)) $msg = "Il valore del numero anti-timeout deve essere un numero intero.".$back_button;
		elseif ($newconfirm_subject == "") $msg = "Specificare l'oggetto per la mail di conferma.".$back_button;
		elseif ($newconfirm_msg == "") $msg = "Specificare il messaggio per la mail di conferma.".$back_button;
		elseif ($newwelcome_subject == "") $msg = "Specificare l'oggetto per la mail di benvenuto.".$back_button;
		elseif ($newwelcome_msg == "") $msg = "Specificare il messaggio per la mail di benvenuto.".$back_button;
		elseif ($newgoodbye_subject == "") $msg = "Specificare l'oggetto per la mail di rimozione.".$back_button;
		elseif ($newgoodbye_msg == "") $msg = "Specificare il messaggio per la mail di rimozione.".$back_button;
		elseif ($newdefault_per_page == "") $msg = "Inserire il numero di visualizzazione utenti per pagina.".$back_button;
		elseif (!is_numeric($newdefault_per_page)) $msg = "Il valore del numero di pagina deve essere un numero intero.".$back_button;
		else {
			$fd = @fopen($config_file, "w") or die(print_template ("err_write.html"));
			$new_config = "$newusername\n$newpassword\n$newlist_name\n$newlist_file\n$newmail_group\n$newowner_email\n$newsend_confirm\n$newconfirm_subject\n$newsend_welcome\n$newwelcome_subject\n$newsend_goodbye\n$newgoodbye_subject\n$newdefault_per_page\n";
			fputs($fd, $new_config);
			fputs($fd, "<conferma>\n$newconfirm_msg\n</conferma>\n");
			fputs($fd, "<benvenuto>\n$newwelcome_msg\n</benvenuto>\n");
			fputs($fd, "<firma>\n$newsig\n</firma>\n");
			fputs($fd, "<cancellazione>\n$newgoodbye_msg\n</cancellazione>\n");
			fputs($fd, "<escludi>\n$newbanned_addresses\n</escludi>");
			fclose($fd);
			$main_msg = "La configurazione e' stata aggiornata con successo";
			$msg = "Se sono stati modificati username e/o passoword bisognera' uscire ed effettuare il login nuovamente.<br>";
  		}
		$lista_sost = array ("msg" => $msg, "main_msg" => $main_msg);
		print_template ("adm_config_result.html", $lista_sost);
	}
	else admin_print_config_form();
}

# visualizza le opzioni di configurazione
function admin_print_config_form() {
	$list_file = stripslashes($list_file);
	if ($GLOBALS[send_confirm] == "1") { $send_confirm_chk = "checked"; }
	if ($GLOBALS[send_welcome] == "1") { $send_welcome_chk = "checked"; }
	if ($GLOBALS[send_goodbye] == "1") { $send_goodbye_chk = "checked"; }
	$lista_sost = array ("username" => $GLOBALS[username],
						"password" => $GLOBALS[password],
						"admin_keyword" => $GLOBALS[admin_keyword],
						"mail_group" => $GLOBALS[mail_group],
						"list_name" => $GLOBALS[list_name],
						"list_file" => stripslashes($GLOBALS[list_file]),
						"owner_email" => $GLOBALS[owner_email],
						"default_per_page" => $GLOBALS[default_per_page],
						"banned_addresses" => $GLOBALS[banned_addresses],
						"sig" => $GLOBALS[sig],
						"confirm_subject" => $GLOBALS[confirm_subject],
						"confirm_msg" => $GLOBALS[confirm_msg],
						"goodbye_subject" => $GLOBALS[goodbye_subject],
						"goodbye_msg" => $GLOBALS[goodbye_msg],
						"welcome_subject" => $GLOBALS[welcome_subject],
						"welcome_msg" => $GLOBALS[welcome_msg],
						"send_confirm_chk" => $send_confirm_chk,
						"send_welcome_chk" => $send_welcome_chk,
						"send_goodbye_chk" => $send_goodbye_chk,
						"percentuale" => "%");
	print_template ("adm_config.html", $lista_sost);
}

# Schermata di login
function admin_print_logon($msg) {
	$lista_sost = array ("msg" => $msg);
	print_template ("adm_login.html", $lista_sost);
}

# modulo di iscrizione
function print_subscribe_form() {
	global $list_name;
	$lista_sost = array ("list_name" => $list_name);
	print_template ("sub_uns.html", $lista_sost);
}

# Stampa l'intestazione di pagina
function print_header() {
	global $list_name;
	$lista_sost = array ("list_name" => $list_name);
	print_template ("header.html", $lista_sost);
}

# Stampa il piè di pagina
function print_footer() {
 global $version;
 $lista_sost = array ("version" => $version);
 print_template ("footer.html", $lista_sost);
}

# Legge il template, sostituisce le variabili e fa l'output
function print_template($template, $lista_sost="") {
	global $instdir_snl;
	# carica template
	$carica_tpl = file_get_contents($instdir_snl.'/template/'.$template);
	# trova e sostituisci variabili all'interno del template
	if ($lista_sost != "") {
		$carica_tpl = preg_replace( '/\%(.*)\%/Ue', '\$lista_sost["\1"]', $carica_tpl);
	}
	# visualizza template html
	echo $carica_tpl;
}

?>