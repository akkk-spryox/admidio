<?php
/******************************************************************************
 * Profil/Registrierung wird angelegt bzw. gespeichert
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * user_id :  ID des Benutzers, dessen Profil bearbeitet werden soll
 * new_user : 0 - (Default) vorhandenen User bearbeiten
 *            1 - Neuen Benutzer hinzufuegen.
 *            2 - Registrierung entgegennehmen
 *            3 - Registrierung zuordnen/akzeptieren
 *
 *****************************************************************************/

require("../../system/common.php");
require("../../system/classes/system_mail.php");

// Registrierung muss ausgeloggt moeglich sein
if($_GET['new_user'] != 2)
{
    require("../../system/login_valid.php");
}

// Uebergabevariablen pruefen

if(isset($_GET["user_id"]))
{
    if(is_numeric($_GET["user_id"]) == false)
    {
        $g_message->show("invalid");
    }
    $usr_id  = $_GET['user_id'];
}
else
{
    $usr_id = 0;
}

// pruefen, ob Modus neues Mitglied oder Registrierung erfassen
if(array_key_exists("new_user", $_GET))
{
    if(is_numeric($_GET['new_user']))
    {
        $new_user = $_GET['new_user'];
    }
    else
    {
        $new_user = 0;
    }
}
else
{
    $new_user = 0;
}

if($new_user == 1 || $new_user == 2)
{
    $usr_id = 0;
}

// Registrierung deaktiviert, also auch diesen Modus sperren
if(($new_user == 2 || $new_user == 3)
&& $g_preferences['registration_mode'] == 0)
{
    $g_message->show("module_disabled");
}

$_SESSION['profile_request'] = $_REQUEST;

if(!isset($_POST['usr_login_name']))
{
    $_POST['usr_login_name'] = "";
}

/*------------------------------------------------------------*/
// prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
/*------------------------------------------------------------*/
if($new_user == 0 && $g_current_user->editProfile($usr_id) == false)
{
    $g_message->show("norights");
}

$user = new User($g_db, $usr_id);

if($usr_id > 0)
{
    if($user->getValue("usr_valid") == 1)
    {
        // keine Webanmeldung, dann schauen, ob User überhaupt Mitglied in der Gliedgemeinschaft ist
        if(isMember($usr_id) == false)
        {
            // pruefen, ob der User noch in anderen Organisationen aktiv ist
            $sql    = "SELECT *
                         FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_MEMBERS. "
                        WHERE rol_valid   = 1
                          AND rol_cat_id  = cat_id
                          AND cat_org_id <> ". $g_current_organization->getValue("org_id"). "
                          AND mem_rol_id  = rol_id
                          AND mem_valid   = 1
                          AND mem_usr_id  = $usr_id ";
            $g_db->query($sql);
            $b_other_orga = false;

            if($g_db->num_rows() > 0)
            {
                // User, der woanders noch aktiv ist, darf in dieser Orga nicht bearbeitet werden
                $g_message->show("norights");
            }
        }
    }
}

/*------------------------------------------------------------*/
// Feldinhalte pruefen der User-Klasse zuordnen
/*------------------------------------------------------------*/

// bei Registrierung muss Loginname und Pw geprueft werden
if($new_user == 2)
{
    if(strlen($_POST['usr_login_name']) == 0)
    {
        $g_message->show("feld", "Benutzername");
    }

    // Passwort sollte laenger als 6 Zeichen sein
    if(strlen($_POST['usr_password']) < 6)
    {
        $g_message->show("password_length");
    }

    // beide Passwortfelder muessen identisch sein
    if ($_POST['usr_password'] != $_POST['password2'])
    {
        $g_message->show("passwords_not_equal");
    }

    if(strlen($_POST['usr_password']) == 0)
    {
        $g_message->show("feld", "Passwort");
    }
}

