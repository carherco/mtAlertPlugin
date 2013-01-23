<?php

class mtAlertMessagePeer extends BasemtAlertMessagePeer
{
  static public function doSelectPks($criteria = null)
  {
    $criteria = is_null($criteria)? new Criteria() : $criteria;

    $criteria->clearSelectColumns();
    $criteria->addSelectColumn(self::ID);

    $stmt = self::doSelectStmt($criteria);

    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
  }

  /**
   * Returns a criteria that will only selected
   * those alerts that are active.
   *
   * @param $criteria
   *
   * @return a Criteria instance
   */
  static public function doSelectActiveCriteria($criteria = null)			
  {			
    $criteria = is_null($criteria)? new Criteria() : $criteria;			
    $criteria->add(mtAlertMessagePeer::IS_ACTIVE, true);
    $activateRange = $criteria->getNewCriterion(mtAlertMessagePeer::ACTIVATION_DATE, date('Y-m-d'), CRITERIA::LESS_EQUAL);	
    $activateRange->addAnd($criteria->getNewCriterion(mtAlertMessagePeer::DEACTIVATION_DATE, date('Y-m-d'), CRITERIA::GREATER_EQUAL));			
    $activateRange->addOr($criteria->getNewCriterion(mtAlertMessagePeer::ACTIVATION_DATE, null, CRITERIA::ISNULL));		
    $activateRange->addOr($criteria->getNewCriterion(mtAlertMessagePeer::DEACTIVATION_DATE, null, CRITERIA::ISNULL));		
    $criteria->add($activateRange);

    return $criteria;			
  }

  /**
   * Returns a criteria that will only select those 
   * mtAlertMessages that can be showed in a web browser
   *
   * @param Criteria
   *
   * @return a Criteria instance
   */
  static public function doSelectBrowserCriteria($criteria = null)
  {
    $criteria = is_null($criteria)? new Criteria() : $criteria;
    $criteria->add(mtAlertMessagePeer::SHOW_IN_BROWSER, true);
    return $criteria;
  }

  /**
   * Returns a criteria that will only select those
   * mtAlertMessages that can be emailed.
   *
   * @param $criteria
   *
   * @return a Criteria instance
   */
  static public function doSelectMailCriteria($criteria = null)
  {
    $criteria = is_null($criteria)? new Criteria() : $criteria;
    $criteria->add(mtAlertMessagePeer::CAN_BE_MAILED, true);
    return $criteria;
  }

  /**
   * Returns a criteria instance that adds the credentials
   * and user conditions.
   *
   * The criteria will select all the mtAlertMessages that
   * matches with at least one $credential or at least one 
   * $user or are set to be shown to everyone.
   *
   * @param $credentials an array of credentials (strings)
   * @param $usernames   an array of usernames (strings)
   * @param $criteria    a Criteria instance
   *
   * @return a Criteria instance
   */
  static public function doSelectCredentialsUsersCriteria($credentials, $usernames, $criteria = null)
  {
    $criteria = is_null($criteria)? new Criteria() : $criteria;
    $criteria->setDistinct(true);

    $criteria->addJoin(mtAlertMessagePeer::ID, mtAlertMessageCredentialPeer::MT_ALERT_MESSAGE_ID, Criteria::LEFT_JOIN);
    $criteria->addJoin(mtAlertMessagePeer::ID, mtAlertMessageUserPeer::MT_ALERT_MESSAGE_ID, Criteria::LEFT_JOIN);

    $criterion1 = $criteria->getNewCriterion(mtAlertMessageCredentialPeer::CREDENTIAL, $credentials, Criteria::IN);
    $criterion3 = $criteria->getNewCriterion(mtAlertMessageUserPeer::USERNAME, $usernames, Criteria::IN);

    $criterion5 = $criteria->getNewCriterion(mtAlertMessagePeer::SHOW_TO_ALL, true);
    $criterion1->addOr($criterion3);
    $criterion1->addOr($criterion5);

    $criteria->addOr($criterion1);

    return $criteria;
  }

