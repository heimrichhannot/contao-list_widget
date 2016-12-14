<?php

namespace HeimrichHannot\ListWidget;


class ListWidget extends \Widget
{

    protected $blnForAttribute   = true;
    protected $strTemplate       = 'be_widget';
    protected $strListTemplate = 'list_widget';
    protected $arrDca;
    protected $arrWidgetErrors   = array();

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

        // header fields
        if (is_array($this->arrDca['headerFields_callback']))
        {
            $arrCallback        = $this->arrDca['headerFields_callback'];
            $objTemplate->headerFields = \System::importStatic($arrCallback[0])->{$arrCallback[1]}($this->objDca, $this->arrDca, $this);
        }
        elseif (is_callable($this->arrDca['headerFields_callback']))
        {
            $objTemplate->headerFields = $this->arrDca['headerFields_callback']($this->objDca, $this->arrDca, $this);
        }

        return $objTemplate->parse();
    }

}