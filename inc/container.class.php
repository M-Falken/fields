<?php

class PluginFieldsContainer extends CommonDBTM {
   
   static function install(Migration $migration) {
      global $DB;

      $obj = new self();
      $table = $obj->getTable();

      if (!TableExists($table)) {
         $migration->displayMessage("Installing $table");

         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id`           INT(11)        NOT NULL auto_increment,
                  `name`         VARCHAR(255)   DEFAULT NULL,
                  `label`         VARCHAR(255)   DEFAULT NULL,
                  `itemtype`     VARCHAR(255)   DEFAULT NULL,
                  `type`         VARCHAR(255)   DEFAULT NULL,
                  `entities_id`  INT(11)        NOT NULL DEFAULT '0',
                  `is_recursive` TINYINT(1)     NOT NULL DEFAULT '0',
                  PRIMARY KEY    (`id`),
                  KEY            `entities_id`  (`entities_id`)
               ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"; 
            $DB->query($query) or die ($DB->error());
      }

      return true;
   }

   
   static function uninstall() {
      global $DB;

      $obj = new self();
      $DB->query("DROP TABLE IF EXISTS `".$obj->getTable()."`");

      return true;
   }

   function defineTabs($options=array()) {
      global $LANG, $CFG_GLPI;

      $ong = array();
      $this->addStandardTab('PluginFieldsField', $ong, $options);

      return $ong;
   }

   static function getTypeName() {
      global $LANG;

      return $LANG['fields']['type'][1];
   }

   public function canCreate() {
      return true;
   }

   public function canView() {
      return true;
   }

   public function showForm($ID, $options=array()) {
      global $LANG;

      if ($ID > 0) {
         $this->check($ID,'r');
      } else {
         // Create item
         $this->check(-1,'w');
      }

      $this->showTabs($options);
      $this->showFormHeader($options);

      echo "<tr>";
      echo "<td>".$LANG['common'][16]." : </td>";
      echo "<td>";
      Html::autocompletionTextField($this, 'name', array('value' => $this->fields["name"]));
      echo "</td>";
      echo "<td>".$LANG['mailing'][139]." : </td>";
      echo "<td>";
      Html::autocompletionTextField($this, 'label', array('value' => $this->fields["label"]));
      echo "</td>";
      echo "</tr>";

      echo "<tr>";
      echo "<td>".$LANG['common'][17]." : </td>";
      echo "<td>";
      Dropdown::showFromArray('type', array(
            'tab' => $LANG['fields']['container']['type']['tab'],
            'dom' => $LANG['fields']['container']['type']['dom']
         ), 
         array('value' => $this->fields["type"]));
      echo "</td>";
      echo "<td>".$LANG['common'][90]." : </td>";
      echo "<td>";
      Dropdown::showFromArray('itemtype', self::getItemtypes(), 
         array('value' => $this->fields["itemtype"]));
      echo "</td>";
      echo "</tr>";

      $this->showFormButtons($options);
      $this->addDivForTabs();

      return true;
   }


   static function getItemtypes() {
      global $LANG;

      return array(
         'Computer'           => $LANG['Menu'][0],
         'Networkequipment'   => $LANG['Menu'][1],
         'Printer'            => $LANG['Menu'][2],
         'Monitor'            => $LANG['Menu'][3],
         'Software'           => $LANG['Menu'][4],
         'Ticket'             => $LANG['Menu'][5],
         'User'               => $LANG['Menu'][14],
         'Cartridgeitem'      => $LANG['Menu'][21],
         'Contact'            => $LANG['Menu'][22],
         'Supplier'           => $LANG['Menu'][23],
         'Contract'           => $LANG['Menu'][25],
         'Document'           => $LANG['Menu'][27],
         'State'              => $LANG['Menu'][28],
         'Consumableitem'     => $LANG['Menu'][32],
         'Phone'              => $LANG['Menu'][34],
         'Profile'            => $LANG['Menu'][35],
         'Group'              => $LANG['Menu'][36],
         'Entity'             => $LANG['Menu'][37]
      );
   }

   static function getEntries($type = 'tab', $full = false) {
      $itemtypes = array();
      $container = new self;
      $found = $container->find("`type` = '$type'", "`label`");
      foreach($found as $item) {
         if ($full) {
            $itemtypes[$item['itemtype']][$item['name']] = $item['label'];
         } else {
            $itemtypes[] = $item['itemtype'];
         }
      }
      return $itemtypes;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $LANG;
      
      $itemtypes = self::getEntries('tab', true);
      if (isset($itemtypes[$item->getType()])) {
         $tabs_entries = array();
         foreach($itemtypes[$item->getType()] as $tab_name => $tab_label) { 
            $tabs_entries[$tab_name] = $tab_label;
         }
         return $tabs_entries;
      }
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      //retrieve container for current tab
      $container = new self;
      $found_c = $container->find("`type` = 'tab' AND `name` = '$tabnum'");
      $tmp = array_shift($found_c);
      $c_id = $tmp['id'];

      return PluginFieldsField::showForTabContainer($c_id, $item->fields['id']);
   }

   function updateFieldsValues($datas) {
      global $DB;

      $c_id     = $datas['plugin_fields_containers_id'];
      $items_id = $datas['items_id'];

      unset(
         $datas['plugin_fields_containers_id'], 
         $datas['items_id'], 
         $datas['update_fields_values']
      );

      $field_obj = new PluginFieldsField;
      $field_value_obj = new PluginFieldsValue;
      foreach($datas as $field => $value) {
         //find field
         $found_f = $field_obj->find(
            "`plugin_fields_containers_id` = $c_id AND `name` = '".$field."'");
         $tmp_f = array_shift($found_f);
         $fields_id = $tmp_f['id'];

         //find existing values
         $found_v = $field_value_obj->find(
            "`plugin_fields_fields_id` = $fields_id AND `items_id` = '".$items_id."'");
         if (count($found_v) > 0) {
            //update
            $tmp_v = array_shift($found_v);
            $values_id = $tmp_v['id'];
            $field_value_obj->update(array(
               'id'    => $values_id,
               'value' => $value
            ));
         } else {
            // add
            $field_value_obj->add(array(
               'items_id'                    => $items_id,
               'value'                       => $value,
               'plugin_fields_containers_id' => $c_id,
               'plugin_fields_fields_id'     => $fields_id
            ));
         }
      }
   }

}