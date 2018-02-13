<?php

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

class PluginFormcreatorForm_Profile extends CommonDBRelation {
   static public $itemtype_1 = 'PluginFormcreatorForm';
   static public $items_id_1 = 'plugin_formcreator_forms_id';
   static public $itemtype_2 = 'Profile';
   static public $items_id_2 = 'profiles_id';

   static function getTypeName($nb=0) {
      return _n('Target', 'Targets', $nb, 'formcreator');
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
         return self::getTypeName(2);
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      global $DB, $CFG_GLPI;

      echo "<form name='notificationtargets_form' id='notificationtargets_form'
             method='post' action=' ";
      echo Toolbox::getItemTypeFormURL(__CLASS__)."'>";
      echo "<table class    ='tab_cadre_fixe'>";

      echo '<tr><th colspan="2">'.__('Access type', 'formcreator').'</th>';
      echo '</tr>';
      echo '<td>';
      Dropdown::showFromArray(
         'access_rights',
         [
            PluginFormcreatorForm::ACCESS_PUBLIC     => __('Public access', 'formcreator'),
            PluginFormcreatorForm::ACCESS_PRIVATE    => __('Private access', 'formcreator'),
            PluginFormcreatorForm::ACCESS_RESTRICTED => __('Restricted access', 'formcreator'),
         ],
         [
            'value' => (isset($item->fields["access_rights"])) ? $item->fields["access_rights"] : 1,
         ]
      );
      echo '</td>';
      echo '<td>'.__('Link to the form', 'formcreator').': ';
      if ($item->fields['is_active']) {
         $form_url = $CFG_GLPI['url_base'].'/plugins/formcreator/front/formdisplay.php?id='.$item->getID();
         echo '<a href="'.$form_url.'">'.$form_url.'</a>&nbsp;';
         echo '<a href="mailto:?subject='.$item->getName().'&body='.$form_url.'" target="_blank">';
         echo '<img src="'.$CFG_GLPI['root_doc'].'/plugins/formcreator/pics/email.png" />';
         echo '</a>';
      } else {
         echo __('Please active the form to view the link', 'formcreator');
      }
      echo '</td>';
      echo "</tr>";

      if ($item->fields["access_rights"] == PluginFormcreatorForm::ACCESS_RESTRICTED) {
         echo '<tr><th colspan="2">'.self::getTypeName(2).'</th></tr>';

         $table         = getTableForItemType(__CLASS__);
         $table_profile = getTableForItemType('Profile');
         $query = "SELECT p.`id`, p.`name`, IF(f.`profiles_id` IS NOT NULL, 1, 0) AS `profile`
                   FROM $table_profile p
                   LEFT JOIN $table f
                     ON p.`id` = f.`profiles_id`
                     AND f.`plugin_formcreator_forms_id` = ".$item->fields['id'];
         $result = $DB->query($query);
         while (list($id, $name, $profile) = $DB->fetch_array($result)) {
            $checked = $profile ? ' checked' : '';
            echo '<tr><td colspan="2"><label>';
            echo '<input type="checkbox" name="profiles_id[]" value="'.$id.'" '.$checked.'> ';
            echo $name;
            echo '</label></td></tr>';
         }
      }

      echo '<tr>';
         echo '<td class="center" colspan="2">';
            echo '<input type="hidden" name="profiles_id[]" value="0" />';
            echo '<input type="hidden" name="form_id" value="'.$item->fields['id'].'" />';
            echo '<input type="submit" name="update" value="'.__('Save').'" class="submit" />';
         echo "</td>";
      echo "</tr>";

      echo "</table>";
      Html::closeForm();
   }

   /**
    * Import a form's profile into the db
    * @see PluginFormcreatorForm::importJson
    *
    * @param  integer $forms_id  id of the parent form
    * @param  array   $form_profile the validator data (match the validator table)
    * @return integer the validator's id
    */
   public static function import($forms_id = 0, $form_profile = []) {
      $item    = new self;
      $profile = new Profile;
      $form_profile['plugin_formcreator_forms_id'] = $forms_id;

      // retreive foreign key
      if (!isset($form['_profile'])
          || !$form['profiles_id']
                  = plugin_formcreator_getFromDBByField($profile, 'name', $form['_profile'])) {
         $form['profiles_id'] = $_SESSION['glpiactive_entity'];
      }

      if ($form_profiles_id = plugin_formcreator_getFromDBByField($item, 'uuid', $form_profile['uuid'])) {
         // add id key
         $form_profile['id'] = $form_profiles_id;

         // update section
         $item->update($form_profile);
      } else {
         //create section
         $form_profiles_id = $item->add($form_profile);
      }

      return $validators_id;
   }

   /**
    * Export in an array all the data of the current instanciated form_profile
    * @param boolean $remove_uuid remove the uuid key
    *
    * @return array the array with all data (with sub tables)
    */
   public function export($remove_uuid = false) {
      if (!$this->getID()) {
         return false;
      }

      $form_profile = $this->fields;

      // export fk
      $profile = new Profile;
      if ($profile->getFromDB($form_profile['profiles_id'])) {
         $form_profile['_profile'] = $profile->fields['name'];
      }

      // remove fk
      unset($form_profile['id'],
            $form_profile['profiles_id'],
            $form_profile['plugin_formcreator_forms_id']);

      if ($remove_uuid) {
         $form_profile['uuid'] = '';
      }

      return $form_profile;
   }
}
