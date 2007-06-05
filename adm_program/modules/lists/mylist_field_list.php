<?php
/******************************************************************************
 * Script mit HTML-Code fuer ein Feld der Eigenen-Liste-Konfiguration
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * field_number : wenn ueber Ajax nachgeladen wird, steht hier die Nummer
 *                des Feldes, welches erzeugt werden soll
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
 
$b_ajax = false;

if(isset($_POST['field_number']))
{
    // Script wurde ueber Ajax aufgerufen
    include("../../system/common.php"); 

    $i = $_POST['field_number'];
    $b_ajax = true;
}

if(isset($result_user_fields) == false)
{
    //Liste der Zusatzfelder erstellen
    $sql    =  "SELECT * 
                  FROM ". TBL_USER_FIELDS. "
                 WHERE usf_org_id IS NULL
                    OR usf_org_id = $g_current_organization->id
                 ORDER BY usf_org_id DESC, usf_name ASC";

    $result_user_fields = mysql_query($sql, $g_adm_con);
    db_error($result_user_fields,__FILE__,__LINE__);  
}

// Array um den Namen der Tabellen sinnvolle Texte zuzuweisen
$arr_col_name = array('usr_last_name'  => 'Nachname',
                      'usr_first_name' => 'Vorname',
                      'usr_address'    => 'Adresse',
                      'usr_zip_code'   => 'PLZ',
                      'usr_city'       => 'Ort',
                      'usr_country'    => 'Land',
                      'usr_phone'      => 'Telefon',
                      'usr_mobile'     => 'Handy',
                      'usr_fax'        => 'Fax',
                      'usr_email'      => 'E-Mail',
                      'usr_homepage'   => 'Homepage',
                      'usr_birthday'   => 'Geburtstag',
                      'usr_gender'     => 'Geschlecht',
                      'usr_login_name' => 'Loginname',
                      'usr_photo'      => 'Foto'
                      );

echo "<div style=\"text-align: center; width: 18%; float: left; margin-top: 5px;\">&nbsp;$i. Feld :&nbsp;</div>
    <div style=\"text-align: center; width: 37%; float: left; margin-top: 5px;\">
        <select size=\"1\" name=\"column$i\">
            <option value=\"\" ";
                if($b_ajax == true || $b_history == false)
                {
                    echo " selected ";
                }
                echo "></option>
            <optgroup label=\"Stammdaten\">";
                $value = reset($arr_col_name);
                $key   = key($arr_col_name);
                for($j = 0; $j < count($arr_col_name); $j++)
                {
                    echo "<option value=\"$key\" ";
                    if($b_ajax == false && $b_history == true)
                    {
                        // wenn Zurueck gewaehlt wurde, dann Felder mit den alten
                        // Werten vorbelegen
                        if($form_values["column$i"] == $key)
                        {
                            echo " selected ";                          
                        }
                    }
                    else
                    {
                        // Nachname und Vorname sollen in den ersten beiden
                        // Spalten vorgeschlagen werden
                        if(($key == "usr_last_name" && $i == 1 )
                        || ($key == "usr_first_name" && $i == 2 )) 
                        {
                            echo " selected ";
                        }
                    }
                    echo ">$value</option>";
                    $value = next($arr_col_name);
                    $key   = key($arr_col_name);
                }

                //ggf zusaetzliche Felder auslesen und bereitstellen
                $field_header = false;
                $msg_header   = false;

                while($uf_row = mysql_fetch_array($result_user_fields))
                {     
                    if($uf_row['usf_org_id'] != NULL
                    && $field_header == false)
                    {
                        echo "</optgroup>
                        <optgroup label=\"Zus&auml;tzliche Felder\">";
                        $field_header = true;
                    }
                    if($uf_row['usf_org_id'] == NULL
                    && $msg_header == false)
                    {
                        echo "</optgroup>
                        <optgroup label=\"Messenger\">";
                        $msg_header = true;
                    }
                    //Nur Moderatoren duerfen sich gelockte Felder anzeigen lassen 
                    if($uf_row['usf_hidden'] == 0 || $g_current_user->assignRoles())
                    {
                        echo"<option value=\"". $uf_row['usf_id']. "\"";
                        // wenn Zurueck gewaehlt wurde, dann Felder mit den alten
                        // Werten vorbelegen
                        if($b_ajax == false && $b_history == true
                        && $form_values["column$i"] == $uf_row['usf_id'])
                        {
                            echo " selected ";                          
                        }
                        echo ">"; 
                        
                        // Ajax gibt alles in UTF8 zurueck
                        if($b_ajax)
                        {
                            echo utf8_encode($uf_row['usf_name']);
                        }
                        else
                        {
                            echo $uf_row['usf_name'];
                        }
                            
                        echo "</option>";
                    }
                }    
                mysql_data_seek($result_user_fields, 0);                                
            echo "</optgroup>
        </select>&nbsp;&nbsp;
    </div>
    <div style=\"text-align: center; width: 18%; float: left; margin-top: 5px;\">
        <select size=\"1\" name=\"sort$i\">
            <option value=\"\" ";
                if($b_ajax == true || isset($form_values["sort$i"]) == false)
                {
                    echo " selected ";
                }
                echo ">&nbsp;</option>
            <option value=\"ASC\" ";
                if($b_ajax == false && $b_history == true
                && $form_values["sort$i"] == "ASC")
                {
                    echo " selected ";
                }
                echo ">A bis Z</option>
            <option value=\"DESC\" ";
                if($b_ajax == false && $b_history == true
                && $form_values["sort$i"] == "DESC")
                {
                    echo " selected ";
                }
                echo ">Z bis A</option>
        </select>
    </div>";
    if($b_ajax == false && $b_history == true)
    {
        $condition = $form_values["condition$i"];
    }
    else
    {
        $condition = "";
    }
    echo "<div style=\"text-align: center; width: 27%; float: left; margin-top: 5px;\">
        <input type=\"text\" name=\"condition$i\" size=\"15\" maxlength=\"30\" value=\"$condition\">
    </div>
    <span id=\"next_field_". ($i + 1). "\" style=\"clear: left;\"></span>";
?>