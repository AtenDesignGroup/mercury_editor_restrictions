<?php

namespace Drupal\mercury_editor_restrictions\Form;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mercury Editor Edit Tray settings form.
 */
class MercuryEditorRestrictionsSettingsForm extends ConfigFormBase {

  /**
   * The YAML parser service.
   *
   * @var \Drupal\Component\Serialization\Yaml
   */
  protected $yamlParser;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Serialization\Yaml $yaml_parser
   *   The YAML parser service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    Yaml $yaml_parser
  ) {
    parent::__construct($config_factory);
    $this->yamlParser = $yaml_parser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('serialization.yaml')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mercury_editor_restrictions_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'mercury_editor.restrictions.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('mercury_editor_restrictions.settings');
    $settings_obj = $config->get('restrictions') ?? [];
    $form['settings'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Mercury Editor Restrictions Settings'),
      '#description' => $this->t('Enter the restrictions settings in YAML format.'),
      '#default_value' => $this->yamlParser->encode($settings_obj),
    ];
    if (class_exists('Drupal\codemirror_editor\Element\CodeMirror')) {
      $form['settings']['#type'] = 'codemirror';
      $form['settings']['#codemirror'] = [
        'mode' => 'yaml',
        'lineNumbers' => TRUE,
        'lineWrapping' => TRUE,
        'indentUnit' => 2,
        'indentWithTabs' => FALSE,
        'matchBrackets' => TRUE,
        'autoCloseBrackets' => TRUE,
        'autoCloseTags' => TRUE,
        'styleActiveLine' => TRUE,
        'continueComments' => TRUE,
        'toolbar' => FALSE,
        'extraKeys' => [
          'Ctrl-Space' => 'autocomplete',
        ],
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable('mercury_editor_restrictions.settings');
    $settings_yml_string = $form_state->getValue('settings');
    $config->set('restrictions', $this->yamlParser->decode($settings_yml_string));
    $config->save();
    // Confirmation on form submission.
    $this->messenger()->addMessage($this->t('Mercury Editor restrictions settings have been saved.'));
  }

}
