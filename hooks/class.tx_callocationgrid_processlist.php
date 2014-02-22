<?php
/*
 * @author Thomas Kowtsch
 */
class tx_callocationgrid_processlist {
    private $callist = array();
    private $calday = -1;
    private $calweek = -1;
    private $calmonth = -1;
    private $calyear = -1;
    
    private $gOut = '';    
    private $locations = array();
    private $locations_txt = array();

    private $dayIterations;
	
    public function __construct( ) {
    }

    /*
     * function to render all events within a set of locations
     */
    private function renderEvents($event, array $location) {
        $tmp_loc = array();
        $rendering = '';
        
        // get location of event
        $tmp_loc[0]=$event->getLocationId();

        // if location is not defined need to use the event's parent record location
        if (($tmp_loc[0]==0) && (is_object($event->parentEvent->locationObject))) {
                $tmp_loc[0]=$event->parentEvent->getLocationId();
        }

        if (count(array_intersect($tmp_loc,$location))>0){
                $rendering=$event->renderEventForList();
        }    
        return $rendering;
    }
    
    protected function renderCalList($parent) {
        $sims = array();
        $rems = array();

        $ret = '';

        $retD=date($parent->conf['view.']['locationgrid.']['dateFormat'],strtotime($this->calyear.'-'.$this->calmonth.'-'.$this->calday));
        $ret.=$parent->cObj->stdWrap($retD,$parent->conf['view.']['locationgrid.']['date_stdWrap.']);
        $event_count = 0;

        // iterate over all defined locations
        foreach ($this->locations as $loc) {
            $ret2='';
            foreach($this->callist as $anyEvent){
                $renderedEvent = $this->renderEvents($anyEvent, $loc);
                if ($renderedEvent != '') {
                    $ret2.=$renderedEvent; 
                    $event_count++;
                }
            }
            $ret.=$parent->cObj->stdWrap($ret2,$parent->conf['view.']['locationgrid.']['item_stdWrap.']);
        }
        // If no events were rendered: reset to blank
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
                if (intval($this->dayIterations)<=(count($sims[$aKey])-1)) {
                        $corrected_sims[$aKey] = $sims[$aKey][intval($this->dayIterations)];
                }
            } else {
                $corrected_sims[$aKey] = $sims[$aKey];
            }
        }
        $pagepart = tx_cal_functions::substituteMarkerArrayNotCached($content, $corrected_sims, $rems, array ());

        $this->dayIterations++;
        return $pagepart;
    }

    private function renderHeading($parent, $hookParams) {
        $this->dayIterations = 0;
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

        $this->locations = $ts_loc;
        $this->locations_txt = $ts_loc_txt;
        while (count($this->locations)>count($this->locations_txt)) {
            $this->locations_txt[] = '';
        }

        $this->calday = $hookParams['cal_day'];
        $this->calweek = $hookParams['cal_week'];
        $this->calmonth = $hookParams['cal_month'];
        $this->calyear = $hookParams['cal_year'];		
        $head = '';
        $head.= $parent->cObj->stdWrap('',$parent->conf['view.']['locationgrid.']['headerItem_stdWrap.']);
        foreach ($this->locations_txt as $loc) {
            $loc_head = $loc;
            $head.= $parent->cObj->stdWrap($loc_head,$parent->conf['view.']['locationgrid.']['headerItem_stdWrap.']);
        }
        $this->gOut = $parent->cObj->stdWrap($head,$parent->conf['view.']['locationgrid.']['row_stdWrap.']);
    }
    
    public function prepareOuterEventWrapper($parent, &$middle, $event, $calTimeObject, $firstTime, $hookParams, &$allowFurtherGrouping) {
        if(!$parent->conf['view.']['locationgrid.']['enable']){
            return;
        }

        // if hook is invoked for the first time: render some type of heading
        if ($firstTime){
            $this->renderHeading($parent, $hookParams);
        } else {
            // if current event date is different from the previous one: render the list & reset the array
            if (($hookParams['cal_day']!=$this->calday)||($hookParams['cal_week']!=$this->calweek)) {
                // render the events
                $this->gOut.= $this->renderCalList($parent);
                $this->callist = array();
                $this->calday = $hookParams['cal_day'];
                $this->calweek = $hookParams['cal_week'];
                $this->calmonth = $hookParams['cal_month'];
                $this->calyear = $hookParams['cal_year'];
            } 
        }

        // add only events with configured locations to reduce further rendering time and memory consumption
        foreach ($this->locations as $locs) {

                if (in_array((integer)$event->getLocationId(), $locs)){
                        $this->callist[] = $event;
                } else {
                        if (is_object($event->parentEvent->locationObject)) {
                                if (in_array((integer)$event->parentEvent->getLocationId(), $locs)){
                                        $this->callist[] = $event;
                                }
                        }
                }
        }	 
        $allowFurtherGrouping = false;
    }

    public function applyOuterEventWrapper($parent, &$middle, $event, &$allowFurtherGrouping) {
        $allowFurtherGrouping = false;
        if (count($this->callist)>0) {
            $this->gOut.=$this->renderCalList($parent);
        }

        $middle.=$parent->cObj->stdWrap($this->gOut,$parent->conf['view.']['locationgrid.']);
    }
} 

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/callocationgrid/hooks/class.tx_callocationgrid_processlist.php']) {
        include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/callocationgrid/hooks/class.tx_callocationgrid_processlist.php']);
}

?>
