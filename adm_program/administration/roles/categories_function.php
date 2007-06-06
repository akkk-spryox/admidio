<?php
/******************************************************************************
 * Verschiedene Funktionen fuer Kategorien
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * cat_id: ID der Rollen-Kategorien
 * type :  Typ der Kategorie, die angelegt werden sollen
 *         ROL = Rollenkategorien
 *         LNK = Linkkategorien
 * mode:   1 - Kategorie anlegen oder updaten
 *         2 - Kategorie loeschen
 *         3 - Frage, ob Kategorie geloescht werden soll
 *
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/
 
require("../../system/common.php");
require("../../system/login_valid.php");

// lokale Variablen der Uebergabevariablen initialisieren
$req_type   = "";
$req_cat_id = 0;

// Uebergabevariablen pruefen

// Modus und Rechte pruefen
if(isset($_GET['type']))
{
    if($_GET['type'] != "ROL" && $_GET['type'] != "LNK" && $_GET['type'] != "USF")
    {
        $g_message->show("invalid");
    }
    if($_GET['type'] == "ROL" && $g_current_user->assignRoles() == false)
    {
        $g_message->show("norights");
    }
    if($_GET['type'] == "LNK" && $g_current_user->editWeblinksRight() == false)
    {
        $g_message->show("norights");
    }
    if($_GET['type'] == "USF" && $g_current_user->editUser() == false)
    {
        $g_message->show("norights");
    }
    $req_type = $_GET['type'];
}
else
{
    $g_message->show("invalid");
}

if(is_numeric($_GET["mode"]) == false
|| $_GET["mode"] < 1 || $_GET["mode"] > 3)
{
    $g_message->show("invalid");
}

if(isset($_GET['cat_id']))
{
    if(is_numeric($_GET['cat_id']) == false)
    {
        $g_message->show("invalid");
    }
    $req_cat_id = $_GET['cat_id'];
}

$err_code = "";
$err_text = "";

if($_GET['mode'] == 1)
{
    // Feld anlegen oder updaten

    $_SESSION['categories_request'] = $_REQUEST;
    $category_name = strStripTags($_POST['name']);

    if(strlen($category_name) > 0)
    {
        if($req_cat_id == 0)
        {
            // Schauen, ob die Kategorie bereits existiert
            $sql    = "SELECT COUNT(*) as count 
                         FROM ". TBL_CATEGORIES. "
                        WHERE (  cat_org_id  = $g_current_organization->id
                              OR cat_org_id IS NULL )
                          AND cat_type   = {0}
                          AND cat_name   LIKE {1} ";
            $sql    = prepareSQL($sql, array($req_type, $category_name));
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
            $row = mysql_fetch_array($result);

            if($row['count'] > 0)
            {
                $g_message->show("category_exist");
            }      
        }

        if(array_key_exists("hidden", $_POST))
        {
            $hidden = 1;
        }
        else
        {
            $hidden = 0;
        }

        if($req_cat_id > 0)
        {
            $sql = "UPDATE ". TBL_CATEGORIES. "
                       SET cat_name   = {0}
                         , cat_hidden = $hidden
                     WHERE cat_id     = {1}";
        }
        else
        {
            // Feld in Datenbank hinzufuegen
            $sql    = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden)
                                                 VALUES ($g_current_organization->id, {2}, {0}, $hidden) ";
        }
        $sql    = prepareSQL($sql, array(trim($category_name), $req_cat_id, $req_type));
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);
       
        $_SESSION['navigation']->deleteLastUrl();
        unset($_SESSION['categories_request']);
    }
    else
    {
        // es sind nicht alle Felder gefuellt
        $err_text = "Name";
        $err_code = "feld";
    }

    if(strlen($err_code) > 0)
    {
        $g_message->show($err_code, $err_text);
    }

    $err_code = "save";
}
elseif($_GET['mode'] == 2 || $_GET["mode"] == 3)
{
    // Kategorie loeschen

    $sql = "SELECT cat_name, cat_system FROM ". TBL_CATEGORIES. "
             WHERE cat_id = $req_cat_id ";
    $result = mysql_query($sql, $g_adm_con);
    db_error($result,__FILE__,__LINE__);
    $row = mysql_fetch_array($result);

    if($row['cat_system'] == 1)
    {
        // Systemfelder duerfen nicht geloescht werden
        $g_message->show("invalid");
    }
    
    if($_GET['mode'] == 2)
    {
        // erst einmal zugehoerige Daten loeschen
        if($req_type == 'ROL')
        {
            $sql    = "DELETE FROM ". TBL_ROLES. "
                        WHERE rol_cat_id = $req_cat_id ";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
        }
        elseif($req_type == 'LNK')
        {
            $sql    = "DELETE FROM ". TBL_LINKS. "
                        WHERE lnk_cat_id = $req_cat_id ";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
        }
        elseif($req_type == 'USF')
        {
            $sql    = "DELETE FROM ". TBL_USER_FIELDS. "
                        WHERE usf_cat_id = $req_cat_id ";
            $result = mysql_query($sql, $g_adm_con);
            db_error($result,__FILE__,__LINE__);
        }

        // Feld loeschen
        $sql    = "DELETE FROM ". TBL_CATEGORIES. "
                    WHERE cat_id = $req_cat_id";
        $result = mysql_query($sql, $g_adm_con);
        db_error($result,__FILE__,__LINE__);

        $err_code = "delete";
    }
    else
    {
        // Frage, ob Kategorie geloescht werden soll
        $g_message->setForwardYesNo("$g_root_path/adm_program/administration/roles/categories_function.php?cat_id=$req_cat_id&mode=2&type=$req_type");
        $g_message->show("delete_category", utf8_encode($row['cat_name']), "Löschen");
    }
}
         
// zur Kategorienuebersicht zurueck
$g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
$g_message->show($err_code);
?>