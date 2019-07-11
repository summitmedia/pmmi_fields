<?php

/**
 * @file
 * Contains \Drupal\pmmi_field_extras\Plugin\Field\FieldWidget\ViewfieldWidgetWithMore.
 */

namespace Drupal\pmmi_field_extras\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\viewfield\Plugin\Field\FieldWidget\ViewfieldWidget;

/**
 * Plugin implementation of the 'viewfield' widget.
 *
 * @FieldWidget(
 *   id = "viewfield_select_with_more",
 *   label = @Translation("Select List with more link"),
 *   field_types = {
 *     "viewfield"
 *   }
 * )
 */
class ViewfieldWidgetWithMore extends ViewfieldWidget {

  /**
   * Array of default values for this field..
   *
   * @var \Drupal\Core\Field\FieldItemListInterface
   */
  protected $items;

  /**
   * The order of this item in the array of sub-elements (0, 1, 2, etc.).
   *
   * @var int
   */
  protected $delta;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $this->items = $items;
    $this->delta = $delta;

    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $field_name = $this->fieldDefinition->getName();
    $id_prefix = implode('-', array_merge($element['#field_parents'], [$field_name]));
    $wrapper_id = Crypt::hashBase64($id_prefix . '-ajax-wrapper');
    unset($element['settings_wrapper']);
    $element += [
      '#type' => 'container',
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];
    $element['vname']['#ajax'] = [
      'callback' => [get_class($this), 'ajaxRefresh'],
      'wrapper' => $wrapper_id,
    ];

    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element && $triggering_element['#name'] == $field_name . '[' . $delta . '][vname]') {
      $view_name = $triggering_element['#value'];
    }
    elseif (isset($items[$delta]->vname)) {
      $view_name = $items[$delta]->vname;
    }
    if (isset($view_name)) {
      $view = explode('|', $view_name);
      $view_object = $this->getView($view[0], $view[1]);
      $view_instance = $view_object->preview();
      $saved_settings = $this->getSavedSettings();

      $element['view_override_title'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Override title'),
        '#default_value' => isset($saved_settings['view_override_title']) ? $saved_settings['view_override_title'] : FALSE,
      ];
      $element['view_title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('View title'),
        '#default_value' => isset($saved_settings['view_title']) ? $saved_settings['view_title'] : '',
        '#states' => [
          'visible' => [
            'input[name="' . $field_name . '[' . $delta . '][view_override_title]"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $element['more'] = [
        '#type' => 'container',
      ];
      if (!empty($view_instance)) {
        $this->prepareFormElements($element, $view_instance['#view']->display_handler, $view[1]);
        foreach (['attachment_after', 'attachment_before'] as $attachment_position) {
          if (!empty($view_instance['#view']->{$attachment_position})) {
            $attachment = $view_instance['#view']->{$attachment_position}[0]['#view'];
            $this->prepareFormElements($element, $attachment->display_handler, $attachment->current_display, $attachment_position);
          }
        }
      }
      else {
        $this->prepareFormElements($element, $view_object->display_handler, $view[1]);
      }
    }

    return $element;
  }

  /**
   * Get saved settings.
   */
  protected function getSavedSettings() {
    $settings = [];
    if ($values = $this->items[$this->delta]->settings) {
      $settings = Json::decode($values);
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  protected function getViewOptions($handler, $option, $display) {
    $settings = $this->getSavedSettings();
    return isset($settings['more']) && isset($settings['more'][$display]) ? $settings['more'][$display][$option] : $handler->options[$option];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $key => $value) {
      $settings_array = [];
      // Save more link settings.
      foreach (['more', 'view_title', 'view_override_title'] as $setting) {
        $values[$key]['settings'] = isset($values[$key]['settings']) ? $values[$key]['settings'] : '';
        if (!empty($value[$setting])) {
          $settings_array += [$setting => $value[$setting]];
        }
      }
      $values[$key]['settings'] .= Json::encode($settings_array);
    }
    return $values;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    $element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($element['#array_parents'], 0, -1));
    return $element;
  }

  /**
   * Prepare form elements.
   */
  protected function prepareFormElements(&$element, $handler, $display, $attachment_position = '') {
    $input_name = $element['#field_name'] . '[' . $element['#delta'] . '][more][' . $display . '][use_more]';
    $display_name = !empty($attachment_position) ? $attachment_position : $display;
    $element['more'][$display] = [
      '#type' => 'fieldset',
      '#title' => $this->t('More link options for @display display', ['@display' => $display_name]),
    ];
    $element['more'][$display]['use_more'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('More link'),
      '#default_value' => $this->getViewOptions($handler, 'use_more', $display),
    ];
    $element['more'][$display]['use_more_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('More link text'),
      '#default_value' => $this->getViewOptions($handler, 'use_more_text', $display),
      '#states' => [
        'visible' => [
          'input[name="' . $input_name . '"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element['more'][$display]['link_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('More link url'),
      '#default_value' => $this->getViewOptions($handler, 'link_url', $display),
      '#states' => [
        'visible' => [
          'input[name="' . $input_name . '"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getViewSettings($view, $display, $settings) {}

}
