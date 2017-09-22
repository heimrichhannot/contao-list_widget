<?php

namespace HeimrichHannot\ListWidget;


use HeimrichHannot\Ajax\Response\ResponseData;
use HeimrichHannot\Ajax\Response\ResponseSuccess;
use HeimrichHannot\Haste\Dca\General;
use HeimrichHannot\Haste\Util\Arrays;
use HeimrichHannot\Haste\Util\Url;
use HeimrichHannot\Request\Request;

class ListWidget extends \Widget
{
    const LOAD_ACTION = 'list-load';

    protected $blnForAttribute = true;
    protected $strTemplate     = 'be_widget';
    protected $strListTemplate = 'list_widget';
    protected $arrDca;
    protected $arrWidgetErrors = [];

    protected static $arrSkipFields = ['id', 'tstamp', 'pid', 'dateAdded'];

    public function __construct($arrData)
    {
        \Controller::loadDataContainer($arrData['strTable']);
        $this->arrDca = $GLOBALS['TL_DCA'][$arrData['strTable']]['fields'][$arrData['strField']]['eval']['listWidget'];

        parent::__construct($arrData);
    }


    /**
     * Generate the widget and return it as string
     *
     * @return string
     */
    public function generate()
    {
        $objTemplate = new \BackendTemplate($this->arrDca['template'] ?: $this->strListTemplate);

        $arrConfig = $this->arrDca;

        // no id necessary for identifier since a backend widget can only be available once in a palette
        $arrConfig['identifier'] = $this->name;

        $arrConfig = static::prepareConfig($arrConfig, $this, $this->objDca);

        if ($arrConfig['ajax'])
        {
            static::initAjaxLoading(
                $arrConfig,
                $this,
                $this->objDca
            );

        }

        static::addToTemplate($objTemplate, $arrConfig);

        return $objTemplate->parse();
    }

    public static function prepareConfig($arrConfig = [], $objContext = null, $objDca = null)
    {
        $arrConfig = $arrConfig ?: [];

        // header
        $arrConfig['headerFields'] = General::getConfigByArrayOrCallbackOrFunction($arrConfig, 'header_fields', [$arrConfig, $objContext, $objDca]);

        if ($arrConfig['useDbAsHeader'] && $arrConfig['table'])
        {
            $strTable        = $arrConfig['table'];
            $arrFields       = \Database::getInstance()->getFieldNames($strTable, true);
            $arrHeaderFields = [];

            foreach ($arrFields as $strField)
            {
                if (in_array($strField, static::$arrSkipFields))
                {
                    continue;
                }

                $arrHeaderFields[$strField] = General::getLocalizedFieldname($strField, $strTable);
            }

            $arrConfig['headerFields'] = $arrHeaderFields;
        }

        if (!$arrConfig['ajax'])
        {
            $arrConfig['items'] = General::getConfigByArrayOrCallbackOrFunction($arrConfig, 'items', [$arrConfig, $objContext, $objDca]);
        }

        $arrConfig['language'] = General::getConfigByArrayOrCallbackOrFunction($arrConfig, 'language', [$arrConfig, $objContext, $objDca]);

        $arrConfig['columns'] = General::getConfigByArrayOrCallbackOrFunction($arrConfig, 'columns', [$arrConfig, $objContext, $objDca]);

        // prepare columns -> if not specified, get it from header fields
        if (!$arrConfig['columns'])
        {
            if (is_array($arrConfig['headerFields']))
            {
                $arrColumns = [];
                $i          = 0;

                foreach ($arrConfig['headerFields'] as $strField => $strLabel)
                {
                    $arrColumns[] = [
                        'name'       => $strLabel,
                        'db'         => $strField,
                        'dt'         => $i++,
                        'searchable' => true,
                        'className'  => is_numeric($strField) ? 'col_' . $strField : $strField,
                    ];
                }

                $arrConfig['columns'] = $arrColumns;
            }
        }

        return $arrConfig;
    }


    public static function initAjaxLoading(array $arrConfig, $objContext = null, $objDc = null)
    {
        if (!Request::getInstance()->isXmlHttpRequest())
        {
            return;
        }

        if (Request::getGet('key') == ListWidget::LOAD_ACTION && Request::getGet('scope') == $arrConfig['identifier'])
        {
            $objResponse = new ResponseSuccess();

            // start loading
            if (!isset($arrConfig['ajaxConfig']['load_items_callback']))
            {
                $arrConfig['ajaxConfig']['load_items_callback'] = function () use ($arrConfig, $objContext, $objDc)
                {
                    return self::loadItems($arrConfig, [], $objContext, $objDc);
                };
            }

            $strResult = General::getConfigByArrayOrCallbackOrFunction(
                $arrConfig['ajaxConfig'],
                'load_items',
                [$arrConfig, [], $objContext, $objDc]
            );

            $objResponse->setResult(new ResponseData('', $strResult));
            $objResponse->output();
        }
    }

