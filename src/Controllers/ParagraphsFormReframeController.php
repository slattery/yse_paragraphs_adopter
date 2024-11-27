<?php

namespace Drupal\yse_paragraphs_adopter\Controllers;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;


/**
 * Returns responses for entity browser routes.
 */
class ParagraphsFormReframeController extends ControllerBase {

  /**
   * Return an Ajax dialog command that wraps an iframe,
   * which will serve the form for editing a referenced entity
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity being edited.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently processing request.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An Ajax response with a command for opening or closing the dialog
   *   containing the edit form.
   */


  public function reframe(EntityInterface $entity, Request $request) {
    //make src
    //ship render array to OpenDialogCommand
    $response = new AjaxResponse();
    $entity_type = $entity->getEntityType();


    $trigger_name = $request->request->get('_triggering_element_name');
    $edit_button = (strpos($trigger_name, 'edit_button') !== FALSE);
    if ($edit_button) {
      // Remove posted values from original form to prevent
      // data leakage into this form when the form is of the same bundle.
      $original_request = $request->request;
      $request->request = new InputBag();
    }

    $iframe_src = Url::fromRoute('yse_paragraphs_adopter.edit_form_render', [
      'entity_type' => 'paragraph',
      'entity' => $entity->id(),
    ]);

    //TODO: form_mode replacement is breaking the form
    $iframe_src->setOptions(array('query' => ['form_mode' => 'edit_iframe']));

    $reframe_attributes = new Attribute([
      'src' => $iframe_src->toString(),
      'class' => ['reframe-dialog-iframe'],
      'name' => 'reframe-dialog-iframe',
      'width' => '99%',
      'height' => '500',
      'data-bundle-type' => $entity->getType(),
    ]);

    //passing all but the div-wrapped iframe, letting theme_hook and twig do that.
    $iframe_array = [
      '#theme' => 'reframe_iframe',
      '#iframe_attributes' => $reframe_attributes,
    ];

    $response->setAttachments([
      'library' => [
        'paragraphs_inline_entity_form/dialog',
        'yse_paragraphs_adopter/reframe',
        'core/drupal.dialog.ajax',
      ],
    ]);

    if ($edit_button) {
      $request->request = $original_request;
    }

    $title = $this->t('Edit @entity', ['@entity' => $entity->label()]);

    $response->addCommand(new OpenDialogCommand('#' . $entity->getEntityTypeId() . '-' . $entity->id() . '-edit-dialog', $title, $iframe_array, ['modal' => TRUE, 'width' => '92%', 'dialogClass' => 'reframe-dialog-modal']));
    return $response;
  }

    /**
   * Returns the form for editing a referenced entity
   * uses a standard response in an iframe rather than
   * an Ajax response.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity being edited.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The currently processing request.
   *
   * @return array
   *   A render array containing the paragraph edit form.
   */
  public function getform(EntityInterface $entity, Request $request) {
    //TODO: Not using ajax from here, called in iframe.  hope to reinstate ajax later.
    //$original_request = $request->request;
    //$request->request = new InputBag();

    if (!$entity) {
      // Handle case where paragraph doesn't exist
      return [
        '#type' => 'markup',
        '#markup' => $this->t('Paragraph not found.'),
      ];
    }
    // Use edit form class if it exists, otherwise use default form class.
    $entity_type = $entity->getEntityType();
    $operation = $entity_type->getFormClass('edit') ? 'edit' : 'default';


    if (!empty($operation)) {
      $form_object = $this->entityTypeManager()->getFormObject($entity->getEntityTypeId(), $operation);
      $form_object->setEntity($entity);
      $form_id = $form_object->getFormId();
      $form_state = (new FormState())
        ->setFormObject($form_object)
        ->disableRedirect();
      $entity_form = $this->formBuilder()->buildForm($form_object, $form_state);
      $entity_form['#attached']['library'][] = 'core/drupal.ajax';

      //Add an attribute for hooks to find.
      if (isset($entity_form['#attributes'])){
        $entity_form['#attributes']['data-reframe'] = true;
      }

      //We want the Content/Behavior tabs to show up for the para
      //but we do not use a ref field widget, so there is no place for them.
      //We create this container and use twig to print out the tabs.
      //Sending data-form-id for use in the twig.
      //@see container--paragraphs-formfields.html.twig

      $entity_form['reframe_paragraph_fields'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => [
              'reframe-paragraph-fields'
          ],
          'id' => [
            'reframe-paragraph-fields'
          ],
          'data-form-id' => str_replace('_', '-', $form_id),
        ],
        '#container_attributes' => [
          'data-paragraphs-formfields' => true
        ]
      ];


      foreach (Element::children($entity_form) as $key) {
        //dvm($key);
        if ($key == 'reframe_paragraph_fields') {
          continue;
        }
        // Move elements into the container
        $entity_form['reframe_paragraph_fields'][$key] = $entity_form[$key];
        unset($entity_form[$key]);
      }

      // Update parents recursively
      static::_updateElementParents($entity_form['reframe_paragraph_fields']);
      return $entity_form;
    }
  }

  protected function _updateElementParents(array &$element, array $new_parents = []) {
    // If no new parents are provided, start with an empty array
    if (!empty($element)){
      $current_parents = $new_parents;

      if (isset($element['#name']) ){
        $element['#parents'] = $current_parents;
        $element['#array_parents'] = $current_parents;
      }

      // Recursively process child elements
      foreach (Element::children($element) as $key) {
        if($key && $key !== "") {
        // Add the current child key to the parents
          $child_parents = $current_parents;
          $child_parents[] = $key;

          // Recursively update this child element's parents
          if (isset($element[$key]) && $element[$key] instanceof \Traversable) {
            static::_updateElementParents($element[$key], $child_parents);
            continue;
          }
        }
      }
    }
  }
}


