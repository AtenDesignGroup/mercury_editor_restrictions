<?php

namespace Drupal\mercury_editor_restrictions\EventSubscriber;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\Messenger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\layout_paragraphs\Event\LayoutParagraphsAllowedTypesEvent;
use PSpell\Config;

/**
 * Provides Layout Paragraphs Restrictions.
 */
class LayoutParagraphsRestrictions implements EventSubscriberInterface {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * An array of restriction rules.
   *
   * @var array
   */
  protected $restrictions;

  /**
   * Constructor.
   *
   * We use dependency injection get Messenger.
   *
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(Messenger $messenger, ConfigFactoryInterface $config_factory) {
    $this->messenger = $messenger;
    $this->restrictions = $config_factory
      ->get('mercury_editor_restrictions.settings')
      ->get('restrictions');
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      LayoutParagraphsAllowedTypesEvent::EVENT_NAME => ['typeRestrictions', -100],
    ];
  }

  /**
   * Restricts available types based on settings in layout.
   *
   * @param \Drupal\layout_paragraphs\Event\LayoutParagraphsAllowedTypesEvent $event
   *   The allowed types event.
   */
  public function typeRestrictions(LayoutParagraphsAllowedTypesEvent $event) {

    $layout = $event->getLayout();
    $parent_uuid = $event->getParentUuid();
    if (!empty($parent_uuid)) {
      $section = $layout->getLayoutSection($layout->getComponentByUuid($parent_uuid)->getEntity());
      $parent_entity = $section->getEntity();
      $parent_component_type = $parent_entity->bundle();
      $layout_plugin_id = $section->getLayoutId();
    }
    $types = $event->getTypes();
    $entity = $layout->getEntity();
    $bundle = $entity->getEntityTypeId() . ':' . $entity->bundle();
    $region = $event->getRegion() ?? '_root';
    $layout_region = !empty($layout_plugin_id) && !empty($region)
      ? $layout_plugin_id . ':' . $region
      : '';
    $field_name = $layout->getFieldName();

    if ($parent_uuid) {
      $parent_component = $layout->getComponentByUuid($parent_uuid);
      $section = $layout->getLayoutSection($parent_component->getEntity());
      $layout_id = $section->getLayoutId();
      $sibling_components = $section->getComponentsForRegion($region);
    }
    else {
      $layout_id = '';
      $sibling_components = $layout->getRootComponents();
    }

    $matching_restrictions = [];
    foreach ($this->restrictions as $restriction_key => $restriction) {
      $match = FALSE;
      // Match against the bundle of the entity that contains the layout.
      if (!empty($restriction['settings']['bundles'])) {
        $bundles = [];
        foreach ($restriction['settings']['bundles'] ?? [] as $entity => $entity_bundle) {
          $bundles[] = $entity . ':' . $entity_bundle;
        }
        if (in_array($bundle, $bundles)) {
          $match = TRUE;
        }
        else {
          continue;
        }
      }
      // Match against the entity field that contains the layout.
      if (!empty($restriction['settings']['fields'])) {
        if (in_array($field_name, $restriction['settings']['fields'])) {
          $match = TRUE;
        }
        else {
          continue;
        }
      }
      // Match agains layout regions.
      if (!empty($restriction['settings']['layout_regions'])) {
        $matching_layout_regions = [];
        foreach ($restriction['settings']['layout_regions'] as $layout_name => $regions) {
          if (is_array($regions)) {
            foreach ($regions as $region) {
              $matching_layout_regions[] = $layout_name . ':' . $region;
            }
          }
          else {
            $matching_layout_regions[] = $layout_name . ':' . $regions;
          }
        }
        if (in_array($layout_region, $matching_layout_regions)) {
          $match = TRUE;
        }
        elseif (in_array($layout_plugin_id . ':' . '_all', $matching_layout_regions)) {
          $match = TRUE;
        }
        else {
          continue;
        }
      }
      // Match against parent component type.
      if (!empty($restriction['settings']['parent_component_types'])) {
        if (in_array($parent_component_type, $restriction['settings']['parent_component_types'])) {
          $match = TRUE;
        }
        else {
          continue;
        }
      }
      if (!empty($restriction['settings']['_root'])) {
        if ($region === '_root') {
          $matching_restrictions[$restriction_key] = $restriction;
        }
        else {
          continue;
        }
      }

      if ($match) {
        $matching_restrictions[$restriction_key] = $restriction;
      }
    }

    // Process the matching restrictions.
    if ($matching_restrictions) {

      // Build a list of allowed component types from the filtered restrictions.
      $allowed = array_reduce($matching_restrictions, function ($carry, $item) {
        foreach ($item['allow_components'] ?? [] as $allowed_type) {
          $carry[$allowed_type] = TRUE;
        }
        return $carry;
      }, []);

      foreach (array_keys($types) as $key) {
        if (!$allowed[$key]) {
          unset($types[$key]);
        }
      }

      $event->setTypes($types);
    }

  }

  /**
   * Sets the restrictions property.
   *
   * @param array $restrictions
   *   The restrictions.
   */
  protected function setRestrictions(array $restrictions) {
    $this->restrictions = $restrictions;
    return $this;
  }

  /**
   * Filters the restrictions by a property.
   *
   * @param string $filter_property
   *   The property to filter by.
   * @param string $filter_value
   *   The value to filter by.
   *
   * @return \Drupal\mercury_editor_restrictions\EventSubscriber\LayoutParagraphsRestrictions
   *   The current object.
   */
  protected function filterBy($filter_property, $filter_value) {
    $this->restrictions = array_filter(
      $this->restrictions,
      function ($restriction) use ($filter_property, $filter_value) {
        return (self::restrictByProperty($restriction['settings'][$filter_property] ?? NULL, $filter_value));
      }
    );
    return $this;
  }

  /**
   * Returns true if the restriction $property is undefined or contains $value.
   *
   * @param null|array $property
   *   The restriction property.
   * @param null|string $value
   *   The value to check.
   *
   * @return bool
   *   True if matches.
   */
  private static function restrictByProperty($property, $value) {
    return empty($property) ||
      (is_array($property) &&
        in_array($value, $property));
  }

  /**
   * Returns true if the restriction $property is greater than the $value.
   *
   * @param null|array $property
   *   The restriction property.
   * @param null|string $value
   *   The value to check.
   *
   * @return bool
   *   True if matches.
   */
  private static function restrictByCount($property, $value) {
    return empty($property) || $value < $property;
  }

}
