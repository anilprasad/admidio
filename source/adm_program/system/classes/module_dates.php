<?php
/******************************************************************************
 * Show a list of all events
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *****************************************************************************/
 
class dates
{
    private $mode;
    private $catId;
    private $dateId;
    private $dateFrom;
    private $dateTo;
    private $order;
    
    public function __construct()
    {
        $this->setMode();
        $this->catId = '';
    }
    
    //returns possible modes for dates
    public function getModes()
    {
        return array('actual', 'old', 'all', 'period', 'day');
    }
    
    //returns current mode
    public function getMode()
    {
        return $this->mode;
    }
    
    //sets current mode
    public function setMode($mode='actual', $var1='', $var2='')
    {
        //check if mode is valid
        if(in_array($mode, $this->getModes()))
        {
            //check dates for validty if necessary
            if(($mode == 'period' || $mode == 'day') && (!isset($var1) || $this->formatDate($var1)==FALSE))
            {
                return FALSE;
            }     
            if($mode == 'period' && (!isset($var1) || $this->formatDate($var2)==FALSE))
            {
                return FALSE;
            }  
            
            $this->mode = $mode;
            
            //set $dateFrom and $dateTo regarding to $mode
            switch($this->mode)
            {
                case 'actual':
                    $this->setDateFrom();
                    $this->setDateTo();
                    $this->setDateId();
                    $this->setOrder();
                    break;
                case 'old':
                    $this->setDateFrom('1970-01-01');
                    $this->setDateTo(DATE_NOW);
                    $this->setDateId();
                    $this->setOrder('DESC');
                    break;
                case 'all':
                    $this->setDateFrom('1970-01-01');
                    $this->setDateTo();
                    $this->setDateId();
                    $this->setOrder();
                    break;
                case 'period':
                    $this->setDateFrom($var1);
                    $this->setDateTo($var2);
                    $this->setDateId();
                    $this->setOrder();
                    break;
                case 'day':
                    $this->setDateFrom($var1);
                    $this->setDateTo($var1);
                    $this->setDateId();
                    $this->setOrder();
                    break;             
            }            
            return TRUE;  
        }
        else
        {
            return FALSE;    
        }
    }
        
    //sets $dateFrom
    private function setDateFrom($date=DATE_NOW)
    {
        $checkedDate = $this->formatDate($date);
        if($checkedDate != FALSE)
        {
            $this->dateFrom = $checkedDate;
            return TRUE;    
        }
        else
        {
            return FALSE;    
        }
        
    }
    
    //returns date From
    public function getDateFrom()
    {
        return $this->dateFrom;
    }
    
    //sets $dateTo
    private function setDateTo($date='9999-12-31')
    {
        $checkedDate = $this->formatDate($date);
        if($checkedDate != FALSE)
        {
            $this->dateTo = $checkedDate;
            return TRUE;    
        }
        else
        {
            return FALSE;    
        }
    }
    
    //returns date To
    public function getDateTo()
    {
        return $this->dateTo;
    }
    
    //checks date
    private function formatDate($date)
    {
        global $gPreferences;
         
        $objDate = new DateTimeExtended($date, 'Y-m-d', 'date');
        if($objDate->valid())
        {
            return $date;
        }
        else
        {
            // check if date has system format
            $objDate = new DateTimeExtended($date, $gPreferences['system_date'], 'date');
            $objDate->setDateTime($date, $gPreferences['system_date']);
            if($objDate->valid())
            {
                return  substr($objDate->getDateTimeEnglish(), 0, 10);
            }
            else
            {
                FALSE;
            }
        }
    }
        
    //sets current catId
    public function setCatId($id=0)
    {
        if(is_numeric($id))
        {
            $this->catId=$id;
            return TRUE;    
        }
        else
        {
            return FALSE;
        }
    }
        
    //sets current catId
    public function setDateId($id=0)
    {        
        if(is_numeric($id))
        {
            $this->dateId=$id;
            return TRUE;    
        }
        else
        {
            return FALSE;
        }
    }
        
    //sets current Order
    public function setOrder($order='ASC')
    {        
        if(in_array($order, array('ASC','DESC')))
        {
            $this->order=$order;
            return TRUE;    
        }
        else
        {
            return FALSE;
        }
    }
    
