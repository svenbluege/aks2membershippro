<?php
/**
 * @version        1.0.0
 * @package        Joomla
 * @subpackage     AKS2MembershipPro
 * @author         Tuan Pham Ngoc
 * @copyright      Copyright (C) 2016 Ossolution Team
 * @license        GNU/GPL, see LICENSE.php
 */

// No direct access
defined('_JEXEC') or die;

class Aks2MembershipProController extends JControllerLegacy
{
	public function reset_data()
	{
		$db = JFactory::getDbo();
		$db->truncateTable('#__osmembership_categories');
		$db->truncateTable('#__osmembership_plans');
		$db->truncateTable('#__osmembership_coupons');
		$db->truncateTable('#__osmembership_subscribers');

		$this->setRedirect('index.php?option=com_aks2membershippro', JText::_('Data is clean up. Now, you can start the migration'));
	}

	/**
	 * Migrate categories
	 */
	public function migrate_categories()
	{
		JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_osmembership/table');
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);
		$fields = array_keys($db->getTableColumns('#__osmembership_categories'));

		if (!in_array('category_id', $fields))
		{
			$sql = "ALTER TABLE  `#__osmembership_categories` ADD  `category_id` INT NOT NULL DEFAULT  '0';";
			$db->setQuery($sql);
			$db->execute();
		}

		$query->select('akeebasubs_levelgroup_id, title, enabled')
			->from('#__akeebasubs_levelgroups')
			->order('akeebasubs_levelgroup_id ASC');
		$db->setQuery($query);
		$categories = $db->loadObjectList();

		$row = JTable::getInstance('Category', 'OSMembershipTable');

		foreach ($categories as $category)
		{
			$row->id          = 0;
			$row->title       = $category->title;
			$row->published   = $category->enabled;
			$row->category_id = $category->akeebasubs_levelgroup_id;
			$row->store();
		}

		$numberCategories = count($categories);

