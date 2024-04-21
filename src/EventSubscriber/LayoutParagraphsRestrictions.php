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
    foreach ($this->restrictions as $key => $restriction) {
      [$key, $value] = explode('=', $key);
      if (isset($context[$key]) && $context[$key] == $value) {
        if ($restriction['components']) {
          $include = array_merge($include, array_fill_keys($restriction['components'], TRUE));
        }
        if ($restriction['exclude_components']) {
          $exclude = array_merge($exclude, array_fill_keys($restriction['exclude_components'], TRUE));
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
