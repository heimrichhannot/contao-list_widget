<?php

/**
 * Backend form fields
 */
$GLOBALS['BE_FFL']['listWidget'] = 'HeimrichHannot\ListWidget\ListWidget';

/**
 * Assets
 */
if (TL_MODE == 'BE')
{
    $GLOBALS['TL_JAVASCRIPT']['datatables-i18n']       = 'composer/vendor/heimrichhannot/datatables-additional/datatables-i18n/datatables-i18n.min.js';
    $GLOBALS['TL_JAVASCRIPT']['datatables-core']       = 'composer/vendor/datatables/datatables/media/js/jquery.dataTables.min.js';
    $GLOBALS['TL_JAVASCRIPT']['datatables-rowReorder'] =
        'composer/vendor/heimrichhannot/datatables-additional/datatables-RowReorder/js/dataTables.rowReorder.min.js';

    $GLOBALS['TL_CSS']['datatables-core']       = 'composer/vendor/heimrichhannot/datatables-additional/datatables.net-dt/css/jquery.dataTables.min.css';
    $GLOBALS['TL_CSS']['datatables-rowReorder'] =
        'composer/vendor/heimrichhannot/datatables-additional/datatables-RowReorder/css/rowReorder.dataTables.min.css';

    $GLOBALS['TL_JAVASCRIPT']['jquery.list_widget.js'] = 'system/modules/list_widget/assets/js/jquery.list_widget.js';
//    $GLOBALS['TL_CSS']['jquery.list.js']    = 'system/modules/fieldpalette/assets/css/fieldpalette-wizard-be.css';
}
