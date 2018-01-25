<?php
/**
 ***********************************************************************************************
 * Overview and maintenance of all menus
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 ****************************************************************************/

require_once(__DIR__ . '/../../system/common.php');

// Rechte pruefen
if(!$gCurrentUser->isAdministrator())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$headline = $gL10n->get('SYS_MENU');

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

$page->addJavascript('
    function moveMenu(direction, menID) {
        var actRow = document.getElementById("row_" + menID);
        var childs = actRow.parentNode.childNodes;
        var prevNode    = null;
        var nextNode    = null;
        var actRowCount = 0;
        var actSequence = 0;
        var secondSequence = 0;

        // erst einmal aktuelle Sequenz und vorherigen/naechsten Knoten ermitteln
        for (i = 0; i < childs.length; i++) {
            if (childs[i].tagName === "TR") {
                actRowCount++;
                if (actSequence > 0 && nextNode === null) {
                    nextNode = childs[i];
                }

                if (childs[i].id === "row_" + menID) {
                    actSequence = actRowCount;
                }

                if (actSequence === 0) {
                    prevNode = childs[i];
                }
            }
        }

        // entsprechende Werte zum Hoch- bzw. Runterverschieben ermitteln
        if (direction === "UP") {
            if (prevNode !== null) {
                actRow.parentNode.insertBefore(actRow, prevNode);
                secondSequence = actSequence - 1;
            }
        } else {
            if (nextNode !== null) {
                actRow.parentNode.insertBefore(nextNode, actRow);
                secondSequence = actSequence + 1;
            }
        }

        if (secondSequence > 0) {
            // Nun erst mal die neue Position von der gewaehlten Kategorie aktualisieren
            $.get(gRootPath + "/adm_program/modules/menu/menu_function.php?men_id=" + menID + "&mode=3&sequence=" + direction);
        }
    }');

// get module menu
$menuMenu = $page->getMenu();

$gNavigation->addStartUrl(CURRENT_URL, $headline);

// define link to create new menu
$menuMenu->addItem(
    'admMenuItemNew', ADMIDIO_URL . FOLDER_MODULES . '/menu/menu_new.php',
    $gL10n->get('GBO_CREATE_VAR_ENTRY', array($gL10n->get('SYS_MENU'))), 'add.png'
);

// Create table object
$menuOverview = new HtmlTable('tbl_menues', $page, true);

// create array with all column heading values
$columnHeading = array(
    $gL10n->get('SYS_TITLE'),
    '&nbsp;',
    $gL10n->get('ORG_URL'),
    '<img class="admidio-icon-info" src="'.THEME_URL.'/icons/star.png" alt="'.$gL10n->get('CAT_DEFAULT_VAR', array($gL10n->get('MEN_MENU_ITEM'))).'" title="'.$gL10n->get('CAT_DEFAULT_VAR', array($gL10n->get('MEN_MENU_ITEM'))).'" />',
    '&nbsp;'
);
$menuOverview->setColumnAlignByArray(array('left', 'left', 'left', 'center', 'right'));
$menuOverview->addRowHeadingByArray($columnHeading);

$sql = 'SELECT *
          FROM '.TBL_MENU.'
         WHERE men_men_id_parent IS NULL
      ORDER BY men_order';
$mainMenStatement = $gDb->queryPrepared($sql);

while ($mainMen = $mainMenStatement->fetchObject())
{
    $sql = 'SELECT *
              FROM '.TBL_MENU.'
             WHERE men_men_id_parent = ? -- $mainMen->men_id
          ORDER BY men_men_id_parent DESC, men_order';
    $menuStatement = $gDb->queryPrepared($sql, array($mainMen->men_id));

    if($menuStatement->rowCount() > 0)
    {
        $menuGroup = 0;

        // Get data
        while($menuRow = $menuStatement->fetchObject())
        {

            if($menuGroup != $menuRow->men_men_id_parent)
            {
                $blockId = 'admMenu_'.$menuRow->men_men_id_parent;

                $menuOverview->addTableBody();
                $menuOverview->addRow('', array('class' => 'admidio-group-heading'));
                $menuOverview->addColumn('<span id="caret_'.$blockId.'" class="caret"></span>'.$gL10n->get($mainMen->men_name),
                                  array('id' => 'group_'.$blockId, 'colspan' => '8'));
                $menuOverview->addTableBody('id', $blockId);

                $menuGroup = $menuRow->men_men_id_parent;
            }

            if(admIsTranslationStrId($menuRow->men_name))
            {
                $menuName = $gL10n->get($menuRow->men_name);
                $menuNameDesc = $gL10n->get($menuRow->men_description);
            }
            else
            {
                $menuName = $menuRow->men_name;
                $menuNameDesc = $menuRow->men_description;
            }

            $htmlMoveRow = '<a class="admidio-icon-link" href="javascript:moveMenu(\'UP\', '.$menuRow->men_id.')"><img
                                    src="'. THEME_PATH. '/icons/arrow_up.png" alt="'.$gL10n->get('CAT_MOVE_UP', array($headline)).'" title="'.$gL10n->get('CAT_MOVE_UP', array($headline)).'" /></a>
                               <a class="admidio-icon-link" href="javascript:moveMenu(\'DOWN\', '.$menuRow->men_id.')"><img
                                    src="'. THEME_PATH. '/icons/arrow_down.png" alt="'.$gL10n->get('CAT_MOVE_DOWN', array($headline)).'" title="'.$gL10n->get('CAT_MOVE_DOWN', array($headline)).'" /></a>';

            $htmlStandardMenu = '&nbsp;';
            if($menuRow->men_standart == 1)
            {
                $htmlStandardMenu = '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/star.png" alt="'.$gL10n->get('CAT_DEFAULT_VAR', array($gL10n->get('MEN_MENU_ITEM'))).'" title="'.$gL10n->get('CAT_DEFAULT_VAR', array($gL10n->get('MEN_MENU_ITEM'))).'" />';
            }

            $menuAdministration = '<a class="admidio-icon-link" href="'.safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/menu/menu_new.php', array('men_id' => $menuRow->men_id)). '"><img
                                        src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';

            // don't allow delete for standart menus
            if($menuRow->men_standart == 0)
            {
                $menuAdministration .= '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                            href="'.safeUrl(ADMIDIO_URL.'/adm_program/system/popup_message.php', array('type' => 'men', 'element_id' => 'row_men_'.
                                            $menuRow->men_id, 'name' => $menuName, 'database_id' => $menuRow->men_id)).'"><img
                                               src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
            }

            // create array with all column values
            $columnValues = array(
                '<a href="'.safeUrl(ADMIDIO_URL.FOLDER_MODULES.'/menu/menu_new.php', array('men_id' => $menuRow->men_id)). '" title="'.$menuNameDesc.'">'.$menuName.'</a>',
                $htmlMoveRow,
                '<a href="'.ADMIDIO_URL. $menuRow->men_url. '" title="'.$menuNameDesc.'">'. $menuRow->men_url. '</a>',
                $htmlStandardMenu,
                $menuAdministration
            );
            $menuOverview->addRowByArray($columnValues, 'row_'. $menuRow->men_id);
        }
    }
}

$page->addHtml($menuOverview->show(false));
$page->show();