    //returns SQL conditions
    private function sqlConditionsGet()
    {
        global $gValidLogin;
        global $gCurrentUser;
        
        $sqlConditions ='';
        
        // if user isn't logged in, then don't show hidden categories
        if ($gValidLogin == false)
        {
            $sqlConditions .= ' AND cat_hidden = 0 ';
        }

        // In case ID was permitted and user has rights
        if($this->dateId > 0)
        {
            $sqlConditions .= ' AND dat_id = '.$this->dateId;
        }
        //...otherwise get all additional events for a group
        else
        {
            // add 1 second to end date because full time events to until next day
            $sqlConditions .= ' AND (  dat_begin BETWEEN \''.$this->dateFrom.' 00:00:00\' AND \''.$this->dateTo.' 23:59:59\'
                                    OR dat_end   BETWEEN \''.$this->dateFrom.' 00:00:01\' AND \''.$this->dateTo.' 23:59:59\')';
        
            // show all events from category                
            if($this->catId > 0)
            {                 
                // show all events from category
                $sqlConditions .= ' AND cat_id  = '.$this->catId;
            }
        }

        // add conditions for role permission
        if($gCurrentUser->getValue('usr_id') > 0)
        {
            $sqlConditions .= '
            AND (  dtr_rol_id IS NULL 
                OR dtr_rol_id IN (SELECT mem_rol_id 
                                    FROM '.TBL_MEMBERS.' mem2
                                   WHERE mem2.mem_usr_id = '.$gCurrentUser->getValue('usr_id').'
                                     AND mem2.mem_begin  <= dat_begin
                                     AND mem2.mem_end    >= dat_end) ) ';
        }
        else
        {
            $sqlConditions .= ' AND dtr_rol_id IS NULL ';
        }
        
        return $sqlConditions;
        
    }

    //get number of available announcements
    public function getDatesCount()
    {            
        if($this->dateId == 0)
        {     
            global $gCurrentOrganization;
            global $gDb;
            
            $sql = 'SELECT COUNT(DISTINCT dat_id) as count
                      FROM '.TBL_DATE_ROLE.', '. TBL_DATES. ', '. TBL_CATEGORIES. '
                     WHERE dat_cat_id = cat_id
                       AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                           OR (   dat_global   = 1
                              AND cat_org_id IN ('.$gCurrentOrganization->getFamilySQL().') 
                              )
                           )
                       AND dat_id = dtr_dat_id'
                       .$this->sqlConditionsGet();
            $result = $gDb->query($sql);
            $row    = $gDb->fetch_array($result);             
            return $row['count'];
        }
        else
        {
            return 1;
        }
    }
    
    //returns dates
    public function getDates($startElement=0, $limit=NULL)
    {
        global $gCurrentOrganization;
        global $gCurrentUser;
        global $gProfileFields;
        global $gDb;
                       
        //Ankuendigungen aus der DB fischen...
        $sql = 'SELECT DISTINCT cat.*, dat.*, mem.mem_usr_id as member_date_role, mem.mem_leader,
                       cre_surname.usd_value as create_surname, cre_firstname.usd_value as create_firstname,
                       cha_surname.usd_value as change_surname, cha_firstname.usd_value as change_firstname
                  FROM '.TBL_DATE_ROLE.' dtr, '. TBL_CATEGORIES. ' cat, '. TBL_DATES. ' dat
                  LEFT JOIN '. TBL_USER_DATA .' cre_surname 
                    ON cre_surname.usd_usr_id = dat_usr_id_create
                   AND cre_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
                  LEFT JOIN '. TBL_USER_DATA .' cre_firstname 
                    ON cre_firstname.usd_usr_id = dat_usr_id_create
                   AND cre_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
                  LEFT JOIN '. TBL_USER_DATA .' cha_surname
                    ON cha_surname.usd_usr_id = dat_usr_id_change
                   AND cha_surname.usd_usf_id = '.$gProfileFields->getProperty('LAST_NAME', 'usf_id').'
                  LEFT JOIN '. TBL_USER_DATA .' cha_firstname
                    ON cha_firstname.usd_usr_id = dat_usr_id_change
                   AND cha_firstname.usd_usf_id = '.$gProfileFields->getProperty('FIRST_NAME', 'usf_id').'
                  LEFT JOIN '. TBL_MEMBERS. ' mem
                    ON mem.mem_usr_id = '.$gCurrentUser->getValue('usr_id').'
                   AND mem.mem_rol_id = dat_rol_id
                   AND mem_begin <= \''.DATE_NOW.'\'
                   AND mem_end    > \''.DATE_NOW.'\'
                 WHERE dat_cat_id = cat_id
                   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                       OR (   dat_global   = 1
                          AND cat_org_id IN ('.$gCurrentOrganization->getFamilySQL().') ))
                   AND dat_id = dtr_dat_id
                       '.$this->sqlConditionsGet()
                        . ' ORDER BY dat_begin '.$this->order;
         //Parameter        
        if($limit != NULL)
        {
            $sql .= ' LIMIT '.$limit;
        }               
        if($startElement = 0)
        {
            $sql .= ' OFFSET '.$startElement;
        }         
        
        $result = $gDb->query($sql);

        //array für Ergbenisse       
        $dates= array('numResults'=>$gDb->num_rows($result), 'limit' => $limit, 'stratElement'=>$startElement, 'totalCount'=>$this->getDatesCount(), 'dates'=>array());
       
        //Ergebnisse auf Array pushen
        while($row = $gDb->fetch_array($result))
        {           
            $dates['dates'][] = $row; 
        }
       
        return $dates;
    }
}  
?>