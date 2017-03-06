<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2017 Leo Feyer
 *
 * @license LGPL-3.0+
 */


/**
 * Register the namespaces
 */
ClassLoader::addNamespaces(array
(
	'HeimrichHannot',
));


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	// Widgets
	'HeimrichHannot\ListWidget\ListWidget' => 'system/modules/list_widget/widgets/ListWidget.php',
));


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	'list_widget' => 'system/modules/list_widget/templates',
));