  /**
   * Returns a criteria that filters mtAlertMessages
   * according to the current day name.
   *
   * @param $criteria
   *
   * @return a Criteria instance
   */
  static public function doSelectDayCriteria($criteria = null)
  {
    $criteria = is_null($criteria)? new Criteria() : $criteria;
    $criteria->addJoin(mtAlertMessagePeer::ID, mtAlertMessageDayPeer::MT_ALERT_MESSAGE_ID, CRITERIA::LEFT_JOIN);	
    $dayCriteria = $criteria->getNewCriterion(mtAlertMessageDayPeer::MT_ALERT_DAY_ID, date('w'));		
    $dayCriteria->addOr($criteria->getNewCriterion(mtAlertMessagePeer::SHOW_ALL_DAYS, true));			
    $criteria->addAnd($dayCriteria);

    return $criteria;	
  }
  
  /**
   * Returns a criteria that filters mtAlertMessages	
   * according to the scope.	
   *		
   * @param $criteria			
   *		
   * @return a Criteria instance			
   */			
  static public function doSelectScopeCriteria($criteria = null, $scope = null)			
  {			
    $criteria = is_null($criteria)? new Criteria() : $criteria;			
    if(!is_null($scope))
    {	
       $scopeCriteria = $criteria->getNewCriterion(mtAlertMessagePeer::SCOPE, $scope);
       $scopeCriteria->addOr($criteria->getNewCriterion(mtAlertMessagePeer::SCOPE, null, CRITERIA::ISNULL));		
       $criteria->addAnd($scopeCriteria);		
    }
    return $criteria;			
  }

  /**
   * Returns a criteria that filters by
   *  - username
   *  - day
   *  - active-
   *  - only alerts that can be shown in a web browser
   *  - etc.
   *
   * @param $sf_user a sfUser instance
   * @param $criteria
   *
   * @return $criteria
   */
  static public function doSelectForAuthenticatedUser($sf_user, $criteria = null)
  {
    $criteria = self::doSelectCredentialsUsersCriteria($sf_user->getCredentials(),
                             array(mtAlertUserHelper::getUsername($sf_user)),
                             self::doSelectDayCriteria(self::doSelectActiveCriteria(self::doSelectBrowserCriteria($criteria))));
    $mtAlerts   = array();
    $tmpAlerts  = self::doSelect($criteria);

    /* Check for static condition */
    foreach ($tmpAlerts as $a)
    {
      if ($a->checkCondition() && $a->checkConfiguration(mtAlertUserHelper::getUsername($sf_user)))
      {
        $mtAlerts[] = $a;
      }
    }

    return $mtAlerts;
  }

  /**
   * Returns the appropiate criteria for retrieving alerts
   * from database.
   *
   * @param $sf_user a sfUser instance
   * @param $criteria
   *
   * @return $criteria
   */
  static public function doSelectForUser($sf_user, $scope, $criteria = null)
  {
    if ($sf_user->isAuthenticated())
    {
      return self::doSelectForAuthenticatedUser($sf_user, $scope, $criteria);
    }
    else
    {
      return self::doSelectForNonAuthenticatedUser($sf_user, $scope, $criteria);
    }
  }

  /**
   * Returns a criteria that filters by
   *  - day
   *  - is_active
   *  - browser_only
   *
   * @param $sf_user a sfUser instance
   * @param $criteria
   *
   * @return $criteria
   */
  static public function doSelectForNonAuthenticatedUser($sf_user, $scope, $criteria = null)
  {
    $criteria = self::doSelectCredentialsUsersCriteria($sf_user->getCredentials(),		
                                    array(mtAlertUserHelper::getUsername($sf_user)),
                                    self::doSelectDayCriteria(self::doSelectActiveCriteria(self::doSelectBrowserCriteria(self::doSelectScopeCriteria($criteria,$scope)))));
    $mtAlerts   = array();
    $tmpAlerts  = self::doSelect($criteria);

    /* Check for static condition */
    foreach ($tmpAlerts as $a)
    {
      if ($a->checkCondition())
      {
        $mtAlerts[] = $a;
      }
    }

    return $mtAlerts;
  }
}
