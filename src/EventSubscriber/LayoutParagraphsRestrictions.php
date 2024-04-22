<?php

namespace Drupal\mercury_editor_restrictions\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\Messenger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\layout_paragraphs\Event\LayoutParagraphsAllowedTypesEvent;

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
   * The current context will have the following possible key/value pairs:
   *
   * - parent_uuid: The UUID of the parent component.
   * - parent_type: The bundle of the parent component.
   * - sibling_uuid: The UUID of the sibling component.
   * - sibling_type: The bundle of the sibling component.
   * - region: The region name (_root if no region is set).
   * - layout: The layout plugin ID.
   * - placement: The placement of the component (before or after).
   *
   * @param \Drupal\layout_paragraphs\Event\LayoutParagraphsAllowedTypesEvent $event
   *   The allowed types event.
   */
  public function typeRestrictions(LayoutParagraphsAllowedTypesEvent $event) {
    $context = $event->getContext();
    if ($context['parent_uuid']) {
      $parent_paragraph = $event->getLayout()->getComponentByUuid($event->getParentUuid())->getEntity();
      $section = $event->getLayout()->getLayoutSection($parent_paragraph);
      $layout_plugin_id = $section->getLayoutId();
      $context['parent_type'] = $parent_paragraph->bundle();
      $context['layout'] = $layout_plugin_id;
    }
    if ($context['sibling_uuid']) {
      $sibling_paragraph = $event->getLayout()->getComponentByUuid($context['sibling_uuid'])->getEntity();
      $context['sibling_type'] = $sibling_paragraph->bundle();
    }
    if (empty($context['region'])) {
      $context['region'] = '_root';
    }

    $include = [];
    $exclude = [];
    foreach ($this->restrictions as $restriction) {
      foreach ($restriction['context'] as $restriction_context_key => $restriction_context_value) {
        if (strpos($restriction_context_value, '!') === 0) {
          $restriction_context_value = substr($restriction_context_value, 1);
          $test = isset($context[$restriction_context_key])
            && $context[$restriction_context_key] !== $restriction_context_value;
        }
        else {
          $test = isset($context[$restriction_context_key])
            && $context[$restriction_context_key] == $restriction_context_value;
        }
        if ($test === TRUE) {
          if (!empty($restriction['components'])) {
            $include = array_merge($include, array_fill_keys($restriction['components'], TRUE));
          }
          if (!empty($restriction['exclude_components'])) {
            $exclude = array_merge($exclude, array_fill_keys($restriction['exclude_components'], TRUE));
          }
        }
      }
    }
    if ($include) {
      $event->setTypes(array_intersect_key($event->getTypes(), $include));
    }
    if ($exclude) {
      $event->setTypes(array_diff_key($event->getTypes(), $exclude));
    }
  }

}
