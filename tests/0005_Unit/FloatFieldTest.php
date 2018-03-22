<?php
class FloatFieldTest extends SuperAdminTestCase {

   public function provider() {
      $dataset = array(
            array(
               'fields'          => array(
                  'fieldtype'       => 'float',
                  'name'            => 'question',
                  'required'        => '0',
                  'default_values'  => '',
                  'order'           => '1',
                  'show_rule'       => 'always',
                  'show_empty'      => '0',
                  'values'          => '',
                  '_parameters'     => [
                     'float' => [
                        'range' => [
                           'range_min'       => '',
                           'range_max'       => '',
                        ],
                        'regex' => '',
                     ]
                  ]
               ),
               'data'            => null,
               'expectedValue'   => '',
               'expectedIsValid' => true
            ),
            array(
               'fields'          => array(
                  'fieldtype'       => 'float',
                  'name'            => 'question',
                  'required'        => '0',
                  'default_values'  => '2',
                  'order'           => '1',
                  'show_rule'       => 'always',
                  'show_empty'      => '0',
                  'values'          => '',
                  '_parameters'     => [
                     'float' => [
                        'range' => [
                           'range_min'       => '',
                           'range_max'       => '',
                        ],
                        'regex' => '',
                     ]
                  ]
               ),
               'data'            => null,
               'expectedValue'   => '2',
               'expectedIsValid' => true
            ),
            array(
               'fields'          => array(
                  'fieldtype'       => 'float',
                  'name'            => 'question',
                  'required'        => '0',
                  'default_values'  => "2",
                  'order'           => '1',
                  'show_rule'       => 'always',
                  'show_empty'      => '0',
                  '_parameters'     => [
                     'float' => [
                        'range' => [
                           'range_min'       => 3,
                           'range_max'       => 4,
                        ],
                        'regex' => '',
                     ]
                  ]
               ),
               'data'            => null,
               'expectedValue'   => '2',
               'expectedIsValid' => false
            ),
            array(
               'fields'          => array(
                  'fieldtype'       => 'float',
                  'name'            => 'question',
                  'required'        => '0',
                  'default_values'  => "5",
                  'order'           => '1',
                  'show_rule'       => 'always',
                  'show_empty'      => '0',
                  'values'          => '',
                  '_parameters'     => [
                     'float' => [
                        'range' => [
                           'range_min'       => 3,
                           'range_max'       => 4,
                        ],
                        'regex' => '',
                     ]
                  ]
               ),
               'data'            => null,
               'expectedValue'   => '5',
               'expectedIsValid' => false
            ),
            array(
               'fields'          => array(
                  'fieldtype'       => 'float',
                  'name'            => 'question',
                  'required'        => '0',
                  'default_values'  => "3.141592",
                  'order'           => '1',
                  'show_rule'       => 'always',
                  'show_empty'      => '0',
                  'values'          => '',
                  '_parameters'     => [
                     'float' => [
                        'range' => [
                           'range_min'       => 3,
                           'range_max'       => 4,
                        ],
                        'regex' => '',
                     ]
                  ]
               ),
               'data'            => null,
               'expectedValue'   => '3.141592',
               'expectedIsValid' => true
            ),
      );

      return $dataset;
   }

   /**
    * @dataProvider provider
    */
   public function testFieldValue($fields, $data, $expectedValue, $expectedValidity) {
      $fieldInstance = new PluginFormcreatorFloatField($fields, $data);

      $value = $fieldInstance->getValue();
      $this->assertEquals($expectedValue, $value);
   }

   /**
    * @dataProvider provider
    */
   public function testFieldIsValid($fields, $data, $expectedValue, $expectedValidity) {
      $section = $this->getSection();
      $fields[$section::getForeignKeyField()] = $section->getID();

      $question = new PluginFormcreatorQuestion();
      $question->add($fields);
      $this->assertFalse($question->isNewItem(), json_encode($_SESSION['MESSAGE_AFTER_REDIRECT'], JSON_PRETTY_PRINT));
      $question->updateParameters($fields);

      $fieldInstance = new PluginFormcreatorFloatField($question->fields, $data);
      $isValid = $fieldInstance->isValid($fields['default_values']);
      $this->assertEquals($expectedValidity, $isValid);
   }

   private function getSection() {
      $form = new PluginFormcreatorForm();
      $form->add([
         'name' => 'form'
      ]);
      $section = new PluginFormcreatorSection();
      $section->add([
         $form::getForeignKeyField() => $form->getID(),
         'name' => 'section',
      ]);
      return $section;
   }
}
