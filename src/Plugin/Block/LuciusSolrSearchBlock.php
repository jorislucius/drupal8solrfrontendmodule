<?php

/**
 * @file
 * Contains \Drupal\lucius_solr\Plugin\Block\luciusSolrSearchBlock.
 */

namespace Drupal\lucius_solr\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Config\ConfigManager;
use Drupal\search_api_solr\SolrConnector\SolrConnectorPluginManager;
use Drupal\Core\Url;
use Drupal\lucius_solr\Controller\luciusSolrController;

/**
 * Provides a 'luciusSolrSearchBlock' block.
 *
 * @Block(
 *  id = "lucius_solr_search_block",
 *  admin_label = @Translation("lucius solr search block"),
 * )
 */
class luciusSolrSearchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Component\Serialization\Json definition.
   *
   * @var Drupal\Component\Serialization\Json
   */
  protected $serialization_json;

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * Drupal\Core\Render\Renderer definition.
   *
   * @var Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * Drupal\Core\Entity\EntityManager definition.
   *
   * @var Drupal\Core\Entity\EntityManager
   */
  protected $entity_manager;

  /**
   * Drupal\Core\Config\ConfigManager definition.
   *
   * @var Drupal\Core\Config\ConfigManager
   */
  protected $config_manager;

  /**
   * @var \Drupal\search_api_solr\SolrConnector\SolrConnectorPluginManager
   */
  protected $solrConnectorPluginManager;

  /**
   * Construct.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(
        array $configuration,
        $plugin_id,
        $plugin_definition,
        Json $serialization_json,
        RequestStack $request,
        Renderer $renderer, 
        EntityManager $entity_manager, 
        ConfigManager $config_manager,
        SolrConnectorPluginManager $solr_connector_plugin_manager
        ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->serialization_json = $serialization_json;
    $this->request = $request;
    $this->renderer = $renderer;
    $this->entity_manager = $entity_manager;
    $this->config_manager = $config_manager;
    $this->solrConnectorPluginManager = $solr_connector_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('serialization.json'),
      $container->get('request_stack'),
      $container->get('renderer'),
      $container->get('entity.manager'),
      $container->get('config.manager'),
      $container->get('plugin.manager.search_api_solr.connector')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['lucius_solr_filter'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Filter'),
      '#description' => $this->t('De query parameter naamgeving.'),
      '#default_value' => isset($this->configuration['lucius_solr_filter']) ? $this->configuration['lucius_solr_filter'] : 'query',
      '#maxlength' => 64,
      '#size' => 64,
      '#weight' => '0',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['lucius_solr_filter'] = $form_state->getValue('lucius_solr_filter');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // Create new solrcontroller.
    $solr_controller = new luciusSolrController($this->request, $this->solrConnectorPluginManager, $this->entity_manager);
    $query_parameter = $this->configuration['lucius_solr_filter'];
    $results = $solr_controller->search($query_parameter);

    // Set the active query.
    $build['#query'] = $this->request->getCurrentRequest()->get($query_parameter);
    // Check if we have results
    if (!empty($results)) {
      // Prepare results.
      $build['#results'] = array(
        '#theme' => 'item_list',
        '#items' => array(),
      );

      // Loop through and build links.
      foreach ($results as $result) {
        $build['#results']['#items'][] = array(
          '#title' => $result['title'],
          '#type' => 'link',
          '#url' => $result['url'],
        );
      }
    }

    // Set the theme for the content.
    $build['#theme'] = 'lucius_solr_search_block';

    return $build;
  }

}
