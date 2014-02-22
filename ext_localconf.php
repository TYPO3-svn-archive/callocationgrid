<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

/* Default confirm location View */
//t3lib_extMgm::addService($_EXTKEY,  'cal_view' /* sv type */,  'tx_default_list' /* sv key */,
//	array(
//		'title' => 'location grid view', 'description' => '', 'subtype' => 'list',
//		'available' => TRUE, 'priority' => 55, 'quality' => 50,
//		'os' => '', 'exec' => '',
//		'classFile' => t3lib_extMgm::extPath($_EXTKEY).'view/class.tx_callocationgrid_view.php',
//		'className' => 'tx_callocationgrid_view',
//	)
//);

$GLOBALS ['TYPO3_CONF_VARS']['FE']['EXTCONF']['ext/cal/view/class.tx_cal_listview.php']['drawList'][] = 'EXT:callocationgrid/hooks/class.tx_callocationgrid_processlist.php:tx_callocationgrid_processlist';
?>

