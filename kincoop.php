<?php

require_once 'kincoop.civix.php';

use CRM_Kincoop_ExtensionUtil as E;

const GIFT_FT_NAME = 'Gift';

const REVERSIBLE_AMOUNT_KEYS = array(
  'line_total' => TRUE,
  'line_total_inclusive' => TRUE,
  'net_amount' => TRUE,
  'total_amount' => TRUE,
  'unit_price' => TRUE,
);

/**
 * Implements hook_civicrm_pre
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_pre
 */
function kincoop_civicrm_pre($op, $objectName, $id, &$params) {
  if(isset($params['financial_type_id'])) {
    if (isNewContribution($objectName, $op) && isAssociatedWithGift($params)) {
      reverseSignsOnAmounts($params);
    }
  }
}

/**
 * Implements hook_civirules_alter_trigger_data
 *
 * @link https://docs.civicrm.org/civirules/en/latest/hooks/hook_civirules_alter_trigger_data/
 */
function kincoop_civirules_alter_trigger_data(&$triggerData) {
  $contributionData = $triggerData->getEntityData('Contribution');
  if (isset($contributionData) && isAssociatedWithGift($contributionData)) {
    reassignContactIdToHousehold($triggerData, $contributionData);
  }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function kincoop_civicrm_config(&$config): void {
  _kincoop_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function kincoop_civicrm_install(): void {
  _kincoop_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function kincoop_civicrm_enable(): void {
  _kincoop_civix_civicrm_enable();
}

function isNewContribution($objectName, $op): bool {
  return $objectName == 'Contribution' && $op == 'create';
}

function isAssociatedWithGift($contributionData): bool {
  $financialTypeId = getFromObjectOrArray($contributionData, 'financial_type_id');
  if (!isset($financialTypeId)) {
    return FALSE;
  }
  $ftName = CRM_Core_DAO::singleValueQuery('SELECT name FROM civicrm_financial_type WHERE id = %1',
    array(1 => array($financialTypeId, 'Integer')));
  return $ftName == GIFT_FT_NAME;
}

function reverseSignsOnAmounts(&$params): void {
  array_walk_recursive($params, 'reverseSignIfAppropriate');
}

function reverseSignIfAppropriate(&$item, $key): void {
  if (!isReversibleAmount($key)) {
    return;
  }
  if (is_numeric($item)) {
    $item = -$item;
  } elseif (is_string($item)) {
    $item = '-' . $item;
  }
}

function isReversibleAmount($key): bool {
  return array_key_exists($key, REVERSIBLE_AMOUNT_KEYS);
}

function reassignContactIdToHousehold($triggerData, $contributionData): void {
  $householdContactId = getHouseholdContactId($contributionData);
  if (!isset($householdContactId)) {
    Civi::log()->debug('[' . __FUNCTION__ . '] ' .
      'Warning: no household found for this contribution [#' . $contributionData->id . '].' .
      'This may lead to an unexpected action.');
  }
  $triggerData->setContactId($householdContactId);
}

function getHouseholdContactId($contributionData): ?int {
  $contributionId = getFromObjectOrArray($contributionData, 'id');
  if (!isset($contributionId)) {
    Civi::log()->debug('$contributionId not present');
    return null;
  }
  Civi::log()->debug('[' . __FUNCTION__ . '] $contributionId: ' . $contributionId);

  $contributionCustomGroupTableName = CRM_Core_DAO::singleValueQuery(
    'SELECT table_name FROM civicrm_custom_group WHERE extends = \'Contribution\'');

  $householdCustomFieldId = CRM_Core_DAO::singleValueQuery(
    'SELECT id FROM civicrm_custom_field WHERE name = \'Household\'');
  $householdContactIdColumnName = 'household_' . $householdCustomFieldId;

  return CRM_Core_DAO::singleValueQuery(
    'SELECT ' . $householdContactIdColumnName .
    ' FROM ' . $contributionCustomGroupTableName .
    ' WHERE entity_id = %1',
    array(1 => array($contributionId, 'Integer')));
}

function getFromObjectOrArray($objectOrArray, $key) {
  $array = (array) $objectOrArray;
  return $array[$key];
}