    public static function addToTemplate($objTemplate, array $arrConfig)
    {
        $objTemplate->class        = $arrConfig['class'];
        $objTemplate->ajax         = $arrConfig['ajax'];
        $objTemplate->headerFields = $arrConfig['headerFields'];
        $objTemplate->columnDefs   = htmlentities(json_encode(static::getColumnDefsData($arrConfig['columns'])));
        $objTemplate->language     = htmlentities(json_encode($arrConfig['language']));

        if ($arrConfig['ajax'])
        {
            $objTemplate->processingAction = Url::addQueryString(
                'key=' . static::LOAD_ACTION . '&scope=' . $arrConfig['identifier'] . '&rt=' . \RequestToken::get()
            );
        }
        else
        {
            $objTemplate->items = $arrConfig['items'];
        }
    }

    public static function loadItems($arrConfig, $arrOptions = [], $objContext = null, $objDc = null)
    {
        $arrOptions = !empty($arrOptions)
            ? $arrOptions
            : [
                'table'   => $arrConfig['table'],
                'columns' => $arrConfig['columns'],
            ];

        $objItems                       = static::fetchItems($arrOptions);
        $arrResponse                    = [];
        $arrResponse['draw']            = Request::hasGet('draw') ? intval(Request::getGet('draw')) : 0;
        $arrResponse['recordsTotal']    = intval(static::countTotal($arrOptions));
        $arrResponse['recordsFiltered'] = intval(static::countFiltered($arrOptions));

        // prepare
        if (!isset($arrConfig['ajaxConfig']['prepare_items_callback']))
        {
            $arrConfig['ajaxConfig']['prepare_items_callback'] = function () use ($objItems, $arrConfig, $arrOptions, $objContext, $objDc)
            {
                return self::prepareItems($objItems, $arrConfig, $arrOptions, $objContext, $objDc);
            };
        }

        $arrResponse['data'] = General::getConfigByArrayOrCallbackOrFunction(
            $arrConfig['ajaxConfig'],
            'prepare_items',
            [
                $objItems,
                $arrConfig,
                $arrOptions,
                $objContext,
                $objDc,
            ]
        );

        return $arrResponse;
    }

    protected static function prepareItems($objItems, $arrConfig, $arrOptions = [], $objContext = null, $objDc = null)
    {
        if ($objItems === null)
        {
            return [];
        }

        $arrItems = [];

        while ($objItems->next())
        {
            $objItem = $objItems->current();
            $arrItem = [];

            foreach ($arrConfig['columns'] as $arrColumn)
            {
                $arrItem[] = [
                    'value' => $objItem->{$arrColumn['db']},
                ];
            }

            $arrItems[] = $arrItem;
        }

        return $arrItems;
    }

    protected static function getColumnDefsData($arrColumns)
    {
        $arrConfig = [];

        foreach ($arrColumns as $i => $arrColumn)
        {
            $arrConfig[] = array_merge(
                Arrays::filterByPrefixes($arrColumn, ['searchable', 'className', 'orderable', 'type']),
                ['targets' => $arrColumn['dt']],
                ['render' => ['_' => 'value']]
            );
        }

        return $arrConfig;
    }

    /**
     * Count the total matching items
     *
     * @param array $arrOptions
     * @return int
     */
    protected static function countTotal(array $arrOptions)
    {
        $strModel = \Model::getClassFromTable($arrOptions['table']);

        if (isset($arrOptions['column']))
        {
            return $strModel::countBy($arrOptions['column'], $arrOptions['value'], $arrOptions);
        }
        else
        {
            return $strModel::countAll();
        }
    }

    /**
     * Count the filtered items
     *
     * @param  array $arrOptions SQL options
     *
     * @return integer
     */
    protected static function countFiltered($arrOptions)
    {
        unset($arrOptions['limit']);
        unset($arrOptions['offset']);

        $strModel = \Model::getClassFromTable($arrOptions['table']);

        if (isset($arrOptions['column']))
        {
            return $strModel::countBy($arrOptions['column'], $arrOptions['value'], $arrOptions);
        }
        else
        {
            return $strModel::countAll();
        }
    }

    /**
     * Fetch the matching items
     *
     * @param  array $arrOptions SQL options
     *
     * @return array          Server-side processing response array
     */
    protected static function fetchItems(&$arrOptions = [])
    {
        $arrOptions = static::limitSQL($arrOptions);
        $arrOptions = static::filterSQL($arrOptions);
        $arrOptions = static::orderSQL($arrOptions);

        $strModel = \Model::getClassFromTable($arrOptions['table']);

        return $strModel::findAll($arrOptions);
    }

