<?php

declare(strict_types=1);

namespace Drupal\company;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a company entity type.
 */
interface CompanyInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
