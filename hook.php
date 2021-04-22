<?php
/**
 * ---------------------------------------------------------------------
 * Formcreator is a plugin which allows creation of custom forms of
 * easy access.
 * ---------------------------------------------------------------------
 * LICENSE
 *
 * This file is part of Formcreator.
 *
 * Formcreator is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Formcreator is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Formcreator. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2011 - 2021 Teclib'
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/pluginsGLPI/formcreator/
 * @link      https://pluginsglpi.github.io/formcreator/
 * @link      http://plugins.glpi-project.org/#/plugin/formcreator
 * ---------------------------------------------------------------------
 */

/**
 * Install all necessary elements for the plugin
 * @return boolean True if success
 */
function plugin_formcreator_install() {
   spl_autoload_register('plugin_formcreator_autoload');

   $version   = plugin_version_formcreator();
   $migration = new Migration($version['version']);
   require_once(__DIR__ . '/install/install.php');
   $install = new PluginFormcreatorInstall();
   if (!$install->isPluginInstalled()
      || isset($_SESSION['plugin_formcreator']['cli'])
      && $_SESSION['plugin_formcreator']['cli'] == 'force-install') {
      return $install->install($migration);
   }
   return $install->upgrade($migration);
}

/**
 * Uninstall previously installed elements of the plugin
 *
 * @return boolean True if success
 */
function plugin_formcreator_uninstall() {
   require_once(__DIR__ . '/install/install.php');
   $install = new PluginFormcreatorInstall();
   $install->uninstall();
}

/**
 * Define Dropdown tables to be manage in GLPI :
 */
function plugin_formcreator_getDropdown() {
   return [
      'PluginFormcreatorCategory' => _n('Form category', 'Form categories', 2, 'formcreator'),
   ];
}


function plugin_formcreator_addDefaultSelect($itemtype) {
   switch ($itemtype) {
      case PluginFormcreatorIssue::class:
         return "`glpi_plugin_formcreator_issues`.`itemtype`, ";
   }
   return "";
}


function plugin_formcreator_addDefaultJoin($itemtype, $ref_table, &$already_link_tables) {
   $join = '';
   switch ($itemtype) {
      case PluginFormcreatorIssue::class:
         // Get default joins for tickets
         $join = Search::addDefaultJoin(Ticket::getType(), Ticket::getTable(), $already_link_tables);
         // but we want to join in issues
         $join = str_replace('`glpi_tickets`.`id`', '`glpi_plugin_formcreator_issues`.`itemtype` = "Ticket" AND `glpi_plugin_formcreator_issues`.`items_id`', $join);
         $join = str_replace('`glpi_tickets`', '`glpi_plugin_formcreator_issues`', $join);
         $join = str_replace('`users_id_recipient`', '`requester_id`', $join);
         $join .= Search::addLeftJoin($itemtype, $ref_table, $already_link_tables, Group::getTable(), 'groups_id_validator');
         $issueSo = Search::getOptions($itemtype);
         $join .= Search::addLeftJoin(
            $itemtype,
            $ref_table,
            $already_link_tables,
            $issueSo[9]['table'],
            'users_id',
            0,
            0,
            $issueSo[9]['joinparams']
         );
         $join .= Search::addLeftJoin(
            $itemtype,
            $ref_table,
            $already_link_tables,
            $issueSo[16]['table'],
            'groups_id',
            0,
            0,
            $issueSo[16]['joinparams']
         );
         $join .= Search::addLeftJoin(
            $itemtype,
            $ref_table,
            $already_link_tables,
            $issueSo[11]['table'],
            'users_id_validate',
            0,
            0,
            $issueSo[11]['joinparams']
         );
         break;

      case PluginFormcreatorFormAnswer::class:
         $formanswerSo = Search::getOptions($itemtype);
         $join .= Search::addLeftJoin(
            $itemtype,
            $ref_table,
            $already_link_tables,
            $formanswerSo[5]['table'],
            'users_id_validate',
            0,
            0,
            $formanswerSo[5]['joinparams']
         );
         $join .= Search::addLeftJoin(
            $itemtype,
            $ref_table,
            $already_link_tables,
            $formanswerSo[7]['table'],
            'users_id_validate',
            0,
            0,
            $formanswerSo[7]['joinparams']
         );
         break;
   }
   return $join;
}