    /**
     * Paging
     *
     * Construct the LIMIT clause for server-side processing SQL query
     *
     * @param  array $arrOptions SQL options
     *
     * @return array The $arrOptions filled with limit clause
     */
    protected static function limitSQL($arrOptions)
    {
        if (Request::hasGet('start') && Request::getGet('length') != -1)
        {
            $arrOptions['limit']  = Request::getGet('length');
            $arrOptions['offset'] = Request::getGet('start');
        }

        return $arrOptions;
    }

    /**
     * Searching / Filtering
     *
     * Construct the WHERE clause for server-side processing SQL query.
     *
     * NOTE this does not match the built-in DataTables filtering which does it
     * word by word on any field. It's possible to do here performance on large
     * databases would be very poor
     *
     * @param  array $arrOptions SQL options
     *
     * @return array The $arrOptions filled with where conditions (values and columns)
     */
    protected static function filterSQL($arrOptions)
    {
        $t = $arrOptions['table'];

        $columns      = $arrOptions['columns'];
        $globalSearch = [];
        $columnSearch = [];
        $dtColumns    = self::pluck($columns, 'dt');
        $request      = Request::getInstance()->query->all();

        if (isset($request['search']) && $request['search']['value'] != '')
        {
            $str = $request['search']['value'];
            for ($i = 0, $ien = count($request['columns']); $i < $ien; $i++)
            {
                $requestColumn = $request['columns'][$i];
                $columnIdx     = array_search($requestColumn['data'], $dtColumns);
                $column        = $columns[$columnIdx];

                if (!$column['db'])
                {
                    continue;
                }

                if ($requestColumn['searchable'] == 'true')
                {
                    $globalSearch[] = "$t." . $column['db'] . " LIKE '%%" . $str . "%%'";
                }
            }
        }
        // Individual column filtering
        if (isset($request['columns']))
        {
            for ($i = 0, $ien = count($request['columns']); $i < $ien; $i++)
            {
                $requestColumn = $request['columns'][$i];
                $columnIdx     = array_search($requestColumn['data'], $dtColumns);
                $column        = $columns[$columnIdx];
                $str           = $requestColumn['search']['value'];

                if (!$column['db'])
                {
                    continue;
                }

                if ($requestColumn['searchable'] == 'true' && $str != '')
                {
                    $columnSearch[] = "$t." . $column['db'] . " LIKE '%%" . $str . "%%'";
                }
            }
        }
        // Combine the filters into a single string
        $where = '';
        if (count($globalSearch))
        {
            $where = '(' . implode(' OR ', $globalSearch) . ')';
        }

        if (count($columnSearch))
        {
            $where = $where === '' ? implode(' AND ', $columnSearch) : $where . ' AND ' . implode(' AND ', $columnSearch);
        }


        if ($where)
        {
            if (is_array($arrOptions['column']))
            {
                $arrOptions['column'] = array_merge($arrOptions['column'], [$where]);
            }
            else{
                $arrOptions['column'] = [$where]; // prevent adding table before where clause by creating array
            }
        }

        return $arrOptions;
    }

    /**
     * Ordering
     *
     * Construct the ORDER BY clause for server-side processing SQL query
     *
     * @param  array $arrOptions SQL options
     *
     * @return array The $arrOptions filled with order conditions
     */
    protected static function orderSQL($arrOptions)
    {
        $t       = $arrOptions['table'];
        $request = Request::getInstance()->query->all();
        $columns = $arrOptions['columns'];

        if (isset($request['order']) && count($request['order']))
        {
            $orderBy   = [];
            $dtColumns = static::pluck($columns, 'dt');
            for ($i = 0, $ien = count($request['order']); $i < $ien; $i++)
            {
                // Convert the column index into the column data property
                $columnIdx     = intval($request['order'][$i]['column']);
                $requestColumn = $request['columns'][$columnIdx];
                $columnIdx     = array_search($requestColumn['data'], $dtColumns);
                $column        = $columns[$columnIdx];

                if (!$column['db'])
                {
                    continue;
                }

                if ($requestColumn['orderable'] == 'true')
                {
                    $dir = $request['order'][$i]['dir'] === 'asc' ? 'ASC' : 'DESC';

                    if ($column['name'] == 'transport')
                    {
                        $orderBy[] = "GREATEST($t." . $column['db'] . ", $t.transportTime) " . $dir;
                    }
                    else
                    {
                        $orderBy[] = "$t." . $column['db'] . " " . $dir;
                    }

                }
            }

            if ($orderBy)
            {
                $arrOptions['order'] = implode(', ', $orderBy);
            }
        }

        return $arrOptions;
    }


    /**
     * Pull a particular property from each assoc. array in a numeric array,
     * returning and array of the property values from each item.
     *
     * @param  array  $a    Array to get data from
     * @param  string $prop Property to read
     *
     * @return array        Array of property values
     */
    protected static function pluck($a, $prop)
    {
        $out = [];
        for ($i = 0, $len = count($a); $i < $len; $i++)
        {
            $out[] = $a[$i][$prop];
        }

        return $out;
    }

}