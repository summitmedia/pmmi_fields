<?php

namespace Drupal\pmmi_fields\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormComplex;

/**
 * Plugin implementation of the 'pmmi_inline_entity_form_complex' widget.
 *
 * @FieldWidget(
 *   id = "pmmi_inline_entity_form_complex",
 *   label = @Translation("PMMI Inline entity form - Complex"),
 *   field_types = {
 *     "pmmi_entity_reference"
 *   },
 *   multiple_values = true
 * )
 */
class PMMIEntityReferenceEntityWidget extends InlineEntityFormComplex {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Set custom theme function,
    // which will prepare table render array with view mode cell and rows.
    $element['entities']['#theme'] = 'pmmi_fields_entity_table';

    // Add view mode for each entity.
    $entities = $form_state->get([
      'inline_entity_form',
      $this->getIefId(),
      'entities',
    ]);
    foreach ($entities as $key => $value) {
      // Check to see if this entity shouldn't be displayed as a form.
      if (!empty($value['form'])) {
        continue;
      }
      // Build Select list for available entity view modes,
      // this data used by theme_inline_entity_form_entity_table().
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $value['entity'];
      $row = &$element['entities'][$key];

      $options = $this->entityDisplayRepository->getViewModeOptionsByBundle($entity->getEntityTypeId(), $entity->bundle());
      $saved_item = $items->get($key);
      $default_option = 'default';
      $saved_option = empty($saved_item) ? $default_option : $items->get($key)
        ->getValue()['view_mode'];
      if (array_key_exists($saved_option, $options)) {
        $default_option = $saved_option;
      }
      // Add row value for View mode cell.
      $row['view_mode'] = [
        '#type' => 'select',
        '#default_value' => $default_option,
        '#options' => $options,
      ];
      // Add View Mode to a table cell header.
      $element['entities']['#table_fields']['view_mode'] = [
        'type' => 'view_mode',
        'label' => $this->t('View mode'),
        'weight' => 3,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    // Check if item has target_id for adding existing value.
    if (array_key_exists('target_id', $values)) {
      return $values;
    }
    $field_definition = $this->fieldDefinition;
    foreach ($values as &$value) {
      $view_mode = NestedArray::getValue($form_state->getValues(), [
        $field_definition->getName(),
        'entities',
        $value['weight'],
        'view_mode',
      ]);

      $value['view_mode'] = $view_mode;
    }

    return $values;
  }

}