// nun alle Profilfelder pruefen
foreach($user->userFieldData as $field)
{
    $post_id = "usf-". $field->getValue("usf_id");    
    
    if(isset($_POST[$post_id])) 
    {
        $update_field = true;
        
        // gesperrte Felder duerfen nur von berechtigten Benutzern geaendert werden 
        // Ausnahme bei der Registrierung
        if($field->getValue("usf_disabled") == 1 && $g_current_user->editUsers() == false && $new_user != 2)
        {
            $update_field = false;
        }

        // versteckte Felder duerfen nur im eigenen Profil, oder mit dem Recht alle User
        // zu bearbeiten, geaendert werden
        if($field->getValue("usf_hidden") == 1 && $g_current_user->editUsers() == false 
        && $usr_id != $g_current_user->getValue("usr_id"))
        {
            $update_field = false;
        }
        
        if($update_field)
        {    
            // Pflichtfelder muessen gefuellt sein
            // E-Mail bei Restrierung immer !!!
            if(($field->getValue("usf_mandatory") == 1 && strlen($_POST[$post_id]) == 0)
            || ($new_user == 2 && $field->getValue("usf_name") == "E-Mail" && strlen($_POST[$post_id]) == 0))
            {
                $g_message->show("feld", $field->getValue("usf_name"));
            }
            
            if(strlen($_POST[$post_id]) > 0)
            {
                // Pruefungen fuer die entsprechenden Datentypen
                if($field->getValue("usf_type") == "CHECKBOX")
                {
                    // Checkbox darf nur 1 oder 0 haben
                    if($_POST[$post_id] != 0 && $_POST[$post_id] != 1)
                    {
                        $g_message->show("invalid");
                    }
                }
                elseif($field->getValue("usf_type") == "DATE")
                {
                    // Datum muss gueltig sein und formatiert werden
                    if(dtCheckDate($_POST[$post_id]) == false)
                    {
                        $g_message->show("date_invalid", $field->getValue("usf_name"));
                    }
                    $_POST[$post_id] = dtFormatDate($_POST[$post_id], "Y-m-d");
                }
                elseif($field->getValue("usf_type") == "EMAIL")
                {
                    // Pruefung auf gueltige E-Mail-Adresse
                    if(!isValidEmailAddress($_POST[$post_id]))
                    {
                        $g_message->show("email_invalid");
                    }        
                }
                elseif($field->getValue("usf_type") == "NUMERIC")
                {
                    // Zahl muss numerisch sein
                    if(is_numeric(strtr($_POST[$post_id], ",.", "00")) == false)
                    {
                        $g_message->show("field_numeric", $field->getValue("usf_name"));
                    }
                }
            }
    
            $user->setValue($field->getValue("usf_name"), $_POST[$post_id]);
        }
    }
    else
    {
        // Checkboxen uebergeben bei 0 keinen Wert, deshalb diesen hier setzen
        if($field->getValue("usf_type") == "CHECKBOX")
        {
            $user->setValue($field->getValue("usf_name"), "0");
        }
    }
}

$login_name_changed = false;
$forum_old_username = "";

if($g_current_user->isWebmaster() || $new_user > 0)
{
    // Loginname darf nur vom Webmaster bzw. bei Neuanlage geaendert werden    
    if($_POST['usr_login_name'] != $user->getValue("usr_login_name"))
    {
        if(strlen($_POST['usr_login_name']) > 0)
        {
            // pruefen, ob der Benutzername bereits vergeben ist
            $sql = "SELECT usr_id FROM ". TBL_USERS. "
                     WHERE usr_login_name LIKE '". $_POST['usr_login_name']. "'";
            $g_db->query($sql);

            if($g_db->num_rows() > 0)
            {
                $row = $g_db->fetch_array();

                if(strcmp($row['usr_id'], $usr_id) != 0)
                {
                    $g_message->show("login_name");
                }
            }

            // pruefen, ob der Benutzername bereits im Forum vergeben ist, 
            // Benutzernamenswechesel und diese Dinge
            if($g_preferences['enable_forum_interface'])
            {
                // pruefen, ob der Benutzername bereits im Forum vergeben ist
                if($g_forum->userExists($_POST['usr_login_name']))
                {
                    $g_message->show("login_name_forum");
                }
                
                // bisherigen Loginnamen merken, damit dieser spaeter im Forum geaendert werden kann
                $forum_old_username = "";
                if(strlen($user->getValue("usr_login_name")) > 0)
                {
                    $forum_old_username = $user->getValue("usr_login_name");
                }
            }
        }

        $login_name_changed = true;
        $user->setValue("usr_login_name", $_POST['usr_login_name']);
    }    
}

// falls Registrierung, dann die entsprechenden Felder noch besetzen
if($new_user == 2)
{
    $user->setValue("usr_valid", 0);
    $user->setValue("usr_reg_org_shortname", $g_current_organization->getValue("org_shortname"));
    $user->setValue("usr_password", $_POST['usr_password']);
}


// Falls der User sich registrieren wollte, aber ein Captcha geschaltet ist,
// muss natuerlich der Code ueberprueft werden
if ($new_user == 2 && $g_preferences['enable_registration_captcha'] == 1)
{
    if ( !isset($_SESSION['captchacode']) || strtoupper($_SESSION['captchacode']) != strtoupper($_POST['captcha']) )
    {
        $g_message->show("captcha_code");
    }
}

/*------------------------------------------------------------*/
// Benutzerdaten in Datenbank schreiben
/*------------------------------------------------------------*/

$ret_code = $user->save();        

if($ret_code != 0)
{
    $g_message->show("mysql", $ret_code);
}

// wurde der Loginname vergeben oder geaendert, so muss ein Forumaccount gepflegt werden
// bei einer Bestaetigung der Registrierung muss der Account aktiviert werden
if($g_preferences['enable_forum_interface'] && ($login_name_changed || $new_user == 3))
{
    $set_admin = false;
    if($g_preferences['forum_set_admin'] == 1 && $user->isWebmaster())
    {
        $set_admin = true;
    }
    $g_forum->userSave($user->getValue("usr_login_name"), $user->getValue("usr_password"), $user->getValue("E-Mail"), $forum_old_username, $new_user, $set_admin);
}

