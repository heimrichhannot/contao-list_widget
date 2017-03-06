# Contao List Widget

This simple module offers functionality for displaying a list in the Contao backend (either as a dca field or in a backend module).

For visualization the javascript library [DataTables](https://github.com/DataTables/DataTables) is used.

![alt text](./docs/screenshot.png "Demo in the backend")

## Features

- inputType "listWidget" for usage as a dca field
- convenient functions for integrating a list in your backend module
- the list can display either model data or even arbitrary arrays
- support for datatables javascript library
  - filter the table
  - search the table
  - sort the table
  - everything of the last 3 points can be done dynamically using ajax -> support for very large Contao model driven entities

### Technical instructions

#### Usage as a widget in a dca field

Use the inputType "listWidget" for your field.

```
'someField' => [
    'label'     => &$GLOBALS['TL_LANG']['tl_my_dca']['someField'],
    'exclude'   => true,
    'inputType' => 'listWidget',
    'eval'      => [
        'listWidget' => [
            'headerFields_callback' => ['SomeNamespace\MyClass', 'getHeaderFields'], // array keys need to be the keys in items_callback (see list_widget.html5 for explanation)
            'items_callback'        => ['SomeNamespace\MyClass', 'getItems'],
            'table'                 => 'tl_dca',
            'useDbAsHeader'         => true, // "table" option needs to be defined (see above)
            'template'              => 'list_widget_csv'
            'ajax'                  => true, // caution: only contao models can be used for ajax reloading at the moment
            'ajaxConfig'            => [
                // these two methods don't need to be defined since there are basic implementations already in ListWidget class
                'load_callback'          => ['SomeNamespace\MyClass', 'loadItems'],
                'prepare_items_callback' => ['SomeNamespace\MyClass', 'prepareItems']
            ]
        ]
    ]
]
```

#### Usage in a module

Add the following code e.g. in the generate() method of your BackendModule:

```
static::$arrListOptions = [
    'identifier' => 'module' . $this->id, // needed for distinguishing requests from multiple list widget implementations
    'table' => 'tl_dca', // needed for ajax model handling
    'load_callback' => ['SomeNamespace\MyClass', 'loadItems'],
    'prepare_items_callback' => function($objItems) { // prepares the data for the javascript part
        return $this->parseItems($objItems);
    },
    'columns_callback' => ['SomeNamespace\MyClass', 'getColumns'], // get the columns by callback/function...
    'columns' => static::getColumns(), // ... or set them directly
    'language' => static::getLanguage() // see ListWidget::getLanguage for the syntax
];
ListWidget::initAjaxLoading(static::$arrListOptions);
```

Call this in your module's compile method:

```
ListWidget::addAjaxLoadingToTemplate($this->Template, static::$arrListOptions);
```

#### Example load_callback

Here you can see an example for overriding the core behavior of loadItems():

```
public static function loadItemsNew($arrListOptions, $arrOptions = [], $objDc = null, $arrDca = [], $objWidget = null)
{
    // set an initial filter using the contao options array
    $arrOptions = [
        'table'   => $arrListOptions['table'],
        'columns' => $arrListOptions['columns'],
        // filtering
        'column'  => 'pid',
        'value'   => $objDc->id
    ];

    // the rest of the function should also be called
    return ListWidget::loadItems($arrListOptions, $arrOptions, $objDc, $arrDca, $objWidget);
}
```

### DCA-Config

Name | Possible value | Description
---- | -------------- | -----------
headerFields_callback | callback array, function closure | The callback/function must return the headerFields to be displayed in the list. Array keys need to be the keys in items_callback (see list_widget.html5 for explanation).
items_callback | callback array, function closure | The callback/function must return the items to be displayed in the list
table | string (e.g. "tl_dca") | This value is needed for useDbAsHeader and ajax
useDbAsHeader | boolean | Set to true if the header should contain all fields of a certain database entity ("table" is used)
template | string | Specify a custom template
ajax | boolean | Set to true if ajax reloading should take place (no need for items_callback in this case)
ajaxConfig-> load_callback | callback array, function closure | Override this method if custom model options or methods are needed (see ListWidget::loadItems() for details)
ajaxConfig-> prepare_items_callback | callback array, function closure | Override this method if custom data preparation is needed (see ListWidget::prepareItems() for details)

### Callbacks

Name | Arguments | Expected return value | Description
---- | --------- | --------------------- | -----------
headerFields_callback | $objDc, $arrDca, $objWidget | array containing field and label pairs (```['field1' => 'fieldLabel1', 'field2' => 'fieldLabel2', ...]```) | This callback must return the headerFields to be displayed in the list. Array keys need to be the keys in items_callback (see list_widget.html5 for explanation).
items_callback | $objDc, $arrDca, $objWidget | array of entity arrays (```[['field1' => 'value1', 'field2' => 'value2'], ...]```) | This callback must return the items to be displayed in the list
load_callback | $arrOptions, [], $objDc, $arrDca, $objWidget | data array (see ListWidget::loadItems for more details) | Override this method if custom model options or methods are needed
prepare_items_callback | $objItems, $arrListOptions, $arrOptions, $objDc, $arrDca, $objWidget | data array (see ListWidget::prepareItems for more details) | Override this method if custom data preparation is needed