		$this->setRedirect('index.php?option=com_aks2membershippro&task=migrate_plans', JText::sprintf('%s categories migrated. Now, the system will migrating plans', $numberCategories));
	}

	/**
	 * Migrate subscription plans
	 */
	public function migrate_plans()
	{
		require_once JPATH_ROOT . '/components/com_osmembership/helper/helper.php';

		JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_osmembership/table');

		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);
		$fields = array_keys($db->getTableColumns('#__osmembership_plans'));

		if (!in_array('plan_id', $fields))
		{
			$sql = "ALTER TABLE  `#__osmembership_plans` ADD  `plan_id` INT NOT NULL DEFAULT  '0';";
			$db->setQuery($sql);
			$db->execute();
		}

		// First store the categories relationship
		$query->select('id, category_id')
			->from('#__osmembership_categories')
			->order('id');
		$db->setQuery($query);
		$categories = $db->loadObjectList('category_id');


		$query->clear();
		$query->select('*')
			->from('#__akeebasubs_levels')
			->order('akeebasubs_level_id');
		$db->setQuery($query);
		$plans = $db->loadObjectList();

		$row = JTable::getInstance('Plan', 'OSMembershipTable');

		foreach ($plans as $plan)
		{
			$row->id                  = 0;
			$row->title               = $plan->title;
			$row->description         = $row->short_description = $plan->description;
			$row->published           = $plan->enabled;
			$row->ordering            = $plan->ordering;
			$row->plan_id             = $plan->akeebasubs_level_id;
			$row->access              = $plan->access;
			$row->lifetime_membership = $plan->forever;
			$row->price               = $plan->price;
			$row->expired_date        = $plan->fixed_date;

			list($unit, $length) = OSMembershipHelper::getRecurringSettingOfPlan($plan->duration);

			$row->subscription_length      = $length;
			$row->subscription_length_unit = $unit;
			$row->recurring_subscription   = $plan->recurring;

			if ($plan->akeebasubs_levelgroup_id && isset($categories[$plan->akeebasubs_levelgroup_id]))
			{
				$row->category_id = $categories[$plan->akeebasubs_levelgroup_id]->id;
			}

			$row->send_first_reminder  = $plan->notify1;
			$row->send_second_reminder = $plan->notify2;

			$row->store();
		}

		$numberPlans = count($plans);

		$this->setRedirect('index.php?option=com_aks2membershippro&task=migrate_subscriptions', JText::sprintf('%s plans migrated. Now, the system will migrating coupons', $numberPlans));
	}

	/**
	 * Migrate coupons
	 */
	public function migrate_coupons()
	{
		$history = "YToyOntzOjEwOiJjcmVhdG9yX2lwIjtzOjM5OiIyYTAyOjEyMGI6YzNkNzo3OTMwOmQxZTA6NTkyNjphNjBkOjQ4MmYiO3M6MjA6InVzZXJzZWxlY3RfcmVjdXJyaW5nIjtzOjE6IjEiO30=";
		$params  = unserialize(base64_decode($history));

		print_r($params);
	}

	/**
	 * Migrate subscriptions
	 */
	public function migrate_subscriptions()
	{
		require_once JPATH_ROOT . '/components/com_osmembership/helper/helper.php';

		JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_osmembership/table');
		$db     = JFactory::getDbo();
		$query  = $db->getQuery(true);
		$fields = array_keys($db->getTableColumns('#__osmembership_subscribers'));

		if (!in_array('subscriber_id', $fields))
		{
			$sql = "ALTER TABLE  `#__osmembership_subscribers` ADD  `subscriber_id` INT NOT NULL DEFAULT  '0';";
			$db->setQuery($sql);
			$db->execute();
		}

		// First store the plans relationship
		$query->select('id, plan_id')
			->from('#__osmembership_plans')
			->order('id');
		$db->setQuery($query);
		$plans = $db->loadObjectList('plan_id');

		$start = $this->input->getInt('start', 0);
		$query->clear();
		$query->select('a.*, a.state AS payment_state')
			->select('b.businessname, b.address1, b.address2, b.city, b.state, b.zip, b.country, b.notes')
			->select('u.name, u.email')
			->from('#__akeebasubs_subscriptions AS a')
			->innerJoin('#__akeebasubs_users AS b ON a.user_id = b.user_id')
			->innerJoin('#__users AS u ON a.user_id = u.id')
			->order('a.akeebasubs_subscription_id');
		$db->setQuery($query, $start, 200);
		$subscriptions       = $db->loadObjectList();
		$numberSubscriptions = count($subscriptions);
		if (count($subscriptions) == 0)
		{
			// No records left, redirect to complete page
			$this->setRedirect('index.php?option=com_aks2membershippro&layout=complete');
		}
		else
		{
			$row = JTable::getInstance('Subscriber', 'OSMembershipTable');

			foreach ($subscriptions as $subscription)
			{
				$row->id = 0;
				$name    = $subscription->name;
				if ($name)
				{
					$pos = strpos($name, ' ');
					if ($pos !== false)
					{
						$row->first_name = substr($name, 0, $pos);
						$row->last_name  = substr($name, $pos + 1);
					}
					else
					{
						$row->first_name = $name;
					}
				}

				$row->address  = $subscription->address1;
				$row->address2 = $subscription->address2;
				$row->city     = $subscription->city;
				$row->state    = $subscription->state;
				$row->zip      = $subscription->zip;
				$row->country  = $subscription->country;
				$row->comment  = $subscription->notes;

				$row->plan_id      = $plans[$subscription->akeebasubs_level_id]->id;
				$row->email        = $subscription->email;
				$row->user_id      = $subscription->user_id;
				$row->created_date = $subscription->created_on;
				$row->payment_date = $subscription->created_on;
				$row->from_date    = $subscription->publish_up;
				$row->to_date      = $subscription->publish_down;

				$row->amount          = $subscription->net_amount;
				$row->tax_amount      = $subscription->tax_amount;
				$row->discount_amount = $subscription->discount_amount;
				$row->gross_amount    = $subscription->gross_amount;
				$row->coupon_id       = $subscription->akeebasubs_coupon_id;
				$row->transaction_id  = $subscription->processor_key;
				if ($subscription->processor)
				{
					$row->payment_method = 'os_' . $subscription->processor;
				}

				switch ($subscription->payment_state)
				{
					case 'N':
					case 'P':
						$row->published = 0;
						break;
					case 'C':
						if ($subscription->enabled)
						{
							$row->published = 1;
						}
						else
						{
							$row->published = 2;
						}
						break;
					case 'X':
						$row->published = 3;
						break;
					default:
						$row->published = 3;
						break;
				}

				$row->subscriber_id = $subscription->akeebasubs_subscription_id;

				$row->act = 'subscribe';

				if (!empty($subscription->params))
				{
					$params               = new JRegistry($subscription->params);
					$row->subscription_id = $params->get('recurring_id');
				}

				$params = new JRegistry();
				$params->set('regular_amount', $subscription->recurring_amount);
				$params->set('regular_discount_amount', 0);
				$params->set('regular_tax_amount', 0);
				$params->set('regular_payment_processing_fee', 0);
				$params->set('regular_gross_amount', $subscription->recurring_amount);
				$row->params = $params->toString();

				// Find and set profile ID
				$profileId = 0;
				if ($row->user_id > 0)
				{
					$query->clear();
					$query->select('id')
						->from('#__osmembership_subscribers')
						->where('is_profile = 1')
						->where('user_id = ' . $row->user_id);
					$db->setQuery($query);
					$profileId = $db->loadResult();
				}

				if ($profileId)
				{
					$row->is_profile = 0;
					$row->profile_id = $profileId;
				}
				else
				{
					$row->is_profile = 1;
				}

				if ($row->amount > 0)
				{
					$row->invoice_number = OSMembershipHelper::getInvoiceNumber($row);
				}

				$row->store();

				if (!$row->profile_id)
				{
					$row->profile_id = $row->id;
					$row->store();
				}
			}

			$start += $numberSubscriptions;
			$this->setRedirect('index.php?option=com_aks2membershippro&layout=form&start=' . $start);
		}
	}
}