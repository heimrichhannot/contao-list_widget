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
        $objTemplate        = new \BackendTemplate($this->arrDca['template'] ?: $this->strListTemplate);
        $objTemplate->class = $this->arrDca['class'];
        $objTemplate->ajax  = $this->arrDca['ajax'];

        // header fields
        $arrHeaderFields = [];

        if (is_array($this->arrDca['header_fields_callback']))
        {
            $arrCallback     = $this->arrDca['header_fields_callback'];
            $arrHeaderFields = \System::importStatic($arrCallback[0])->{$arrCallback[1]}($this->objDca, $this->arrDca, $this);
        }
        elseif (is_callable($this->arrDca['header_fields_callback']))
        {
            $arrHeaderFields = $this->arrDca['header_fields_callback']($this->objDca, $this->arrDca, $this);
        }

        if ($this->arrDca['useDbAsHeader'])
        {
            $strTable  = $this->arrDca['table'];
            $arrFields = \Database::getInstance()->getFieldNames($strTable, true);

            foreach ($arrFields as $strField)
            {
                if (in_array($strField, static::$arrSkipFields))
                {
                    continue;
                }

                $arrHeaderFields[$strField] = General::getLocalizedFieldname($strField, $strTable);
            }
        }

        $objTemplate->headerFields = $arrHeaderFields;

        $arrOptions = [
            // no id necessary for identifier since a backend widget can only be available once in a palette
            'identifier' => $this->name,
            'table'      => $this->arrDca['table'],
            'language'   => $this->arrDca['language']
        ];

        $objTemplate->language = htmlentities(json_encode($arrOptions['language']));

        if (!$this->arrDca['ajax'])
        {
            // items
            if (is_array($this->arrDca['items_callback']))
            {
                $arrCallback        = $this->arrDca['items_callback'];
                $objTemplate->items = \System::importStatic($arrCallback[0])->{$arrCallback[1]}($this->objDca, $this->arrDca, $this);
            }
            elseif (is_callable($this->arrDca['items_callback']))
            {
                $objTemplate->items = $this->arrDca['items_callback']($this->objDca, $this->arrDca, $this);
            }
        }
        else
        {
            if (isset($this->arrDca['ajaxConfig']['load_callback']))
            {
                $arrOptions['load_callback'] = $this->arrDca['ajaxConfig']['load_callback'];
            }

            if (isset($this->arrDca['ajaxConfig']['prepare_items_callback']))
            {
                $arrOptions['prepare_items_callback'] = $this->arrDca['ajaxConfig']['prepare_items_callback'];
            }

            // prepare columns
            $arrColumns = [];
            $i = 0;

            if (array_is_assoc($arrHeaderFields))
            {
                foreach ($arrHeaderFields as $strField => $strLabel)
                {
                    $arrColumns[] = [
                        'name'       => $strLabel,
                        'db'         => $strField,
                        'dt'         => $i++,
                        'searchable' => true,
                        'className'  => is_numeric($strField) ? 'col_' . $strField : $strField
                    ];
                }
            }
            else
            {
                foreach ($arrHeaderFields as $strField)
                {
                    $arrColumns[] = [
                        'name'       => is_numeric($strField) ? $strField : General::getLocalizedFieldname($strField, $this->arrDca['table']),
                        'db'         => $strField,
                        'dt'         => $i++,
                        'searchable' => true,
                        'className'  => is_numeric($strField) ? 'col_' . $strField : $strField
                    ];
                }
            }

            $arrOptions['columns'] = $arrColumns;

            static::initAjaxLoading(
                $arrOptions,
                $this->objDca,
                $this->arrDca,
                $this
            );

            static::addAjaxLoadingToTemplate($objTemplate, $arrOptions);
        }

        return $objTemplate->parse();
    }


    public static function initAjaxLoading(array $arrOptions, $objDc = null, $arrDca = [], $objWidget = null)
    {
        if (Request::getInstance()->isXmlHttpRequest())
        {
            if (Request::getGet('key') == ListWidget::LOAD_ACTION && Request::getGet('scope') == $arrOptions['identifier'])
            {
                $objResponse = new ResponseSuccess();
                $strResult   = '';

                // start loading
                if (!isset($arrOptions['load_callback']))
                {
                    $arrOptions['load_callback'] = function () use ($arrOptions, $objDc, $arrDca, $objWidget)
                    {
                        return self::loadItems($arrOptions, [], $objDc, $arrDca, $objWidget);
                    };
                }

                if (is_array($arrOptions['load_callback']))
                {
                    $strResult = \System::importStatic($arrOptions['load_callback'][0])->{$arrOptions['load_callback'][1]}(
                        $arrOptions,
                        [],
                        $objDc,
                        $arrDca,
                        $objWidget
                    );
                }
                elseif (is_callable($arrOptions['load_callback']))
                {
                    $strResult = $arrOptions['load_callback']($arrOptions, [], $objDc, $arrDca, $objWidget);
                }

                $objResponse->setResult(new ResponseData('', $strResult));
                $objResponse->output();
            }
        }
    }

    public static function addAjaxLoadingToTemplate($objTemplate, array $arrOptions)
    {
        $strProcessingAction =
            Url::addQueryString('key=' . static::LOAD_ACTION . '&scope=' . $arrOptions['identifier'] . '&rt=' . \RequestToken::get());

        $objTemplate->processingAction = $strProcessingAction;

        // prepare columns
        if (!isset($arrOptions['columns']))
        {
            if (isset($arrOptions['columns_callback']))
            {
                if (is_array($arrOptions['columns_callback']))
                {
                    $arrOptions['columns'] = \System::importStatic($arrOptions['columns_callback'][0])->{$arrOptions['columns_callback'][1]}();
                }
                elseif (is_callable($arrOptions['columns_callback']))
                {
                    $arrOptions['columns'] = $arrOptions['columns_callback']();
                }
            }
        }

        $objTemplate->columnDefs = htmlentities(json_encode(static::getColumnDefsData($arrOptions['columns'])));
        $objTemplate->language   = htmlentities(json_encode($arrOptions['language']));
    }

    public static function loadItems($arrListOptions, $arrOptions = [], $objDc = null, $arrDca = [], $objWidget = null)
    {
        $arrOptions = !empty($arrOptions)
            ? $arrOptions
            : [
                'table'   => $arrListOptions['table'],
                'columns' => $arrListOptions['columns']
            ];

        $objItems                       = static::fetchItems($arrOptions);
        $arrResponse                    = [];
        $arrResponse['draw']            = Request::hasGet('draw') ? intval(Request::getGet('draw')) : 0;
        $arrResponse['recordsTotal']    = intval(static::countTotal($arrOptions));
        $arrResponse['recordsFiltered'] = intval(static::countFiltered($arrOptions));

        // prepare
        if (!isset($arrListOptions['prepare_items_callback']))
        {
            $arrListOptions['prepare_items_callback'] = function () use ($objItems, $arrListOptions, $arrOptions, $objDc, $arrDca, $objWidget)
            {
                return self::prepareItems($objItems, $arrListOptions, $arrOptions, $objDc, $arrDca, $objWidget);
            };
        }

        if (is_array($arrListOptions['prepare_items_callback']))
        {
            $arrResponse['data'] = \System::importStatic($arrListOptions['prepare_items_callback'][0])->{$arrOptions['prepare_items_callback'][1]}(
                $objItems,
                $arrListOptions,
                $arrOptions,
                $objDc,
                $arrDca,
                $objWidget
            );
        }
        elseif (is_callable($arrListOptions['prepare_items_callback']))
        {
            $arrResponse['data'] = $arrListOptions['prepare_items_callback']($objItems, $arrListOptions, $arrOptions, $objDc, $arrDca, $objWidget);
        }

        return $arrResponse;
    }

    protected function prepareItems($objItems, $arrListOptions, $arrOptions = [], $objDc = null, $arrDca = [], $objWidget = null)
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

            foreach ($arrListOptions['columns'] as $arrColumn)
            {
                $arrItem[] = [
                    'value' => $objItem->{$arrColumn['db']}
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
     * @return integer
     */
    protected function countTotal(array $arrOptions)
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
    protected function countFiltered($arrOptions)
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
    static function limitSQL($arrOptions)
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
    protected function filterSQL($arrOptions)
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


        if (!\BackendUser::getInstance()->isAdmin)
        {
            $arrPids = \BackendUser::getInstance()->mdChannels;

            // Set root IDs
            if (!is_array(\BackendUser::getInstance()->mdChannels) || empty(\BackendUser::getInstance()->mdChannels))
            {
                $arrPids = [0];
            }

            $where .= " $t.pid IN(" . implode(',', array_map('intval', $arrPids)) . ")";
        }


        if ($where)
        {
            $arrOptions['column'] = [$where]; // prevent adding table before where clause by creating array
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
    protected function orderSQL($arrOptions)
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