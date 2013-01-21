<?php

class pluginsMt_alert_message_viewComponents extends sfComponents
{
  /**			
   * Si se le pasa de parámetro 'scope' hará una búsqueda filtrando por dicho ámbito			
   */
  public function executeShow()
  {
    if(!isset($this->scope)) $this->scope = null;
    $criteria = new Criteria();

    $ids      = mtAlertUserHelper::getHideAlertInSessionAttribute($this->getUser());

    if (!empty($ids))
    {
      $criteria->addAnd(mtAlertMessagePeer::ID, $ids, Criteria::NOT_IN);
    }

    $this->mt_alert_messages = mtAlertMessagePeer::doSelectForUser($this->getUser(), $this->scope, $criteria);
  }
}
