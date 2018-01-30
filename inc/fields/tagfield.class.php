<?php
class PluginFormcreatorTagField extends PluginFormcreatorDropdownField
{
   const IS_MULTIPLE    = true;

   public function displayField($canEdit = true) {
      if ($canEdit) {
         $rand     = mt_rand();
         $required = $this->fields['required'] ? ' required' : '';

         echo '<div class="form_field">';
         $values = [];

         $obj = new PluginTagTag();
         $obj->getEmpty();

         $where = "(`type_menu` LIKE '%\"Ticket\"%' OR `type_menu` LIKE '0')";
         $where .= getEntitiesRestrictRequest('AND', getTableForItemType('PluginTagTag'), '', '', true);

         $result = $obj->find($where, "name");
         foreach ($result AS $id => $data) {
            $values[$id] = $data['name'];
         }

         Dropdown::showFromArray('formcreator_field_' . $this->fields['id'], $values, [
            'values'               => $this->getValue(),
            'comments'            => false,
            'rand'                => $rand,
            'multiple'            => true,
         ]);
         echo '</div>' . PHP_EOL;
         echo '<script type="text/javascript">
                  jQuery(document).ready(function($) {
                     jQuery("#dropdown_formcreator_field_' . $this->fields['id'] . $rand . '").on("select2-selecting", function(e) {
                        formcreatorChangeValueOf (' . $this->fields['id']. ', e.val);
                     });
                  });
               </script>';
      } else {
         $answer = $this->getAnswer();
         echo '<div class="form_field">';
         echo empty($answer) ? '' : implode('<br />', json_decode($answer));
         echo '</div>';
      }
   }

   public function getAnswer() {
      $return = [];
      $values = $this->getValue();

      if (!empty($values)) {
         foreach ($values as $value) {
            $return[] = Dropdown::getDropdownName(getTableForItemType('PluginTagTag'), $value);
         }
      }

      return json_encode($return);
   }

   public function prepareQuestionInputForSave($input) {
      return $input;
   }

   public static function getName() {
      return _n('Tag', 'Tags', 2, 'tag');
   }

   public static function getPrefs() {
      return array(
         'required'       => 0,
         'default_values' => 0,
         'values'         => 0,
         'range'          => 0,
         'show_empty'     => 0,
         'regex'          => 0,
         'show_type'      => 1,
         'dropdown_value' => 0,
         'glpi_objects'   => 0,
         'ldap_values'    => 0,
      );
   }

   public static function getJSFields() {
      $prefs = self::getPrefs();
      return "tab_fields_fields['tag'] = 'showFields(" . implode(', ', $prefs) . ");';";
   }
}
