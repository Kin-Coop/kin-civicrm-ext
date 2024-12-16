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
  Civi::log()->debug('$params: ' . print_r($params, TRUE));

  if(isset($params['financial_type_id'])) {
    if (isGiftRequest($op, $objectName, $params['financial_type_id'])) {
      array_walk_recursive($params, 'reverse_sign_if_appropriate');
    }
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

function isGiftRequest($op, $objectName, $financialTypeId): bool {
  return $objectName == 'Contribution' && $op == 'create' && isAssociatedWithGift($financialTypeId);
}

function isAssociatedWithGift($financial_type_id): bool {
  $ftName = CRM_Core_DAO::singleValueQuery('SELECT name FROM civicrm_financial_type WHERE id = %1',
    array(1 => array($financial_type_id, 'Integer')));
  return $ftName == GIFT_FT_NAME;
}

function reverse_sign_if_appropriate(&$item, $key): void {
  if (!isReversibleAmount($key)) {
    return;
  }
  if (is_numeric($item)) {
    $item = -$item;
  } elseif (is_string($item)) {
    $item = '-' . $item;
  }
}

function isReversibleAmount($key): bool
{
  return array_key_exists($key, REVERSIBLE_AMOUNT_KEYS);
}
