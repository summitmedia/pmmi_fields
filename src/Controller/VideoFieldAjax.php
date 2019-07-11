<?php

namespace Drupal\pmmi_field_extras\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class VideoFieldAjax.
 *
 * @package Drupal\pmmi_field_extras\Controller
 */
class VideoFieldAjax extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new VideoFieldAjax.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Replace main video on Video CT.
   *
   * @param int $node
   *   The node object.
   * @param string $js
   *   The js usage.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Ajax callback.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function replaceVideo($node, $js = 'nojs') {
    if ($js == 'nojs') {
      return new RedirectResponse('/node/' . $node);
    }

    $response = new AjaxResponse();
    $entity = $this->entityTypeManager->getStorage('node')->load($node);

    if ($entity->access('view')) {
      $node_render = $this->entityTypeManager
        ->getViewBuilder($entity->getEntityTypeId())
        ->view($entity, 'video_ajax_mode');

      $node_rendered = render($node_render);
      $response->addCommand(new ReplaceCommand('#video-node-info', $node_rendered));
    }

    return $response;
  }

}