/**
 * Undocumented function
 *
 * @param string $itemtype
 * @return string
 */
function plugin_formcreator_getCondition($itemtype) {
   $table = $itemtype::getTable();
   $currentUserId = Session::getLoginUserID();

   if ($itemtype != PluginFormcreatorFormAnswer::class) {
      return '';
   }
   if (Session::haveRight('config', UPDATE)) {
      return '';
   }

   if (PluginFormcreatorCommon::canValidate()) {
      $condition = " (`$table`.`users_id_validator` = $currentUserId";
      $groups = Group_User::getUserGroups($currentUserId);
      if (count($groups) < 1) {
         $condition .= ")";
         return $condition;
      }

      // Add current user's groups to the condition
      $groupIDs = [];
      foreach ($groups as $group) {
         $groupIDs[] = $group['id'];
      }
      $groupIDs = implode(',', $groupIDs);
      $condition .= " OR `$table`.`groups_id_validator` IN ($groupIDs)";
      $condition .= ")";
      return $condition;
   }

   $condition = " `$table`.`requester_id` = $currentUserId";
   return $condition;
}

/**
 * Define specific search request
 *
 * @param  String $itemtype    Itemtype for the search engine
 * @return String          Specific search request
 */
function plugin_formcreator_addDefaultWhere($itemtype) {
   $currentUser = Session::getLoginUserID();
   switch ($itemtype) {
      case PluginFormcreatorIssue::class:
         // Use default where from Tickets
         $condition = Search::addDefaultWhere(Ticket::class);
         // Replace references to ticket tables with issues table
         $condition = str_replace('`glpi_tickets`', '`glpi_plugin_formcreator_issues`', $condition);
         $condition = str_replace('`users_id_recipient`', '`requester_id`', $condition);
         // condition where current user is a validator of the issue
         $complexJoinId = Search::computeComplexJoinID(Search::getOptions($itemtype)[9]['joinparams']);
         $condition .= " OR `glpi_users_$complexJoinId`.`id` = '$currentUser'";
         // condition where current user is a member of a validator group of the issue
         $groupList = [];
         foreach (Group_User::getUserGroups($currentUser) as $group) {
            $groupList[] = $group['id'];
         }
         $groupList = implode("', '", $groupList);
         $complexJoinId = Search::computeComplexJoinID(Search::getOptions($itemtype)[9]['joinparams']);
         $condition .= " OR `glpi_groups_$complexJoinId`.`id` IN ('$groupList')";
         // condition where current user is a validator of a issue of type ticket
         $complexJoinId = Search::computeComplexJoinID(Search::getOptions($itemtype)[11]['joinparams']);
         $condition .= " OR `glpi_users_users_id_validate_57751ba960bd8511d2ad8a01bd8487f4`.`id` = '$currentUser'";
         return "($condition)";
      break;

      case PluginFormcreatorFormAnswer::class:
         $table = $itemtype::getTable();
         if (isset($_SESSION['formcreator']['form_search_answers'])
             && $_SESSION['formcreator']['form_search_answers']) {
            // Context is displaying the answers for a given form
            $formFk = PluginFormcreatorForm::getForeignKeyField();
            return "`$table`.`$formFk` = ".
                         $_SESSION['formcreator']['form_search_answers'];
         }
         if (Session::haveRight('config', UPDATE)) {
            return '';
         }
         if (!PluginFormcreatorCommon::canValidate()) {
            return "`$table`.`requester_id` = $currentUser";
         }
         // check the user
         $formanswerValidationTable = PluginFormcreatorFormanswerValidation::getTable();
         $userType = User::getType();
         $groupType = Group::getType();
         $condition = "`$formanswerValidationTable`.`itemtype` = '$userType' AND `$formanswerValidationTable`.`items_id` = '$currentUser'";

         // check groups of the user
         $groupList = [];
         foreach (Group_User::getUserGroups($currentUser) as $group) {
            $groupList[] = $group['id'];
         }
         if (count($groupList) > 0) {
            $groupList = implode("', '", $groupList);
            $condition .= " OR
               `$formanswerValidationTable`.`itemtype` = '$groupType' AND `$formanswerValidationTable`.`items_id` IN ('$groupList')";
         }
         return "($condition)";
         break;
   }
   return '';
}

