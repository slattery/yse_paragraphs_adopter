<?php

namespace Drupal\yse_paragraphs_adopter\Service;


//May need to check double assignment thoughtw
//A library item might have paras tp parse,

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\filter\FilterPluginManager;
use Drupal\paragraphs\Entity\Paragraph;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Collector {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Filter manager.
   *
   * @var \Drupal\filter\FilterPluginManager
   */
  protected $filterManager;


  /**
   * Filter manager.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The storage for filter_format config entities.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $filterFormatStorage;

  /**
   * The Embedded Paragraphs Collector filter var/param
   *
   * @var \Drupal\filter\Plugin\FilterInterface
   */
  protected $filter;

  /**
   * array holding examined text formats
   *
   * @var array
   */
  protected $is_applicable;

  /**
   * Constructs a new Embedded Paragraphs Collector
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity field manager service.
   * @param \Drupal\filter\FilterPluginManager $filter_plugin_manager
   *   The filter plugin manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *    The module handler service.
   */

  public function __construct(EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, FilterPluginManager $filter_plugin_manager, ModuleHandlerInterface $module_handler) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->filterManager = $filter_plugin_manager;
    $this->moduleHandler = $module_handler;
    $this->filter = $filter_plugin_manager->createInstance('embedded_paragraphs_collector');
    $this->is_applicable = [];
  }


  function adopt_paragraphs_from_node(ContentEntityInterface $parent_node) {
    $processed = [];
    //TODO needs a polite msg if not installed - could also check type for thirdpartysetting yse_paragraphs_adopter','adopt_embedded_paragraphs
    if ($parent_node->hasField('field_embedded_paragraphs') && $this->moduleHandler->moduleExists('paragraphs_inline_entity_form')) {
      $collected_uuids = $this->process_entity($parent_node);
      if (!empty($collected_uuids)) {
        $processed = $this->process_paragraphs($collected_uuids, $parent_node);
      }
    }
    //maybe we will use a sandbox and batch someday but suspect numbers will be low, so return the array to count.
    return $processed;
  }


  function process_entity(ContentEntityInterface|Paragraph $entity) {
    static $embedded_uuids = [];
    $defs = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    /** @var FieldDefinitionInterface $def */
    foreach ($defs as $f => $def) {
      if ($f != 'field_embedded_paragraphs' && $def->getType() == 'entity_reference_revisions' && $def->getSetting('target_type') == 'paragraph') {
        foreach ($entity->get($f)->referencedEntities() as $delta => $paragraph) {
          if ($paragraph instanceof \Drupal\paragraphs\Entity\Paragraph) {
            $this->process_entity($paragraph);
          }
        }
      }
      elseif (in_array($def->getType(), ['text_long', 'text_with_summary']) and !$entity->get($f)->isEmpty()) {
        if ($this->check_text_format($entity->get($f)->format)) {
          $text = $entity->get($f)->value;
          $text .= $entity->get($f)->summary ?? '';
          /** @var array $field_uuids */
          $field_uuids = $this->filter->process($text);
          array_push($embedded_uuids, ...$field_uuids);
        }
      }
    }
    return $embedded_uuids;
  }

  function process_paragraphs($process_uuids, $host_entity) {
    $processed = $existing = [];
    $parent_type = 'node';
    $parent_field_name = 'field_embedded_paragraphs';
    //grab existing refs
    foreach ($host_entity->get($parent_field_name)->referencedEntities() as $i => $p) {
      $existing[] = $p->id();
    }
    foreach ($process_uuids as $para_uuid) {
      $paragraph = $this->entityTypeManager->getStorage('paragraph')->loadByProperties(['uuid' => $para_uuid]);
      /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
      $paragraph = current($paragraph);

      if ($paragraph instanceof \Drupal\paragraphs\Entity\Paragraph) {
        $existing_parent_id = $paragraph->parent_id->value;
        if (empty($existing_parent_id) or $existing_parent_id == '') {
          $paragraph->set('parent_type', $parent_type);
          $paragraph->set('parent_id', $host_entity->id());
          $paragraph->set('parent_field_name', $parent_field_name);
          $paragraph->save();

          if (!in_array($paragraph->id(), $existing)) {
            $host_entity->get($parent_field_name)->appendItem($paragraph);
          }

          //called in presave hook so I don't need to save $host_entity
          $processed[] = $para_uuid;
        }
      }
    }
    return $processed;
  }

  function check_text_format($fmt) {

    if (!empty($this->is_applicable[$fmt])) {
      return $this->is_applicable[$fmt];
    }

    /** @var \Drupal\filter\Entity\FilterFormat $format */
    $format = $this->entityTypeManager->getStorage('filter_format')->load($fmt);
    if (isset($format) and $format instanceof \Drupal\filter\Entity\FilterFormat) {
      $this->is_applicable[$fmt] = ($format->filters()->has('entity_embed') && $format->filters()->get('entity_embed')->status);
      return $this->is_applicable[$fmt];
    }

    return FALSE;
  }


}











