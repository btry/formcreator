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
namespace tests\units;
use GlpiPlugin\Formcreator\Tests\CommonTestCase;

class PluginFormcreatorForm_Validator extends CommonTestCase {
   public function providerGetTypeName() {
      return [
         [
            0,
            'Validators'
         ],
         [
            1,
            'Validator'
         ],
         [
            2,
            'Validators'
         ],
      ];
   }

   /**
    * @dataProvider providerGetTypeName
    *
    * @param integer $nb
    * @param string $expected
    * @return void
    */
   public function testGetTypeName($nb, $expected) {
      $instance = new $this->newTestedInstance();
      $output = $instance->getTypeName($nb);
      $this->string($output)->isEqualTo($expected);
   }

   public function testPrepareInputForAdd() {
      $instance = $this->newTestedInstance();
      $output = $instance->prepareInputForAdd([
         'uuid' => '0000',
      ]);

      $this->array($output)->HasKey('uuid');
      $this->string($output['uuid'])->isEqualTo('0000');

      $output = $instance->prepareInputForAdd([]);

      $this->array($output)->HasKey('uuid');
      $this->string($output['uuid']);
   }

   public function testExport() {
      $user = new \User;
      $user->getFromDBbyName('glpi');

      $form = $this->getForm();

      $formValidator = new \PluginFormcreatorForm_Validator();
      $formValidator->add([
         'plugin_formcreator_forms_id' => $form->getID(),
         'itemtype' => $user::getType(),
         'items_id' => $user->getID(),
         'level'    => 1,
      ]);
      $this->boolean($formValidator->isNewItem())->isFalse();

      $instance = $this->newTestedInstance();

      // Try to export an empty item
      $this->exception(function () use ($instance) {
         $instance->export();
      })->isInstanceOf(\GlpiPlugin\Formcreator\Exception\ExportFailureException::class);

      $instance->getFromDBByCrit([
         'plugin_formcreator_forms_id' => $form->getID(),
         'itemtype' => \User::class,
         'items_id' => $user->getID(),
      ]);
      $this->boolean($instance->isNewItem())->isFalse();

      // Export the item without the ID and with UUID
      $output = $instance->export(false);

      // Test the exported data
      $fieldsWithoutID = [
         'itemtype',
         'level',
      ];
      $extraFields = [
         '_item',
      ];
      $this->array($output)
         ->hasKeys($fieldsWithoutID + $extraFields + ['uuid'])
         ->hasSize(1 + count($fieldsWithoutID) + count($extraFields));
      $this->array($output)->isEqualTo([
         'itemtype' => \User::class,
         '_item' => $user->fields['name'],
         'level' => 1,
         'uuid'  => $instance->fields['uuid'],
      ]);

      // Export the item without the UUID and with ID
      $output = $instance->export(true);
      $this->array($output)
         ->hasKeys($fieldsWithoutID + $extraFields + ['id'])
         ->hasSize(1 + count($fieldsWithoutID) + count($extraFields));
      $this->array($output)->isEqualTo([
         'itemtype' => \User::class,
         '_item' => $user->fields['name'],
         'level' => 1,
         'id'  => $instance->fields['id'],
      ]);
   }

   public function testImport() {
      $linker = new \PluginFormcreatorLinker();
      $input = [
         'itemtype' => \User::class,
         '_item' => 'normal',
         'level' => 1,
         'uuid' => plugin_formcreator_getUuid(),
      ];
      $form = $this->getForm();
      $formId = $form->getID();
      $formValidatorId = \PluginFormcreatorForm_Validator::import($linker, $input, $formId);
      $validId = \PluginFormcreatorForm_Validator::isNewId($formValidatorId);
      $this->boolean($validId)->isFalse();

   }
}
