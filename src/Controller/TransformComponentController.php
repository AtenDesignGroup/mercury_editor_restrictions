<?php

namespace Drupal\mercury_editor_restrictions\Controller;

use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Controller\ControllerBase;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\mercury_editor\MercuryEditorContextService;
use Drupal\mercury_editor\Ajax\IFrameAjaxResponseWrapper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_paragraphs\Ajax\LayoutParagraphsEventCommand;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutRefreshTrait;
use Drupal\layout_paragraphs\LayoutParagraphsLayoutTempstoreRepository;

/**
 * Class DuplicateController.
 *
 * Duplicates a component of a Layout Paragraphs Layout.
 * This is a copy of the DuplicateController class from the Layout Paragraphs
 * module.
 *
 * @todo Consider refactoring this class to extend the original class.
 */
class TransformComponentController extends ControllerBase {

  use LayoutParagraphsLayoutRefreshTrait;
  use AjaxHelperTrait;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    protected LayoutParagraphsLayoutTempstoreRepository $tempstore,
    protected IFrameAjaxResponseWrapper $iFrameAjaxResponseWrapper,
    protected MercuryEditorContextService $mercuryEditorContext
    ) {}

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs.tempstore_repository'),
      $container->get('mercury_editor.iframe_ajax_response_wrapper'),
      $container->get('mercury_editor.context')
    );
  }

  /**
   * Duplicates a component and returns appropriate response.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The layout paragraphs layout object.
   * @param string $component_uuid
   *   The source component to be cloned.
   * @param string $variation
   *   The variation to transform the component to.
   *
   * @return array|\Drupal\Core\Ajax\AjaxResponse
   *   A build array or Ajax respone.
   */
  public function transform(LayoutParagraphsLayout $layout_paragraphs_layout, string $component_uuid, string $variation) {
    $this->setLayoutParagraphsLayout($layout_paragraphs_layout);
    $component = $this->layoutParagraphsLayout->getComponentByUuid($component_uuid);
    $paragraph = $component->getEntity();
    $behavior_settings = $paragraph->getAllBehaviorSettings();
    $style_options = $behavior_settings['style_options'] ?? [];

    $variation = explode('__', $variation)[1] ?? $variation;

    foreach ($style_options as $key => $style_option) {
      if (!empty($style_option['component_variation'])) {
        $behavior_settings['style_options'][$key]['component_variation'] = $variation;
      }
    }

    $paragraph->setAllBehaviorSettings($behavior_settings);

    $this->layoutParagraphsLayout->setComponent($paragraph);
    $this->tempstore->set($this->layoutParagraphsLayout);
    $this->mercuryEditorContext->saveLayout($this->tempstore->get($this->layoutParagraphsLayout));

    if ($this->isAjax()) {
      $response = new AjaxResponse();
      if ($this->needsRefresh()) {
        $layout = $this->renderLayout();
        $dom_selector = '[data-lpb-id="' . $this->layoutParagraphsLayout->id() . '"]';
        $response->addCommand(new ReplaceCommand($dom_selector, $layout));
        return $response;
      }
      $uuid = $paragraph->uuid();
      $rendered_item = [
        '#type' => 'layout_paragraphs_builder',
        '#layout_paragraphs_layout' => $this->layoutParagraphsLayout,
        '#uuid' => $uuid,
      ];
      $response->addCommand(new ReplaceCommand('[data-uuid="' . $uuid . '"]', $rendered_item));
      $response->addCommand(new LayoutParagraphsEventCommand($this->layoutParagraphsLayout, $uuid, 'component:update'));
      return $response;
    }
    return [
      '#type' => 'layout_paragraphs_builder',
      '#layout_paragraphs_layout' => $layout_paragraphs_layout,
    ];

  }

}
