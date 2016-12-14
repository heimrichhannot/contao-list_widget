# Contao List Widget

This simple module offers an input type for displaying a list of entities definable by a callback function.

For visualization the javascript library [DataTables](https://github.com/DataTables/DataTables) is used.

![alt text](./docs/screenshot.png "Demo in the backend")

## Features

### Technical instructions

Use the inputType "listWidget" for your field.

```
'someField' => array(
    'label'     => &$GLOBALS['TL_LANG']['tl_my_dca']['someField'],
    'exclude'   => true,
    'inputType' => 'listWidget',
    'eval'      => array(
        'listWidget' => array(
            'items_callback' => array('SomeNamespace\MyClass', 'getItems'),
            'headerFields_callback' => array('SomeNamespace\MyClass', 'getHeaderFields')
        )
    )
)
```

### Callbacks

Name | Arguments | Expected return value | Description
---- | --------- | --------------------- | -----------
items_callback | $objDc, $arrDca, $objWidget | array of entity arrays (```[['field1' => 'value1', 'field2' => 'value2'], ...]```) | This callback must return the items to be displayed in the list
headerFields_callback | $objDc, $arrDca, $objWidget | array containing field and label pairs (```['field1' => 'fieldLabel1', 'field2' => 'fieldLabel2', ...]```) | This callback must return the headerFields to be displayed in the list
