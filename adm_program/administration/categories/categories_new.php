<?php
/******************************************************************************
 * Kategorien anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * cat_id: ID der Rollen-Kategorien, die bearbeitet werden soll
 * type :  Typ der Kategorie, die angelegt werden sollen
 *         ROL = Rollenkategorien
 *         LNK = Linkkategorien
 *
 ****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_category.php');

// lokale Variablen der Uebergabevariablen initialisieren
$req_cat_id = 0;

// Uebergabevariablen pruefen
$title = 'Kategorie';
if (isset($_GET['title'])) 
{
   $title = $_GET['title'];
}

// Modus und Rechte pruefen
if(isset($_GET['type']))
{
    if($_GET['type'] != 'ROL' && $_GET['type'] != 'LNK' && $_GET['type'] != 'USF' && $_GET['type'] != 'DAT')
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    if($_GET['type'] == 'ROL' && $g_current_user->assignRoles() == false)
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
    if($_GET['type'] == 'LNK' && $g_current_user->editWeblinksRight() == false)
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
    if($_GET['type'] == 'USF' && $g_current_user->editUsers() == false)
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
    if($_GET['type'] == 'DAT' && $g_current_user->editUsers() == false)
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
}
else
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

if(isset($_GET['cat_id']))
{
    if(is_numeric($_GET['cat_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_cat_id = $_GET['cat_id'];
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// UserField-objekt anlegen
$category = new TableCategory($g_db);

if($req_cat_id > 0)
{
    $category->readData($req_cat_id);

    // Pruefung, ob die Kategorie zur aktuellen Organisation gehoert bzw. allen verfuegbar ist
    if($category->getValue('cat_org_id') >  0
    && $category->getValue('cat_org_id') != $g_current_organization->getValue('org_id'))
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
}

if(isset($_SESSION['categories_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['categories_request'] as $key => $value)
    {
        if(strpos($key, 'cat_') == 0)
        {
            $category->setValue($key, stripslashes($value));
        }
    }
    unset($_SESSION['categories_request']);
}

// Kategorie 'Stammdaten' bei Profilfeldern darf nicht umbenannt werden
$html_readonly = '';
$field_focus   = 'cat_name';
if($category->getValue('cat_type') == 'USF' && $category->getValue('cat_name') == 'Stammdaten')
{
    $html_readonly = ' readonly="readonly" ';
    $field_focus   = 'btn_save';
}

// Html-Kopf ausgeben
if($req_cat_id > 0)
{
    $g_layout['title']  = $title.' bearbeiten';
}
else
{
    $g_layout['title']  = $title.' anlegen';
}
$g_layout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#'.$field_focus.'").focus();
        }); 
    //--></script>';
require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '
<form action="'.$g_root_path.'/adm_program/administration/categories/categories_function.php?cat_id='.$req_cat_id.'&amp;type='. $_GET['type']. '&amp;mode=1" method="post">
<div class="formLayout" id="edit_categories_form">
    <div class="formHead">'. $g_layout['title']. '</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="cat_name">Name:</label></dt>
                    <dd>
                        <input type="text" id="cat_name" name="cat_name" '.$html_readonly.' style="width: 150px;" maxlength="30" value="'. $category->getValue('cat_name'). '" />
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>';

            if($_GET['type'] == 'USF')
            {
                // besitzt die Organisation eine Elternorga oder hat selber Kinder, so kann die Kategorie fuer alle Organisationen sichtbar gemacht werden
                if($category->getValue('cat_system') == 0
                && $g_current_organization->countAllRecords() > 1)
                {
                    echo '
                    <li>
                        <dl>
                            <dt>&nbsp;</dt>
                            <dd>
                                <input type="checkbox" id="cat_org_id" name="cat_org_id" tabindex="3" ';
                                if($category->getValue('cat_org_id') == 0)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                                <label for=\"cat_org_id\">'.$title.' für alle Organisationen sichtbar</label>
                                <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=category_global&amp;inline=true"><img 
                                    onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=category_global\',this)" onmouseout="ajax_hideTooltip()"
                                    class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title="" /></a>
                            </dd>
                        </dl>
                    </li>';
                }
            }
            else
            {
                echo '
                <li>
                    <dl>
                        <dt>
                            <label for="cat_hidden"><img src="'. THEME_PATH. '/icons/user_key.png" alt="'.$title.' nur für eingeloggte Benutzer sichtbar" /></label>
                        </dt>
                        <dd>
                            <input type="checkbox" id="cat_hidden" name="cat_hidden" ';
                                if($category->getValue('cat_hidden') == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            <label for="cat_hidden">'.$title.' nur für eingeloggte Benutzer sichtbar</label>
                        </dd>
                    </dl>
                </li>';
            }
        echo '</ul>

        <hr />

        <div class="formSubmit">
            <button id="btn_save" type="submit" value="speichern"><img src="'. THEME_PATH. '/icons/disk.png" alt="Speichern" />&nbsp;Speichern</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="Zurück" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">Zurück</a>
        </span>
    </li>
</ul>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>