function plugin_formcreator_addWhere($link, $nott, $itemtype, $ID, $val, $searchtype) {
   $searchopt = &Search::getOptions($itemtype);
   $table     = $searchopt[$ID]["table"];
   $field     = $searchopt[$ID]["field"];

   switch ($table.".".$field) {
      case "glpi_plugin_formcreator_issues.status" :
         $tocheck = [];
         /** @var CommonITILObject $item  */
         if ($item = getItemForItemtype($itemtype)) {
            switch ($val) {
               case 'all':
                  $tocheck = array_keys($item->getAllStatusArray());
                  break;

               case Ticket::INCOMING:
                  $tocheck = $item->getNewStatusArray();
                  break;

               case 'process' :
                  // getProcessStatusArray should be an abstract method of CommonITILObject
                  $tocheck = $item->getProcessStatusArray();
                  break;

               case 'notclosed' :
                  $tocheck = $item->getAllStatusArray();
                  foreach ($item->getClosedStatusArray() as $status) {
                     unset($tocheck[$status]);
                  }
                  $tocheck = array_keys($tocheck);
                  break;

               case 'old' :
                  $tocheck = array_merge($item->getSolvedStatusArray(),
                                         $item->getClosedStatusArray());
                  break;

               case 'notold' :
                  $tocheck = $item->getAllStatusArray();
                  foreach ($item->getSolvedStatusArray() as $status) {
                     unset($tocheck[$status]);
                  }
                  foreach ($item->getClosedStatusArray() as $status) {
                     unset($tocheck[$status]);
                  }
                  unset($tocheck[PluginFormcreatorFormAnswer::STATUS_REFUSED]);

                  $tocheck = array_keys($tocheck);
                  break;
            }
         }

         if (count($tocheck) == 0) {
            $statuses = $item->getAllStatusArray();
            if (isset($statuses[$val])) {
               $tocheck = [$val];
            }
         }

         if (count($tocheck)) {
            if ($nott) {
               return $link." `$table`.`$field` NOT IN ('".implode("','", $tocheck)."')";
            }
            return $link." `$table`.`$field` IN ('".implode("','", $tocheck)."')";
         }
         break;
   }
}


function plugin_formcreator_AssignToTicket($types) {
   $types[PluginFormcreatorFormAnswer::class] = PluginFormcreatorFormAnswer::getTypeName();

   return $types;
}


function plugin_formcreator_MassiveActions($itemtype) {

   switch ($itemtype) {
      case PluginFormcreatorForm::class:
         return [
            'PluginFormcreatorForm' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Duplicate' => _x('button', 'Duplicate'),
            'PluginFormcreatorForm' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Transfert' => __('Transfer'),
            'PluginFormcreatorForm' . MassiveAction::CLASS_ACTION_SEPARATOR . 'Export' => _sx('button', 'Export'),
         ];
   }
   return [];
}


function plugin_formcreator_giveItem($itemtype, $ID, $data, $num) {
   switch ($itemtype) {
      case PluginFormcreatorIssue::class:
         return PluginFormcreatorIssue::giveItem($itemtype, $ID, $data, $num);
         break;
   }

   return "";
}

