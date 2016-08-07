<?php
/**
 * @version        2.1.0
 * @package        Joomla
 * @subpackage     Membership Pro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2012 - 2015 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die;

error_reporting(0);
if (!file_exists(JPATH_ROOT . '/components/com_osmembership/osmembership.php'))
{
	return;
}

class plgSystemOSMembershipAkeeba extends JPlugin
{
	public function onAfterRoute()
	{
		$app = JFactory::getApplication();

		if ($app->isAdmin())
		{
			return true;
		}

		$option        = JRequest::getCmd('option');
		$view          = JRequest::getCmd('view');
		$paymentMethod = JRequest::getVar('paymentmethod', '');

		if ($option == 'com_akeebasubs' && $view == 'callback' && $paymentMethod == 'paypal')
		{
			// Let Membership Pro handle it
			JRequest::setVar('option', 'com_osmembership');
			JRequest::setVar('task', 'recurring_payment_confirm');
			JRequest::setVar('payment_method', 'os_paypal');

			JRequest::setVar('akeebasubs', 1, 'get');
		}
	}
}