// wenn Daten des eingeloggten Users geaendert werden, dann Session-Variablen aktualisieren
if($user->getValue("usr_id") == $g_current_user->getValue("usr_id"))
{
    $g_current_user = $user;
}

unset($_SESSION['profile_request']);
$_SESSION['navigation']->deleteLastUrl();

// hier auf Modus pruefen, damit kein Konflikt mit Editieren der Webanmeldung entsteht
if($new_user == 3)
{
    /*------------------------------------------------------------*/
    // neuer Benutzer wurde ueber Webanmeldung angelegt und soll nun zugeordnet werden
    /*------------------------------------------------------------*/

    // User auf aktiv setzen
    $user->setValue("usr_valid", 1);
    $user->setValue("usr_reg_org_shortname", "");
    $user->save();

    // nur ausfuehren, wenn E-Mails auch unterstuetzt werden
    if($g_preferences['enable_system_mails'] == 1)
    {
        // Mail an den User schicken, um die Anmeldung zu bestaetigen
        $sysmail = new SystemMail($g_db);
        $sysmail->addRecipient($user->getValue("E-Mail"), $user->getValue("Vorname"). " ". $user->getValue("Nachname"));
        $sysmail->sendSystemMail("SYSMAIL_REGISTRATION_USER", $user);
    }

    // neuer User -> Rollen zuordnen
    $location = "Location: roles.php?user_id=". $user->getValue("usr_id"). "&new_user=1";
    header($location);
    exit();
}
elseif($new_user == 2)
{
    /*------------------------------------------------------------*/
    // Registrierung eines neuen Benutzers
    // -> E-Mail an alle Webmaster schreiben
    /*------------------------------------------------------------*/
    $err_code = "anmeldung";
    $err_text = "";

    // nur ausfuehren, wenn E-Mails auch unterstuetzt werden und die Webmasterbenachrichtung aktiviert ist
    if($g_preferences['enable_system_mails'] == 1 && $g_preferences['enable_registration_admin_mail'] == 1)
    {
        $sql = "SELECT DISTINCT first_name.usd_value as first_name, last_name.usd_value as last_name, email.usd_value as email
                  FROM ". TBL_ROLES. ", ". TBL_CATEGORIES. ", ". TBL_MEMBERS. ", ". TBL_USERS. "
                 RIGHT JOIN ". TBL_USER_DATA. " email
                    ON email.usd_usr_id = usr_id
                   AND email.usd_usf_id = ". $g_current_user->getProperty("E-Mail", "usf_id"). "
                   AND LENGTH(email.usd_value) > 0
                  LEFT JOIN ". TBL_USER_DATA. " first_name
                    ON first_name.usd_usr_id = usr_id
                   AND first_name.usd_usf_id = ". $g_current_user->getProperty("Vorname", "usf_id"). "
                  LEFT JOIN ". TBL_USER_DATA. " last_name
                    ON last_name.usd_usr_id = usr_id
                   AND last_name.usd_usf_id = ". $g_current_user->getProperty("Nachname", "usf_id"). "
                 WHERE rol_approve_users = 1
                   AND rol_cat_id        = cat_id
                   AND cat_org_id        = ". $g_current_organization->getValue("org_id"). "
                   AND mem_rol_id        = rol_id
                   AND mem_valid         = 1
                   AND mem_usr_id        = usr_id
                   AND usr_valid         = 1 ";
        $result = $g_db->query($sql);

        while($row = $g_db->fetch_array($result))
        {
            // Mail an die Webmaster schicken, dass sich ein neuer User angemeldet hat
            $sysmail = new SystemMail($g_db);
            $sysmail->addRecipient($row['email'], $row['first_name']. " ". $row['last_name']);

            if($sysmail->sendSystemMail("SYSMAIL_REGISTRATION_WEBMASTER", $user) == false)
            {
                $err_code = "mail_not_send";
                $err_text = $row['email'];
            }
        }
    }

    // nach Registrierung auf die Startseite verweisen
    $g_message->setForwardUrl($g_homepage);
    $g_message->show($err_code, $err_text);
}

/*------------------------------------------------------------*/
// auf die richtige Seite weiterleiten
/*------------------------------------------------------------*/

if($usr_id == 0)
{
    // neuer User -> Rollen zuordnen
    header("Location: $g_root_path/adm_program/modules/profile/roles.php?user_id=". $user->getValue("usr_id"). "&new_user=1");
    exit();
}
elseif($new_user == 0 && $user->getValue("usr_valid") == 0)
{
    // neue Registrierung bearbeitet
    $g_message->setForwardUrl($_SESSION['navigation']->getPreviousUrl(), 2000);
    $g_message->show("save");
}
else
{
    // zur Profilseite zurueckkehren    
    $g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
    $g_message->show("save");
}
?>