function plugin_formcreator_hook_add_ticket(CommonDBTM $item) {
   global $CFG_GLPI, $DB;

   if (!($item instanceof Ticket)) {
      return;
   }
   if (isset($CFG_GLPI['plugin_formcreator_disable_hook_create_ticket'])) {
      // run this hook only if the plugin is not generating tickets
      return;
   }

   $requester = $DB->request([
      'SELECT' => 'users_id',
      'FROM' => Ticket_User::getTable(),
      'WHERE' => [
         'tickets_id' => $item->getID(),
         'type' =>  '1',
      ],
      'ORDER' => ['id'],
      'LIMIT' => '1',
   ])->next();
   if ($requester === null) {
      $requester = [
         'users_id' => 0,
      ];
   }

   $validationStatus = PluginFormcreatorCommon::getTicketStatusForIssue($item);

   $issue = new PluginFormcreatorIssue();
   $issue->add([
      'items_id'           => $item->getID(),
      'itemtype'           => 'Ticket',
      'name'               => addslashes($item->fields['name']),
      'status'             => $validationStatus,
      'date_creation'      => $item->fields['date'],
      'date_mod'           => $item->fields['date_mod'],
      'entities_id'        => $item->fields['entities_id'],
      'is_recursive'       => '0',
      'requester_id'       => $requester['users_id'],
      'comment'            => addslashes($item->fields['content']),
   ]);
}

function plugin_formcreator_hook_update_ticket(CommonDBTM $item) {
   if (!($item instanceof Ticket)) {
      return;
   }

   $id = $item->getID();

   $validationStatus = PluginFormcreatorCommon::getTicketStatusForIssue($item);

   $issue = new PluginFormcreatorIssue();
   $issue->getFromDBByCrit([
      'AND' => [
         'itemtype'  => Ticket::class,
         'items_id'  => $id
      ]
   ]);
   $issue->update([
      'id'                 => $issue->getID(),
      'items_id'           => $id,
      'display_id'         => "t_$id",
      'itemtype'           => 'Ticket',
      'name'               => addslashes($item->fields['name']),
      'status'             => $validationStatus,
      'date_creation'      => $item->fields['date'],
      'date_mod'           => $item->fields['date_mod'],
      'entities_id'        => $item->fields['entities_id'],
      'is_recursive'       => '0',
      'requester_id'       => $item->fields['users_id_recipient'],
      'comment'            => addslashes($item->fields['content']),
   ]);
}

function plugin_formcreator_hook_delete_ticket(CommonDBTM $item) {
   global $DB;

   if (!($item instanceof Ticket)) {
      return;
   }

   $id = $item->getID();

   // mark formanswers as deleted
   $iterator = $DB->request([
      'SELECT' => ['id'],
      'FROM'   => Item_Ticket::getTable(),
      'WHERE'  => [
         'itemtype'   => 'PluginFormcreatorFormAnswer',
         'tickets_id' => $id,
      ]
   ]);
   foreach ($iterator as $row) {
      $form_answer = new PluginFormcreatorFormAnswer();
      $form_answer->update([
         'id'           => $row['id'],
         'is_deleted'   => 1,
      ]);
   }

   // delete issue
   $issue = new PluginFormcreatorIssue();
   $issue->deleteByCriteria([
      'display_id'   => "t_$id",
      'itemtype' => 'Ticket'
   ], 1);
}

function plugin_formcreator_hook_restore_ticket(CommonDBTM $item) {
   plugin_formcreator_hook_add_ticket($item);
}

function plugin_formcreator_hook_purge_ticket(CommonDBTM $item) {
   if ($item instanceof Ticket) {
      $id = $item->getID();

      $issue = new PluginFormcreatorIssue();
      $issue->deleteByCriteria([
         'display_id'   => "t_$id",
         'itemtype' => 'Ticket'
      ], 1);
   }
}

function plugin_formcreator_hook_pre_purge_targetTicket(CommonDBTM $item) {
   $item->pre_purgeItem();
}

function plugin_formcreator_hook_pre_purge_targetChange(CommonDBTM $item) {
   $item->pre_purgeItem();
}

