<?php

class tx_callocationgrid_processlist {
    var $callist = array();
    var $calday = -1;
    var $calweek = -1;
    var $calmonth = -1;
    var $calyear = -1;
    
    var $gOut = '';    
    var $locations = array();
    var $locations_txt = array();

	var $dayIterations;
	
    function tx_callocationgrid_processlist () {
    }


//    $callist = array();
//prepareOuterEventWrapper
// preInnerEventWrapper

    function renderCalList($parent) {
		global $callist;
		global $locations;
		global $locations_txt;
		global $calday;
		global $calweek;
		global $calmonth;
		global $calyear;
		global $dayIterations;
		
		$sims = array();
		$rems = array();

		$ret = '';
		$tmp_loc = array();
		
		$retD=date($parent->conf['view.']['locationgrid.']['dateFormat'],strtotime($calyear.'-'.$calmonth.'-'.$calday));
		$ret.=$parent->cObj->stdWrap($retD,$parent->conf['view.']['locationgrid.']['date_stdWrap.']);
		$event_count = 0;


		foreach ($locations as $loc) {
			$ret2='';
			foreach($callist as $anyEvent){
				$tmp_loc[0]=$anyEvent->getLocationId();

				if (($tmp_loc[0]==0) && (is_object($anyEvent->parentEvent->locationObject))) {
					$tmp_loc[0]=$anyEvent->parentEvent->getLocationId();
				}
				
				if (count(array_intersect($tmp_loc,$loc))>0){
					$ret2.=$anyEvent->renderEventForList();
					$event_count++;
				}
			}
			$ret.=$parent->cObj->stdWrap($ret2,$parent->conf['view.']['locationgrid.']['item_stdWrap.']);
		}
		if ($event_count == 0) {
			$ret = '';
		}
		$content = $parent->cObj->stdWrap($ret,$parent->conf['view.']['locationgrid.']['row_stdWrap.']);

		$hookObjectsArr = tx_cal_functions::getHookObjectsArray('tx_cal_base_model','searchForObjectMarker','model');
		// Hook: postSearchForObjectMarker
		foreach ($hookObjectsArr as $hookObj) {
			if (method_exists($hookObj, 'postSearchForObjectMarker')) {
				$wrapped = '';
				$hookObj->postSearchForObjectMarker($parent, $content, $sims, $rems, $wrapped, 'locationgrid');
			}
		}
		
		// In case that the hook delivers a nested array for a key: Reduce to a single item 
		$corrected_sims = array();
		reset($sims);
		foreach (array_keys($sims) as $aKey) {
			if (is_array($sims[$aKey])) {
				if (intval($dayIterations)<=(count($sims[$aKey])-1)) {
					$corrected_sims[$aKey] = $sims[$aKey][intval($dayIterations)];
				}
			} else {
				$corrected_sims[$aKey] = $sims[$aKey];
			}
		}
		$pagepart = tx_cal_functions::substituteMarkerArrayNotCached($content, $corrected_sims, $rems, array ());

		$dayIterations++;
		return $pagepart;
    }

    function prepareOuterEventWrapper($parent, &$middle, $event, $calTimeObject, $firstTime, $hookParams, &$allowFurtherGrouping) {

	if(!$parent->conf['view.']['locationgrid.']['enable']){
		return;
	}
        global $callist;
        global $calday;
        global $calweek;
        global $calmonth;
        global $calyear;
        global $locations;
        global $locations_txt;
        global $gOut;
		global $dayIterations;


	// if hook is invoked for the first time: render some type of heading
	if ($firstTime){
		$dayIterations = 0;
		$ts_loc = array();
		$ts_loc_raw = explode(';',$parent->conf['view.']['locationgrid.']['locationIds']);
		$ts_loc_txt = explode(';',$parent->conf['view.']['locationgrid.']['locationText']);
		for ($i=0;$i<=count($ts_loc_raw);$i++){
			$int_loc_id = array();
			$int_loc_id[0] = (integer)$ts_loc_raw[$i];
			if (strpos($ts_loc_raw[$i],'|')==0) {
				if ($int_loc_id[0]>0) {
					$ts_loc[] = $int_loc_id;
				}
			} else {
				$ts_sub_loc = explode('|',$ts_loc_raw[$i]);
				$ts_loc[] = $ts_sub_loc;
			} 
		}

		$locations = $ts_loc;
		$locations_txt = $ts_loc_txt;
		while (count($locations)>count($locations_txt)) {
			$locations_txt[] = '';
		}

		$calday = $hookParams['cal_day'];
		$calweek = $hookParams['cal_week'];
		$calmonth = $hookParams['cal_month'];
		$calyear = $hookParams['cal_year'];		
		$head = '';
		$head.= $parent->cObj->stdWrap('',$parent->conf['view.']['locationgrid.']['headerItem_stdWrap.']);
		foreach ($locations_txt as $loc) {
			$loc_head = $loc;
			$head.= $parent->cObj->stdWrap($loc_head,$parent->conf['view.']['locationgrid.']['headerItem_stdWrap.']);
		}
		$gOut = $parent->cObj->stdWrap($head,$parent->conf['view.']['locationgrid.']['row_stdWrap.']);
	} else {
		// if current event date is different from the previous one: render the list & reset the array
		if (($hookParams['cal_day']!=$calday)||($hookParams['cal_week']!=$calweek)) {
			
			// render the events
			$gOut.= $this->renderCalList($parent);
			$callist = array();
			$calday = $hookParams['cal_day'];
			$calweek = $hookParams['cal_week'];
			$calmonth = $hookParams['cal_month'];
			$calyear = $hookParams['cal_year'];
		} 
	}

	// add only events with configured locations to reduce further rendering time and memory consumption
	foreach ($locations as $locs) {

		if (in_array((integer)$event->getLocationId(), $locs)){
			$callist[] = $event;
		} else {
			if (is_object($event->parentEvent->locationObject)) {
				if (in_array((integer)$event->parentEvent->getLocationId(), $locs)){
					$callist[] = $event;
				}
			}
		}
	}	 
	$allowFurtherGrouping = false;

    }

   function applyOuterEventWrapper($parent, &$middle, $event, &$allowFurtherGrouping) {
   	global $callist;
	global $gOut;
	   	
   	$allowFurtherGrouping = false;
   	if (count($callist)>0) {
		$gOut.=$this->renderCalList($parent);
  	}

	$middle.=$parent->cObj->stdWrap($gOut,$parent->conf['view.']['locationgrid.']);
   	
   }
} 

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/callocationgrid/hooks/class.tx_callocationgrid_processlist.php']) {
        include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/callocationgrid/hooks/class.tx_callocationgrid_processlist.php']);
}

?>
