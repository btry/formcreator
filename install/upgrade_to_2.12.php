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
class PluginFormcreatorUpgradeTo2_12 {
   /** @var Migration */
   protected $migration;

   /**
    * @param Migration $migration
    */
   public function upgrade(Migration $migration) {
      global $DB;

      $this->migration = $migration;

      // Convert datetime to timestamp
      $table = 'glpi_plugin_formcreator_formanswers';
      $migration->changeField($table, 'request_date', 'request_date', 'timestamp');

      $table = 'glpi_plugin_formcreator_issues';
      $migration->changeField($table, 'date_creation', 'date_creation', 'timestamp');
      $migration->changeField($table, 'date_mod', 'date_mod', 'timestamp');
      $this->addValidationPercent();

      $this->changeDropdownTreeSettings();
      $this->migrateToMultiValidation();

      $table = 'glpi_plugin_formcreator_entityconfigs';
      $this->migration->addField($table, 'is_search_visible', 'integer', ['after' => 'is_kb_separated']);
      $this->migration->addField($table, 'is_header_visible', 'integer', ['after' => 'is_search_visible']);
      $this->migration->addField($table, 'header', 'text', ['after' => 'is_header_visible']);
   }

   /**
    * Convert values field of wuestion from form
    * {"itemtype":"ITILCategory","show_ticket_categories_depth":"0","show_ticket_categories_root":"6354"}
    * to form
    * {"itemtype":"ITILCategory","show_tree_depth":-1,"show_tree_root":false}
    *
    * @return void
    */
   public function changeDropdownTreeSettings() {
      global $DB;

      $table = 'glpi_plugin_formcreator_questions';

      $request = [
         'SELECT' => ['id', 'values'],
         'FROM' => $table,
         'WHERE' => ['fieldtype' => ['dropdown']],
      ];
      foreach ($DB->request($request) as $row) {
         $newValues = $row['values'];
         $values = json_decode($row['values'], JSON_OBJECT_AS_ARRAY);
         if ($values === null) {
            continue;
         }
         $newValues = $values;
         unset($newValues['show_ticket_categories_root']);
         unset($newValues['show_ticket_categories_depth']);
         $newValues['show_tree_root'] = $values['show_ticket_categories_root'] ?? '';
         $newValues['show_tree_depth'] = $values['show_ticket_categories_depth'] ?? '-1';
         $newValues = json_encode($newValues);
         $DB->update($table, ['values' => $newValues], ['id' => $row['id']]);
      }
   }

   public function addValidationPercent() {
      $table = 'glpi_plugin_formcreator_forms';
      $this->migration->dropField($table, 'validation_required');
      $this->migration->addField($table, 'validation_percent', 'integer', ['after' => 'show_rule']);

      $table = 'glpi_plugin_formcreator_formanswers';
      $this->migration->addField($table, 'validation_percent', 'integer', ['after' => 'status']);

      $table = 'glpi_plugin_formcreator_forms_validators';
      $this->migration->addField($table, 'level', 'integer', ['after' => 'items_id', 'value' => '1']);
   }

   public function migrateToMultiValidation() {
      global $DB;

      // Need a new table now
      $DB->query(
         "CREATE TABLE IF NOT EXISTS `glpi_plugin_formcreator_formanswervalidations` (
         `id`                                int(11) NOT NULL AUTO_INCREMENT,
         `plugin_formcreator_formanswers_id` int(11) NOT NULL,
         `itemtype`                          varchar(255) NOT NULL DEFAULT '',
         `items_id`                          int(11) NOT NULL,
         `status`                            int(11) NOT NULL DEFAULT '2',
         `level`                             int(11) NOT NULL,
         PRIMARY KEY (`id`),
         UNIQUE KEY `unicity` (`plugin_formcreator_formanswers_id`,`itemtype`,`items_id`, `level`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"
      );

      $table = 'glpi_plugin_formcreator_formanswervalidations';
      $formanswerTable = 'glpi_plugin_formcreator_formanswers';

      $DB->query("INSERT INTO `$table` (
         `plugin_formcreator_formanswers_id`,
         `itemtype`,
         `items_id`,
         `status`,
         `level`
      ) (SELECT
         `$formanswerTable`.`id`,
         'User',
         `$formanswerTable`.`users_id_validator`,
         if (`$formanswerTable`.`status` < 100, `$formanswerTable`.`status`,
            if (`$formanswerTable`.`status` = 101, 2,
               if(`$formanswerTable`.`status` = 102, 4, 3))),
         '1'
         FROM `$formanswerTable`
         WHERE `users_id_validator` > 0
      )");

      $DB->query("INSERT INTO `$table` (
         `plugin_formcreator_formanswers_id`,
         `itemtype`,
         `items_id`,
         `status`,
         `level`
      ) (SELECT
         `$formanswerTable`.`id`,
         'Group',
         `$formanswerTable`.`groups_id_validator`,
         if (`$formanswerTable`.`status` < 100, `$formanswerTable`.`status`,
            if (`$formanswerTable`.`status` = 101, 2,
               if(`$formanswerTable`.`status` = 102, 4, 3))),
         '1'
         FROM `$formanswerTable`
         WHERE `groups_id_validator` > 0
      )");

      $table = 'glpi_plugin_formcreator_issues';
      $this->migration->changeField($table, 'original_id', 'items_id', 'integer');
      $this->migration->changeField($table, 'sub_itemtype', 'itemtype', 'string');
      $this->migration->dropKey($table, 'original_id_sub_itemtype');
      $this->migration->addKey($table, ['itemtype', 'items_id'], 'item');
      $this->migration->dropField($table, 'users_id_validator');
      $this->migration->dropField($table, 'groups_id_validator');
   }
}