function plugin_formcreator_hook_update_ticketvalidation(CommonDBTM $item) {
   $ticket = new Ticket();
   $ticket->getFromDB($item->fields['tickets_id']);
   if ($ticket->isNewItem()) {
      // Should not happen
      return;
   }

   $status = PluginFormcreatorCommon::getTicketStatusForIssue($ticket);

   $issue = new PluginFormcreatorIssue();
   $issue->getFromDBByCrit([
      'itemtype' => Ticket::getType(),
      'items_id' => $item->fields['tickets_id']
   ]);
   if ($issue->isNewItem()) {
      return;
   }
   $issue->update(['status' => $status] + $issue->fields);
}

function plugin_formcreator_hook_update_itilFollowup($followup) {
   $itemtype = $followup->fields['itemtype'];
   if ($itemtype != Ticket::getType()) {
      return;
   }

   $item = new Ticket();
   if (!$item->getFromDB($followup->fields['items_id'])) {
      return;
   }

   $validationStatus = PluginFormcreatorCommon::getTicketStatusForIssue($item);
   $issue = new PluginFormcreatorIssue();
   $issue->getFromDBByCrit([
      'AND' => [
         'itemtype' => $itemtype,
         'items_id' => $item->getID(),
      ]
   ]);
   if ($issue->isNewItem()) {
      return;
   }
   $issue->update([
      'id'           => $issue->getID(),
      'itemtype'     => $itemtype,
      'items_id'     => $item->getID(),
      'status'       => $validationStatus,
      'date_mod'     => $item->fields['date_mod'],
   ]);
}

function plugin_formcreator_dynamicReport($params) {
   switch ($params['item_type']) {
      case PluginFormcreatorFormAnswer::class;
         if ($url = parse_url($_SERVER['HTTP_REFERER'])) {
            if (strpos($url['path'],
                       Toolbox::getItemTypeFormURL("PluginFormcreatorForm")) !== false) {
               parse_str($url['query'], $query);
               if (isset($query['id'])) {
                  $item = new PluginFormcreatorForm;
                  $item->getFromDB($query['id']);
                  PluginFormcreatorFormAnswer::showForForm($item, $params);
                  return true;
               }
            }
         }
         break;
   }

   return false;
}


function plugin_formcreator_redefine_menus($menus) {
   if (!Session::getCurrentInterface() == "helpdesk") {
      return $menus;
   }

   if (PluginFormcreatorEntityconfig::getUsedConfig(
         'replace_helpdesk',
         $_SESSION['glpiactive_entity']
   )) {
      if (isset($menus['tickets'])) {
         unset($menus['tickets']);
      }
   } else {
      $newMenus = [];
      foreach ($menus as $key => $menu) {
         $newMenus[$key] = $menu;
         if ($key == 'create_ticket') {
            $newMenus['forms'] = [
               'default' => '/' . Plugin::getWebDir('formcreator', false) . '/front/formlist.php',
               'title'   => _n('Form', 'Forms', 2, 'formcreator'),
               'content' => [0 => true]
            ];
         }
      }
      $menus = $newMenus;
   }

   return $menus;
}

function plugin_formcreator_hook_update_plugin(CommonDBTM $item) {
   if ($item->fields['directory'] != 'formcreator') {
      return;
   }

   if ($item->fields['state'] != Plugin::ACTIVATED) {
      return;
   }

   // This is Formcreator, and its state switched to enabled
   PluginFormcreatorCommon::buildFontAwesomeData();
}

/**
 * Hook for timeline_actions; display a new action for a CommonITILObject
 * @see CommonITILObject
 *
 * @return void
 */
function plugin_formcreator_timelineActions($options) {
   $item = $options['item'];
   if (!$item->canDeleteItem()) {
      return;
   }

   if (!(isset($_SESSION['glpiactiveprofile']) &&
       $_SESSION['glpiactiveprofile']['interface'] == 'helpdesk')) {
      return;
   }
   echo "<li class='plugin_formcreator_cancel_my_ticket' onclick='".
      "javascript:plugin_formcreator_cancelMyTicket(".$item->fields['id'].");'>"
      ."<i class='fa'></i>".__('Cancel my ticket', 'formcreator')."</li>";
}
