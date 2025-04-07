<?php

use Drupal\civicrm\CiviCrm;
use Civi\ActionProvider\Conditions\Contribution\ContributionChangedCondition;

class ContributionApprovedChanged extends ContributionChangedCondition {
    protected function getFields() {
        return ['approved_60' => 0]; // Replace XX with the actual custom field ID.
    }
}
